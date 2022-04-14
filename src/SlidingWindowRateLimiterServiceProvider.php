<?php

namespace Bvtterfly\SlidingWindowRateLimiter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SlidingWindowRateLimiterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('sliding-window-rate-limiter')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SlidingWindowRateLimiter::class);
    }
}
