<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\TenantClock;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Attendance / daily work-session business logic.
 *
 * Start Day is idempotent per client `offline_uuid` (mapped to the canonical
 * work session UUID) and one session per user per tenant-local work date is
 * enforced. Duration is always calculated server-side from UTC timestamps.
 *
 * Schedule-aware flags (`is_late_start`, `is_early_finish`) are intentionally
 * left false: no working-hour configuration exists yet (Batch 7 scope).
 */
class AttendanceService
{
    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, offline_uuid: string}  $data
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

            return [$existing, false];
        }

        $date = TenantClock::dateFor($user);

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
            'start_time' => CarbonImmutable::now('UTC'),
            'start_latitude' => $data['latitude'],
            'start_longitude' => $data['longitude'],
            'status' => WorkSession::STATUS_ACTIVE,
            'is_late_start' => false,
            'is_early_finish' => false,
        ]);

        return [$session, true];
    }

    /**
     * End the current active work session. Retrying after completion returns the
     * already-completed session instead of recalculating state.
     *
     * @param  array{latitude: float|int|string, longitude: float|int|string}  $data
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
            $today = WorkSession::query()
                ->where('user_id', $user->id)
                ->whereDate('date', TenantClock::dateFor($user))
                ->first();

            if ($today !== null && ! $today->isActive()) {
                return [$today, false];
            }

            throw new ConflictHttpException('No active work session found.');
        }

        $endTime = CarbonImmutable::now('UTC');

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
}
