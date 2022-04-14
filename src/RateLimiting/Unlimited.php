<?php

namespace Bvtterfly\SlidingWindowRateLimiter\RateLimiting;

class Unlimited extends GlobalLimit
{
    /**
     * Create a new limit instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(PHP_INT_MAX);
    }
}
