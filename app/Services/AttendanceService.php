<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Attendance / daily work-session business logic.
 *
 * Start Day is idempotent per client `offline_uuid` (mapped to the canonical
 * work session UUID) and one session per user per tenant-local work date is
 * enforced. The work date is derived from the accepted start event time (the
 * client-supplied `started_at` for offline sync, or server-now when absent).
 *
 * Duration is always calculated server-side from UTC event timestamps; client
 * duration values are never trusted.
 *
 * Schedule-aware flags (`is_late_start`, `is_early_finish`) are intentionally
 * left false: no working-hour configuration exists yet (Batch 7 scope).
 */
class AttendanceService
{
    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, offline_uuid: string, started_at?: string|null}  $data
     * @return array{0: WorkSession, 1: bool} [session, created]
     */
    public function start(User $user, Device $device, array $data): array
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to start a work session.');

        $salesman = $user->salesmanProfile;
        abort_if($salesman === null, 403, 'Only salesmen with a field profile can start a work day.');

        $offlineUuid = (string) $data['offline_uuid'];

        $existing = WorkSession::query()->where('uuid', $offlineUuid)->first();

        if ($existing !== null) {
            if ($existing->user_id !== $user->id) {
                throw new ConflictHttpException('This offline session identifier is already in use.');
            }

            // UUID identity wins: retries never mutate the original event time.
            return [$existing, false];
        }

        $startTime = $this->eventTime($data['started_at'] ?? null);

        $date = TenantClock::dateFor($user, $startTime);

        $sameDay = WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        if ($sameDay !== null) {
            throw new ConflictHttpException('A work session already exists for today.');
        }

        $session = WorkSession::create([
            'uuid' => $offlineUuid,
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'salesman_id' => $salesman->id,
            'device_id' => $device->id,
            'date' => $date,
            'start_time' => $startTime,
            'start_latitude' => $data['latitude'],
            'start_longitude' => $data['longitude'],
            'status' => WorkSession::STATUS_ACTIVE,
            'is_late_start' => false,
            'is_early_finish' => false,
        ]);

        return [$session, true];
    }

    /**
     * End the current active work session using the accepted end event time
     * (`ended_at` for offline sync, or server-now when absent). Retrying after
     * completion returns the already-completed session unchanged.
     *
     * @param  array{latitude: float|int|string, longitude: float|int|string, ended_at?: string|null}  $data
     * @return array{0: WorkSession, 1: bool} [session, ended]
     */
    public function end(User $user, array $data): array
    {
        $session = WorkSession::query()
            ->where('user_id', $user->id)
            ->active()
            ->latest('start_time')
            ->first();

        if ($session === null) {
            // Idempotent retry: a recently completed session (including one that
            // crossed midnight) is returned unchanged instead of forcing another
            // End Day call. The 24h window avoids resurfacing stale sessions.
            $recent = WorkSession::query()
                ->where('user_id', $user->id)
                ->whereNotNull('end_time')
                ->where('end_time', '>=', CarbonImmutable::now('UTC')->subDay())
                ->latest('start_time')
                ->first();

            if ($recent !== null) {
                return [$recent, false];
            }

            throw new ConflictHttpException('No active work session found.');
        }

        $endTime = $this->eventTime($data['ended_at'] ?? null);

        if ($endTime->lessThan($session->start_time)) {
            throw ValidationException::withMessages([
                'ended_at' => 'The ended at time must be after the work session start time.',
            ]);
        }

        $session->update([
            'end_time' => $endTime,
            'end_latitude' => $data['latitude'],
            'end_longitude' => $data['longitude'],
            'status' => WorkSession::STATUS_COMPLETED,
            'duration_minutes' => max(0, (int) $session->start_time->diffInMinutes($endTime)),
            'is_early_finish' => false,
        ]);

        return [$session, true];
    }

    public function today(User $user): ?WorkSession
    {
        return WorkSession::query()
            ->where('user_id', $user->id)
            ->whereDate('date', TenantClock::dateFor($user))
            ->first();
    }

    /**
     * Resolve the accepted event time: the client timestamp for offline sync,
     * or server-now when the client did not supply one.
     */
    private function eventTime(?string $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now('UTC');
        }

        return CarbonImmutable::parse($value)->utc();
    }
}
