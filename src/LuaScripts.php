<?php

namespace Bvtterfly\SlidingWindowRateLimiter;

class LuaScripts
{
    public static function attemptMiddlewareRules(): string
    {
        return <<<'LUA'
            local current_time = redis.call('TIME')
            local num_windows = ARGV[1]
            local min_retries_left = 1000000
            local requests_limit = 0
            local requests_limit_key = 0
            for i=2, num_windows*2, 2 do
                local window = ARGV[i]
                local max_requests = ARGV[i+1]
                local curr_key = KEYS[i/2]
                local trim_time = tonumber(current_time[1]) - window
                redis.call('ZREMRANGEBYSCORE', curr_key, 0, trim_time)
                local request_count = redis.call('ZCARD',curr_key)
                if request_count >= tonumber(max_requests) then
                    local elements = redis.call('zrange', curr_key, 0, 0, 'WITHSCORES')
                    local next_ts = elements[2] + window
                    local available_in = next_ts - tonumber(current_time[1])
                    return {available_in, 0, tonumber(max_requests), i/2 - 1}
                else
                    local retries_left = tonumber(max_requests) - request_count
                    if min_retries_left > retries_left  then
                        min_retries_left = retries_left - 1
                        requests_limit = tonumber(max_requests)
                        requests_limit_key = i/2 - 1
                    end
                end
            end
            for i=2, num_windows*2, 2 do
                local curr_key = KEYS[i/2]
                local window = ARGV[i]
                redis.call('ZADD', curr_key, current_time[1], current_time[1] .. current_time[2])
                redis.call('EXPIRE', curr_key, window)
            end
            return {0, min_retries_left, requests_limit, requests_limit_key}
LUA;
    }

    public static function attempt(): string
    {
        return <<<'LUA'
            local current_time = redis.call('TIME')
            local key = KEYS[1]
            local window = tonumber(ARGV[1])
            local max_requests = tonumber(ARGV[2])
            local trim_time = tonumber(current_time[1]) - window
            redis.call('ZREMRANGEBYSCORE', key, 0, trim_time)
            local request_count = redis.call('ZCARD',key)
            if request_count >= max_requests then
               local elements = redis.call('zrange', key, 0, 0, 'WITHSCORES')
               local next_ts = elements[2] + window
               local available_in = next_ts - tonumber(current_time[1])
               return {available_in, 0, max_requests}
            end
            redis.call('ZADD', key, current_time[1], current_time[1] .. current_time[2])
            redis.call('EXPIRE', key, window)
            return {0, max_requests - request_count - 1, max_requests}
LUA;
    }

    public static function attempts(): string
    {
        return <<<'LUA'
            local current_time = redis.call('TIME')
            local key = KEYS[1]
            local window = ARGV[1]
            local trim_time = tonumber(current_time[1]) - window
            redis.call('ZREMRANGEBYSCORE', key, 0, trim_time)
            local request_count = redis.call('ZCARD',key)
            return request_count
LUA;
    }

    public static function availableIn(): string
    {
        return <<<'LUA'
            local current_time = redis.call('TIME')
            local key = KEYS[1]
            local window = tonumber(ARGV[1])
            local max_requests = tonumber(ARGV[2])
            local trim_time = tonumber(current_time[1]) - window
            redis.call('ZREMRANGEBYSCORE', key, 0, trim_time)
            local request_count = redis.call('ZCARD',key)
            if request_count >= max_requests then
               local elements = redis.call('zrange', key, 0, 0, 'WITHSCORES')
               local next_ts = elements[2] + window
               local available_in = next_ts - tonumber(current_time[1])
               return available_in
            end
            return 0
LUA;
    }
}
