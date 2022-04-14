<?php

use Bvtterfly\SlidingWindowRateLimiter\SlidingWindowRateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');
    $this->limiter = app(SlidingWindowRateLimiter::class);
});

afterEach(function () {
    Redis::connection(Config::get('sliding-window-rate-limiter.use'))->del('sliding_rate_limiter:__TEST_KEY__');
});

it('can attempt and get result', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 30, 100);
    expect($result)->successful()->toBeTrue();
    expect($result)->availableAt()->toEqual(Carbon::now()->addRealSeconds(0)->getTimestamp());
    expect($result)->retryAfter->toEqual(0);
    expect($result)->retriesLeft->toEqual(29);
    expect($result)->limit->toEqual(30);
});

it('can check too many attempts', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 1, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 1, 10);
    expect($result)->successful()->toBeFalse();
    expect($this->limiter)->tooManyAttempts('__TEST_KEY__', 1, 10)->toBeTrue();
});

it('can get attempts', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(2);
});

it('can reset attempts', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(2);
    expect($this->limiter)->resetAttempts('__TEST_KEY__')->toEqual(1);
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(0);
});

it('can get remaining', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(2);
    expect($this->limiter)->remaining('__TEST_KEY__', 3, 10)->toEqual(1);
});

it('can clear key', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(2);
    $this->limiter->clear('__TEST_KEY__');
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(0);
});

it('get available in', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 2, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 2, 10);
    expect($result)->successful()->toBeTrue();
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(2);
    expect($this->limiter)->tooManyAttempts('__TEST_KEY__', 2, 10)->toBeTrue();
    $window = 10;
    expect($this->limiter)->availableIn('__TEST_KEY__', 2, $window)->toBeGreaterThanOrEqual($window - 1)->toBeLessThanOrEqual($window);
});

it('get retries left', function () {
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    $result = $this->limiter->attempt('__TEST_KEY__', 3, 10);
    expect($result)->successful()->toBeTrue();
    expect($this->limiter)->attempts('__TEST_KEY__', 10)->toEqual(2);
    expect($this->limiter)->retriesLeft('__TEST_KEY__', 3, 10)->toEqual(1);
});
