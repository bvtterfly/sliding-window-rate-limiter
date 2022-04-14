<?php

namespace Bvtterfly\SlidingWindowRateLimiter;

use Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects\AttemptMiddlewareRuleResult;
use Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects\AttemptResult;
use Closure;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;

class SlidingWindowRateLimiter
{
    /**
     * The configured limit object resolvers.
     *
     * @var array
     */
    protected array $limiters = [];

    public function __construct(protected Factory $factory)
    {
    }

    /**
     * Register a named limiter configuration.
     *
     * @param  string  $name
     * @param  Closure  $callback
     *
     * @return SlidingWindowRateLimiter
     */
    public function for(string $name, Closure $callback): self
    {
        $this->limiters[$name] = $callback;

        return $this;
    }

    /**
     * Get the given named rate limiter.
     *
     * @param  string  $name
     *
     * @return Closure|null
     */
    public function limiter(string $name): ?Closure
    {
        return $this->limiters[$name] ?? null;
    }

    public function attempt(string $key, int $maxAttempts, int $decay = 60): AttemptResult
    {
        if ($this->tooManyAttempts($key, $maxAttempts, $decay)) {
            return new AttemptResult($this->availableIn($key, $maxAttempts, $decay), 0, $maxAttempts);
        }
        $luaArgs = $this->getLuaArgs($key, $decay, $maxAttempts);
        [$retryAfter, $retriesLeft, $limit] = $this->connection()->eval(LuaScripts::attempt(), 1, ...$luaArgs);

        return new AttemptResult($retryAfter, $retriesLeft, $limit);
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decay
     *
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decay = 60): bool
    {
        if ($this->attempts($key, $decay) >= $maxAttempts) {
            return true;
        }

        return false;
    }

    /**
     * Get the number of attempts for the given key for decay time in seconds.
     *
     * @param  string  $key
     * @param  int  $decay
     *
     * @return int
     */
    public function attempts(string $key, int $decay = 60): int
    {
        $luaArgs = $this->getLuaArgs($key, $decay);

        return $this->connection()->eval(LuaScripts::attempts(), 1, ...$luaArgs);
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param  string  $key
     *
     * @return mixed
     */
    public function resetAttempts(string $key): mixed
    {
        $key = $this->getKeyWithPrefix($key);

        return $this->connection()->command('del', [$key]);
    }

    /**
     * Get the number of retries left for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decay
     *
     * @return int
     */
    public function remaining(string $key, int $maxAttempts, int $decay = 60): int
    {
        $attempts = $this->attempts($key, $decay);

        return $maxAttempts - $attempts;
    }

    /**
     * Clear the number of attempts for the given key.
     *
     * @param  string  $key
     *
     * @return void
     */
    public function clear(string $key)
    {
        $this->resetAttempts($key);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decay
     *
     * @return int
     */
    public function availableIn(string $key, int $maxAttempts, int $decay = 60): int
    {
        $luaArgs = $this->getLuaArgs($key, $decay, $maxAttempts);

        return $this->connection()->eval(LuaScripts::availableIn(), 1, ...$luaArgs);
    }

    /**
     * Get the number of retries left for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decay
     *
     * @return int
     */
    public function retriesLeft(string $key, int $maxAttempts, int $decay = 60): int
    {
        return $this->remaining($key, $maxAttempts, $decay);
    }

    public function attemptLimitRules(Collection $rules): AttemptMiddlewareRuleResult
    {
        $luaArgs = $this->getLimitRulesLuaArgs($rules);

        [$retryAfter, $retriesLeft, $limit, $key] = $this->connection()->eval(LuaScripts::attemptMiddlewareRules(), ...$luaArgs);

        $responseCallback = data_get($rules->get($key), 'responseCallback');

        return new AttemptMiddlewareRuleResult($retryAfter, $retriesLeft, $limit, $responseCallback);
    }

    private function getKeyWithPrefix(string $key): string
    {
        return "sliding_rate_limiter:{$key}";
    }

    /**
     * @param  string  $key
     * @param  int[]  $args
     *
     * @return array
     */
    private function getLuaArgs(string $key, ...$args): array
    {
        return [$this->getKeyWithPrefix($key), ...$args];
    }

    private function getLimitRulesLuaArgs(Collection $rules): array
    {
        $keys = $rules->map(fn (
            $limit
        ) => $this->getKeyWithPrefix($limit->key));
        $args = $rules->map(fn ($limit) => [
            $limit->decaySeconds, $limit->maxAttempts,
        ])->flatten();
        $luaArgs = [$keys->count(), ...$keys->all()];
        $luaArgs[] = $keys->count();

        return [...$luaArgs, ...$args->all()];
    }

    private function connection(): Connection
    {
        return $this->factory->connection(config('sliding-window-rate-limiter.use'));
    }
}
