# Laravel Sliding Window Rate Limiter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bvtterfly/sliding-window-rate-limiter.svg?style=flat-square)](https://packagist.org/packages/bvtterfly/sliding-window-rate-limiter)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/bvtterfly/sliding-window-rate-limiter/run-tests?label=tests)](https://github.com/bvtterfly/sliding-window-rate-limiter/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/bvtterfly/sliding-window-rate-limiter/Check%20&%20fix%20styling?label=code%20style)](https://github.com/bvtterfly/sliding-window-rate-limiter/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/bvtterfly/sliding-window-rate-limiter.svg?style=flat-square)](https://packagist.org/packages/bvtterfly/sliding-window-rate-limiter)

This package provides an easy way to limit any action during a specified time window. You may be familiar with Laravel's Rate Limiter,
It has a similar API, but it uses the Sliding Window algorithm and requires Redis.


## Installation

You can install the package via composer:

```bash
composer require bvtterfly/sliding-window-rate-limiter
```


You can publish the config file with:

```bash
php artisan vendor:publish --tag="sliding-window-rate-limiter-config"
```

This is the contents of the published config file:

```php
return [
    'use' => 'default',
];
```
The package relies on Redis and requires a Redis connection, and you choose which Redis connection to use.

## Usage

The `Bvtterfly\SlidingWindowRateLimiter\Facades\SlidingWindowRateLimiter` facade may be used to interact with the rate limiter.

The simplest method offered by the rate limiter is the `attempt` method, which rate limits an action for a given number of seconds.
The `attempt` method returns a result object that specifies if an attempt was successful and how many attempts remain. If the attempt is unsuccessful, you can get the number of seconds until the action is available again.

```php
use Bvtterfly\SlidingWindowRateLimiter\Facades\SlidingWindowRateLimiter;

$result = SlidingWindowRateLimiter::attempt(
    'send-message:'.$user->id,
    $maxAttempts = 5,
    $decayInSeconds = 60
);

if ($result->successful()) {
    // attempt is successful, do awesome thing... 
} else {
    // attempt is failed, you can get when you can retry again
    // use $result->retryAfter for getting the number of seconds until the action is available again
    // or use $result->availableAt() for getting UNIX timestamp instead.

}
```
You can call the following methods on the `SlidingWindowRateLimiter`:

### tooManyAttempts
```php
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
```

### attempts
```php
/**
 * Get the number of attempts for the given key for decay time in seconds.
 *
 * @param  string  $key
 * @param  int  $decay
 * 
 * @return int
 */
public function attempts(string $key, int $decay = 60): int
```

### resetAttempts
```php
/**
 * Reset the number of attempts for the given key.
 *
 * @param  string  $key
 * 
 * @return mixed
 */
public function resetAttempts(string $key): mixed
```

### remaining
```php
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
```

### clear
```php
/**
 * Clear the number of attempts for the given key.
 *
 * @param  string  $key
 *
 * @return void
 */
public function clear(string $key)
```

### availableIn
```php
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
```

### retriesLeft
```php
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
```

## Route Rate Limiting

Package designed to rate limit actions in a seconds-based system, so it needs its rate limiters classes and lets you configure rate limiters for less than a minute. Still, for ease of usage of this package, It supports default Laravel's Rate Limiters.

This package comes with a `throttle` middleware for Route Rate Limiting, which acts as default `throttle` middleware.
This middleware tries to get a named rate limiter from the `SlidingWindowRateLimiter` or, as a fallback, it will take them from Laravel RateLimiter.

You may wish to change the mapping of `throttle` middleware in your application's HTTP kernel(`App\Http\Kernel`) to use `\Bvtterfly\SlidingWindowRateLimiter\Http\Middleware\ThrottleRequests` class.

## Defining Rate Limiters

> `SlidingWindowRateLimiter` rate limiters are heavily based on Laravel's rate limiters. It only differs in the fact that it is seconds-based. So, before getting started, be sure to read about them on [Laravel docs](https://laravel.com/docs/routing#defining-rate-limiters).

Limit configurations are instances of the `Bvtterfly\SlidingWindowRateLimiter\Limit` class, and It contains helpful "builder" methods to define your rate limits quickly. The rate limiter name may be any string you wish.

For limiting to 500 requests in 45 seconds:

```php
use Bvtterfly\SlidingWindowRateLimiter\Limit;
use Bvtterfly\SlidingWindowRateLimiter\Facades\SlidingWindowRateLimiter;
 
/**
 * Configure the rate limiters for the application.
 *
 * @return void
 */
protected function configureRateLimiting()
{
    SlidingWindowRateLimiter::for('global', function (Request $request) {
        return Limit::perSeconds(45, 500);
    });
}
```

If the incoming request exceeds the specified rate limit, a response with a 429 HTTP status code will automatically be returned by Laravel. If you would like to define your response that a rate limit should return, you may use the `response` method:

```php
SlidingWindowRateLimiter::for('global', function (Request $request) {
    return Limit::perSeconds(45, 500)->response(function () {
        return response('Custom response...', 429);
    });
});
```

You can have multiple rate limits. This configuration will limit only 100 requests per 30 seconds and 1000 requests per day:

```php
SlidingWindowRateLimiter::for('global', function (Request $request) {
    return [
        Limit::perSeconds(30, 100),
        Limit::perDay(1000)
    ];
});
```
Incoming HTTP request instances are passed to rate limiter callbacks, and the rate limit may be calculated dynamically depending on the user or request:

```php
SlidingWindowRateLimiter::for('uploads', function (Request $request) {
    return $request->user()->vipCustomer()
                ? Limit::none()
                : Limit::perMinute(100);
});
```

There may be times when you wish to segment rate limits by some arbitrary value. For example, you may want to allow users to access a given route with 100 requests per minute per authenticated user ID and 10 requests per minute per IP address for guests. Using the `by` a method, you can create your rate limit as follows:

```php
SlidingWindowRateLimiter::for('uploads', function (Request $request) {
    return $request->user()
                ? Limit::perMinute(100)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
});
```

## Attaching Rate Limiters To Routes

Rate limiters can be attached to routes or route groups using the `throttle` middleware. The `throttle` middleware accepts the name of the rate limiter you wish to assign to the route:

```php
Route::middleware(['throttle:media'])->group(function () {
    
    Route::post('/audio', function () {
        //
    })->middleware('throttle:uploads');
 
    Route::post('/video', function () {
        //
    })->middleware('throttle:uploads');
    
});
```


## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ari](https://github.com/bvtterfly)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
