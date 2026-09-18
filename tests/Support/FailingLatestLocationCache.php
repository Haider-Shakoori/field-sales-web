<?php

namespace Tests\Support;

use App\Support\Tracking\LatestLocationCache;

/**
 * Cache double that always fails, proving Redis outages cannot lose GPS data.
 */
class FailingLatestLocationCache extends LatestLocationCache
{
    protected function connection()
    {
        throw new \RuntimeException('Redis unavailable during test.');
    }
}
