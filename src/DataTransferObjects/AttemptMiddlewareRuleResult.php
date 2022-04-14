<?php

namespace Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects;

use Closure;

class AttemptMiddlewareRuleResult extends AttemptResult
{
    public function __construct(
        public int $retryAfter,
        public int $retriesLeft,
        public int $limit,
        public ?Closure $responseCallback = null
    ) {
        parent::__construct($retryAfter, $retriesLeft, $limit);
    }
}
