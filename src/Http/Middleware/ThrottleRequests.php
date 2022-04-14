<?php

namespace Bvtterfly\SlidingWindowRateLimiter\Http\Middleware;

use Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects\AttemptMiddlewareRuleResult;
use Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects\AttemptResult;
use Bvtterfly\SlidingWindowRateLimiter\RateLimiting\Unlimited as SlidingUnlimited;
use Bvtterfly\SlidingWindowRateLimiter\SlidingWindowRateLimiter;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRequests
{
    /**
     * Create a new request throttler.
     *
     * @param  SlidingWindowRateLimiter  $limiter
     * @param  RateLimiter  $laravelLimiter
     * @return void
     */
    public function __construct(protected SlidingWindowRateLimiter $limiter, protected RateLimiter $laravelLimiter)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     *
     * @return Response
     *
     * @throws ThrottleRequestsException|HttpResponseException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (is_string($maxAttempts)
            && func_num_args() === 3
            && ! is_null($limiter = $this->getLimiterByName($maxAttempts))) {
            return $this->handleRequestUsingNamedLimiter($request, $next, $maxAttempts, $limiter);
        }

        return $this->handleRequest(
            $request,
            $next,
            collect([
                (object) [
                    'key' => $prefix.$this->resolveRequestSignature($request),
                    'maxAttempts' => $this->resolveMaxAttempts($request, $maxAttempts),
                    'decaySeconds' => $decayMinutes * 60,
                    'responseCallback' => null,
                ],
            ])
        );
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string  $limiterName
     * @param  Closure  $limiter
     * @return Response
     *
     * @throws ThrottleRequestsException|HttpResponseException
     */
    protected function handleRequestUsingNamedLimiter($request, Closure $next, $limiterName, Closure $limiter): Response
    {
        $limiterResponse = $limiter($request);

        if ($limiterResponse instanceof Response) {
            return $limiterResponse;
        } elseif ($limiterResponse instanceof Unlimited || $limiterResponse instanceof SlidingUnlimited) {
            return $next($request);
        }

        return $this->handleRequest(
            $request,
            $next,
            collect(Arr::wrap($limiterResponse))->map(function ($limit) use ($limiterName) {
                $key = md5($limiterName.$limit->key);
                $maxAttempts = $limit->maxAttempts;
                $responseCallback = $limit->responseCallback;
                if ($limit instanceof Limit) {
                    $decaySeconds = $limit->decayMinutes * 60;
                } else {
                    $decaySeconds = $limit->decay;
                }

                return (object) [
                    'key' => $key,
                    'maxAttempts' => $maxAttempts,
                    'decaySeconds' => $decaySeconds,
                    'responseCallback' => $responseCallback,
                ];
            })
        );
    }

    /**
     * Get the limiter by name from SlidingWindowRateLimiter, or as a fallback, take it from Laravel RateLimiter
     *
     * @param  string  $name
     *
     * @return Closure|null
     */
    protected function getLimiterByName(string $name): ?Closure
    {
        $limiter = $this->limiter->limiter($name);

        if (! $limiter) {
            $limiter = $this->laravelLimiter->limiter($name);
        }

        return $limiter;
    }

    /**
     * Resolve the number of attempts if the user is authenticated or not.
     *
     * @param  Request  $request
     * @param  int|string  $maxAttempts
     *
     * @return int
     */
    protected function resolveMaxAttempts($request, $maxAttempts)
    {
        if (is_string($maxAttempts) && str_contains($maxAttempts, '|')) {
            $maxAttempts = explode('|', $maxAttempts, 2)[$request->user() ? 1 : 0];
        }

        if (! is_numeric($maxAttempts) && $request->user()) {
            $maxAttempts = $request->user()->{$maxAttempts};
        }

        $maxAttempts = (int) $maxAttempts;

        if ($maxAttempts === 0) {
            throw new RuntimeException('Unable to rate limit if max attempts equal to 0');
        }

        return $maxAttempts;
    }

    /**
     * Resolve request signature.
     *
     * @param  Request  $request
     * @return string
     *
     * @throws RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return sha1($user->getAuthIdentifier());
        } elseif ($route = $request->route()) {
            return sha1($route->getDomain().'|'.$request->ip());
        }

        throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  Collection  $limits
     *
     * @return Response
     *
     * @throws ThrottleRequestsException|HttpResponseException
     */
    protected function handleRequest($request, Closure $next, Collection $limits)
    {
        $result = $this->limiter->attemptLimitRules($limits);

        if (! $result->successful()) {
            throw $this->buildException($request, $result);
        }

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $result
        );
    }

    /**
     * Create a 'too many attempts' exception.
     *
     * @param  Request  $request
     * @param  AttemptMiddlewareRuleResult  $result
     * @return ThrottleRequestsException|HttpResponseException
     */
    protected function buildException(Request $request, AttemptMiddlewareRuleResult $result)
    {
        $headers = $this->getHeaders($result);

        return is_callable($result->responseCallback)
            ? new HttpResponseException(($result->responseCallback)($request, $headers))
            : new ThrottleRequestsException('Too Many Attempts.', null, $headers);
    }

    /**
     * Add the limit header information to the given response.
     *
     * @param  Response  $response
     * @param  AttemptResult  $result
     * @return Response
     */
    protected function addHeaders(Response $response, AttemptResult $result)
    {
        $response->headers->add(
            $this->getHeaders($result)
        );

        return $response;
    }

    /**
     * Get the limit headers information.
     *
     * @param  AttemptResult  $result
     * @return array
     */
    protected function getHeaders($result)
    {
        $headers = [
            'X-RateLimit-Limit' => $result->limit,
            'X-RateLimit-Remaining' => $result->retriesLeft,
        ];

        if (! $result->successful()) {
            $headers['Retry-After'] = $result->retryAfter;
            $headers['X-RateLimit-Reset'] = $result->availableAt();
        }

        return $headers;
    }
}
