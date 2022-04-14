<?php

namespace Bvtterfly\SlidingWindowRateLimiter\DataTransferObjects;

use Illuminate\Support\InteractsWithTime;

class AttemptResult
{
    use InteractsWithTime {
        availableAt as getAvailableAt;
    }

    public function __construct(
        public int $retryAfter,
        public int $retriesLeft,
        public int $limit
    ) {
    }

    public function successful(): bool
    {
        return $this->retriesLeft >= 0 && $this->retryAfter === 0;
    }

    public function availableAt(): int
    {
        return $this->getAvailableAt($this->retryAfter);
    }
}
