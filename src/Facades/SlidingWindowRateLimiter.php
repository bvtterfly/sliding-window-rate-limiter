<?php

namespace Bvtterfly\SlidingWindowRateLimiter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Bvtterfly\SlidingWindowRateLimiter\SlidingWindowRateLimiter
 */
class SlidingWindowRateLimiter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Bvtterfly\SlidingWindowRateLimiter\SlidingWindowRateLimiter::class;
    }
}
