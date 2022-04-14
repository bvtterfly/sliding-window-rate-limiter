<?php

use Bvtterfly\SlidingWindowRateLimiter\Facades\SlidingWindowRateLimiter;
use Bvtterfly\SlidingWindowRateLimiter\Http\Middleware\ThrottleRequests;
use Bvtterfly\SlidingWindowRateLimiter\RateLimiting\Limit as SlidingLimit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

it('should skip unlimited rate limiters', function () {
    SlidingWindowRateLimiter::for('unlimited', function (Request $request) {
        return Limit::none();
    });

    SlidingWindowRateLimiter::for('unlimited-2', function (Request $request) {
        return SlidingLimit::none();
    });

    Route::get('/', function () {
        return 'yes';
    })->middleware([ThrottleRequests::class.':unlimited', ThrottleRequests::class.':unlimited-2']);

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->headers)->has('X-RateLimit-Limit')->toBeFalse();
});

it('can limit a route by named limiter', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');
    SlidingWindowRateLimiter::for('test', function (Request $request) {
        return SlidingLimit::perSeconds(30, 3);
    });

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':test');

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(3);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(2);

    $result = Redis::connection(
        Config::get('sliding-window-rate-limiter.use')
    )->del('sliding_rate_limiter:'.md5('test'));

    expect($result)->toEqual(1);
});

it('can limit a route by named limiter from laravel rate limiter', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');
    \Illuminate\Support\Facades\RateLimiter::for('test', function (Request $request) {
        return Limit::perMinute(5);
    });

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':test');

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(5);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(4);

    $result = Redis::connection(
        Config::get('sliding-window-rate-limiter.use')
    )->del('sliding_rate_limiter:'.md5('test'));

    expect($result)->toEqual(1);
});

it('can limit a route by named limiter and add necessary headers', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');

    SlidingWindowRateLimiter::for('test', function (Request $request) {
        return Limit::perMinute(3);
    });

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':test');

    $this->withoutExceptionHandling()->get('/');
    $this->withoutExceptionHandling()->get('/');
    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(3);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(0);

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (ThrottleRequestsException $exception) {
        $result = Redis::connection(
            Config::get('sliding-window-rate-limiter.use')
        )->del('sliding_rate_limiter:'.md5('test'));
        expect($result)->toEqual(1);
        $headers = $exception->getHeaders();
        expect($headers['Retry-After'])->toBeLessThanOrEqual(60)->toBeGreaterThanOrEqual(59);
        expect($headers['X-RateLimit-Reset'])->toBeLessThanOrEqual(getAvailableAt(60))->toBeGreaterThanOrEqual(getAvailableAt(59));
    }
});

it('must throw an exception for undefined named limiter ($maxAttempts = 0)', function () {
    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':__test__');

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (Exception $exception) {
        expect($exception)->toBeInstanceOf(RuntimeException::class);
        expect($exception->getMessage())->toEqual('Unable to rate limit if max attempts equal to 0');
    }
});

it('can limit by passing parameters', function () {
    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':3,1');

    $this->withoutExceptionHandling()->get('/');
    $this->withoutExceptionHandling()->get('/');
    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(3);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(0);

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (ThrottleRequestsException $exception) {
        $result = Redis::connection(
            Config::get('sliding-window-rate-limiter.use')
        )->del('sliding_rate_limiter:'.sha1('|127.0.0.1'));
        expect($result)->toEqual(1);
        $headers = $exception->getHeaders();
        expect($headers['Retry-After'])->toBeLessThanOrEqual(60)->toBeGreaterThanOrEqual(59);
        expect($headers['X-RateLimit-Reset'])->toBeLessThanOrEqual(getAvailableAt(60))->toBeGreaterThanOrEqual(getAvailableAt(59));
    }
});

it('must return response if named limiter return response', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');

    SlidingWindowRateLimiter::for('test', function (Request $request) {
        return response()->json(['ok' => true]);
    });

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':test');

    $response = $this->withoutExceptionHandling()->get('/');

    $response->assertJson(['ok' => true]);
});

it('must return http response exception if named limiter has response callback', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');

    SlidingWindowRateLimiter::for('test', function (Request $request) {
        return SlidingLimit::perMinute(1)->response(fn () => response()->json('HttpResponseException', 421));
    });

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':test');

    $this->withoutExceptionHandling()->get('/');

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (HttpResponseException $exception) {
        $result = Redis::connection(
            Config::get('sliding-window-rate-limiter.use')
        )->del('sliding_rate_limiter:'.md5('test'));
        expect($result)->toEqual(1);
        $response = $exception->getResponse();
        expect($response)->getStatusCode()->toEqual(421);
        expect($response)->getContent()->toEqual('"HttpResponseException"');
    }
});

it('can resolveMaxAttempts if it contains "|"', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':3|5,1');

    Route::get('/user', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':3|5,1');

    $this->withoutExceptionHandling()->get('/');
    $this->withoutExceptionHandling()->get('/');
    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(3);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(0);

    $result = Redis::connection(
        Config::get('sliding-window-rate-limiter.use')
    )->del('sliding_rate_limiter:'.sha1('|127.0.0.1'));
    expect($result)->toEqual(1);

    $user = new \Illuminate\Foundation\Auth\User();
    $user->id = 1000;

    $response = $this->withoutExceptionHandling()->actingAs($user)->get('/user');

    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(5);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(4);

    $result = Redis::connection(
        Config::get('sliding-window-rate-limiter.use')
    )->del('sliding_rate_limiter:'.sha1('1000'));
    expect($result)->toEqual(1);
});

it('can resolveMaxAttempts from user property', function () {
    Config::set('database.redis.options.prefix', '__LARAVEL__TEST__');

    Route::get('/user', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':max_attempts,1');

    $user = new \Illuminate\Foundation\Auth\User();
    $user->id = 1000;
    $user->max_attempts = 17;

    $response = $this->withoutExceptionHandling()->actingAs($user)->get('/user');

    expect($response->headers)->has('X-RateLimit-Limit')->toBeTrue();
    expect($response->headers)->has('X-RateLimit-Remaining')->toBeTrue();
    expect($response->headers)->get('X-RateLimit-Limit')->toEqual(17);
    expect($response->headers)->get('X-RateLimit-Remaining')->toEqual(16);

    $result = Redis::connection(
        Config::get('sliding-window-rate-limiter.use')
    )->del('sliding_rate_limiter:'.sha1('1000'));
    expect($result)->toEqual(1);
});

function getAvailableAt($delay): int
{
    return Carbon::now()->addRealSeconds($delay)->getTimestamp();
}
