<?php

namespace Bvtterfly\SlidingWindowRateLimiter\Tests;

use Bvtterfly\SlidingWindowRateLimiter\SlidingWindowRateLimiterServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            SlidingWindowRateLimiterServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
    }
}
