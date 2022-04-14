<?php

use Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects\AttemptResult;
use Illuminate\Support\Carbon;

it('can check successful attempt', function () {
    $attemptResult = new AttemptResult(0, 10, 30);
    expect($attemptResult)->successful()->toBeTrue();
    $attemptResult = new AttemptResult(0, 0, 30);
    expect($attemptResult)->successful()->toBeTrue();
    $attemptResult = new AttemptResult(10, 0, 30);
    expect($attemptResult)->successful()->toBeFalse();
});

it('can return available at', function () {
    $attemptResult = new AttemptResult(0, 10, 30);
    expect($attemptResult)->availableAt()->toEqual(Carbon::now()->addRealSeconds(0)->getTimestamp());
    $attemptResult = new AttemptResult(11, 0, 30);
    expect($attemptResult)->availableAt()->toEqual(Carbon::now()->addRealSeconds(11)->getTimestamp());
});
