<?php

namespace Bvtterfly\SlidingWindowRateLimiter\RateLimiting;

class Limit
{
    public const MINUTE = 60;

    public const HOUR = 3600;

    public const DAY = 86400;

    /**
     * The rate limit signature key.
     *
     * @var mixed|string
     */
    public mixed $key;

    /**
     * The maximum number of attempts allowed within the given number of seconds.
     */
    public int $maxAttempts;

    /**
     * The number of seconds until the rate limit is reset.
     */
    public int $decay;

    /**
     * The response generator callback.
     *
     * @var null|callable
     */
    public $responseCallback = null;

    /**
     * Create a new limit instance.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decay
     *
     * @return void
     */
    public function __construct(string $key = '', int $maxAttempts = 60, int $decay = 60)
    {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decay = $decay;
    }

    /**
     * Create a new rate limit.
     *
     * @param  int  $maxAttempts
     *
     * @return self
     */
    public static function perMinute(int $maxAttempts): self
    {
        return new self('', $maxAttempts);
    }

    /**
     * Create a new rate limit using minutes as decay time.
     *
     * @param  int  $decayMinutes
     * @param  int  $maxAttempts
     *
     * @return self
     */
    public static function perMinutes(int $decayMinutes, int $maxAttempts): self
    {
        return new self('', $maxAttempts, $decayMinutes * self::MINUTE);
    }

    /**
     * Create a new rate limit using seconds as decay time.
     *
     * @param  int  $decay
     * @param  int  $maxAttempts
     *
     * @return self
     */
    public static function perSeconds(int $decay, int $maxAttempts): self
    {
        return new self('', $maxAttempts, $decay);
    }

    /**
     * Create a new rate limit using hours as decay time.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayHours
     *
     * @return self
     */
    public static function perHour(int $maxAttempts, int $decayHours = 1): self
    {
        return new self('', $maxAttempts, $decayHours * self::HOUR);
    }

    /**
     * Create a new rate limit using days as decay time.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayDays
     *
     * @return self
     */
    public static function perDay($maxAttempts, $decayDays = 1): self
    {
        return new self('', $maxAttempts, $decayDays * self::DAY);
    }

    /**
     * Create a new unlimited rate limit.
     *
     * @return Unlimited
     */
    public static function none()
    {
        return new Unlimited();
    }

    /**
     * Set the key of the rate limit.
     *
     * @param  string  $key
     *
     * @return $this
     */
    public function by($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set the callback that should generate the response when the limit is exceeded.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function response(callable $callback)
    {
        $this->responseCallback = $callback;

        return $this;
    }
}
