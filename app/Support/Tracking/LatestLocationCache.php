<?php

namespace App\Support\Tracking;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Redis acceleration layer for latest locations.
 *
 * MySQL (`current_locations`) is canonical; this cache never holds data that is
 * not already durable in the database. Every operation degrades gracefully when
 * Redis is unavailable: writes are logged and skipped, reads return null/[] so
 * callers can fall back to MySQL.
 *
 * Key:   fs:{tenant_id}:locations:latest   (Redis HASH)
 * Field: user_id
 * Value: latest-location JSON payload
 */
class LatestLocationCache
{
    public function put(int $tenantId, int $userId, array $payload): bool
    {
        try {
            $this->connection()->hset(
                $this->key($tenantId),
                (string) $userId,
                json_encode($payload, JSON_THROW_ON_ERROR),
            );

            return true;
        } catch (Throwable $e) {
            $this->reportFailure('write', $tenantId, $userId, $e);

            return false;
        }
    }

    public function get(int $tenantId, int $userId): ?array
    {
        try {
            $raw = $this->connection()->hget($this->key($tenantId), (string) $userId);

            if ($raw === null || $raw === false || $raw === '') {
                return null;
            }

            return json_decode($raw, true) ?: null;
        } catch (Throwable $e) {
            $this->reportFailure('read', $tenantId, $userId, $e);

            return null;
        }
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function all(int $tenantId): array
    {
        try {
            $raw = $this->connection()->hgetall($this->key($tenantId));

            return collect($raw)
                ->map(fn ($value) => json_decode($value, true))
                ->filter(fn ($value) => is_array($value))
                ->all();
        } catch (Throwable $e) {
            $this->reportFailure('read-all', $tenantId, null, $e);

            return [];
        }
    }

    public function forget(int $tenantId, int $userId): void
    {
        try {
            $this->connection()->hdel($this->key($tenantId), (string) $userId);
        } catch (Throwable $e) {
            $this->reportFailure('forget', $tenantId, $userId, $e);
        }
    }

    public function key(int $tenantId): string
    {
        return "fs:{$tenantId}:locations:latest";
    }

    /**
     * @return mixed
     */
    protected function connection()
    {
        return Redis::connection();
    }

    protected function reportFailure(string $operation, int $tenantId, ?int $userId, Throwable $e): void
    {
        Log::warning('Latest location cache operation failed.', [
            'operation' => $operation,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'exception' => $e->getMessage(),
        ]);
    }
}
