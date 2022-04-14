<?php

use Bvtterfly\SlidingWindowRateLimiter\RateLimiting\Limit;

it('can create a per minute limiter', function () {
    $limiter = Limit::perMinute(98);
    expect($limiter)->maxAttempts->toEqual(98);
    expect($limiter)->decay->toEqual(Limit::MINUTE);
    $limiter = Limit::perMinutes(10, 199);
    expect($limiter)->maxAttempts->toEqual(199);
    expect($limiter)->decay->toEqual(10 * Limit::MINUTE);
});

it('can create a per hour limiter', function () {
    $limiter = Limit::perHour(98);
    expect($limiter)->maxAttempts->toEqual(98);
    expect($limiter)->decay->toEqual(Limit::HOUR);
    $limiter = Limit::perHour(100, 12);
    expect($limiter)->maxAttempts->toEqual(100);
    expect($limiter)->decay->toEqual(12 * Limit::HOUR);
});

it('can create a per day limiter', function () {
    $limiter = Limit::perDay(1000);
    expect($limiter)->maxAttempts->toEqual(1000);
    expect($limiter)->decay->toEqual(Limit::DAY);
    $limiter = Limit::perHour(1000, 30);
    expect($limiter)->maxAttempts->toEqual(1000);
    expect($limiter)->decay->toEqual(30 * Limit::HOUR);
});

it('can create a limiter with key', function () {
    $limiter = Limit::perDay(1000);
    $limiter->by('test__key');
    expect($limiter)->key->toEqual('test__key');
});
