<?php

namespace Tests\Support;

/**
 * Minimal Redis-hash compatible connection used by FakeLatestLocationCache.
 */
class FakeRedisConnection
{
    public function __construct(private readonly FakeLatestLocationCache $cache) {}

    public function hset(string $key, string $field, string $value): int
    {
        $this->cache->store[$key][$field] = $value;
        $this->cache->recordWrite($key, $field, json_decode($value, true) ?? []);

        return 1;
    }

    public function hget(string $key, string $field): ?string
    {
        return $this->cache->store[$key][$field] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function hgetall(string $key): array
    {
        return $this->cache->store[$key] ?? [];
    }

    public function hdel(string $key, string $field): int
    {
        unset($this->cache->store[$key][$field]);

        return 1;
    }
}
