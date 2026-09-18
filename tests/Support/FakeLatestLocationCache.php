<?php

namespace Tests\Support;

use App\Support\Tracking\LatestLocationCache;

/**
 * In-memory Redis stand-in so Batch 7 tests never need a live Redis server.
 */
class FakeLatestLocationCache extends LatestLocationCache
{
    /**
     * @var array<string, array<string, string>>
     */
    public array $store = [];

    /**
     * @var list<string>
     */
    public array $keys = [];

    /**
     * @var list<array{key: string, field: string, payload: array<string, mixed>}>
     */
    public array $writes = [];

    protected function connection()
    {
        return new FakeRedisConnection($this);
    }

    /**
     * @param  array{key: string, field: string, payload: array<string, mixed>}  $write
     */
    public function recordWrite(string $key, string $field, array $payload): void
    {
        $this->keys[] = $key;
        $this->writes[] = ['key' => $key, 'field' => $field, 'payload' => $payload];
    }

    public function payloadFor(string $key, string $field): ?array
    {
        $raw = $this->store[$key][$field] ?? null;

        return $raw === null ? null : json_decode($raw, true);
    }
}
