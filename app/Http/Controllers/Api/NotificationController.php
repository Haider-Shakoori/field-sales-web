<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrentLocation;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\FieldScopeService;
use App\Services\NotificationService;
use App\Services\TrackingSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::success(
            NotificationLog::where('tenant_id', $request->user()->tenant_id)
                ->where('recipient_user_id', $request->user()->id)
                ->latest()
                ->limit(100)
                ->get()
        );
    }

    public function read(Request $request, NotificationLog $notification)
    {
        abort_unless((int) $notification->tenant_id === (int) $request->user()->tenant_id, 404);
        abort_unless((int) $notification->recipient_user_id === (int) $request->user()->id, 403);

        $notification->update(['read_at' => $notification->read_at ?: now()]);

        return ApiResponse::success($notification);
    }

    public function trackingReminder(Request $request, NotificationService $notifications, FieldScopeService $scope, TrackingSettingsService $settingsService)
    {
        if (! $request->user()->hasPermission('notifications:send')) {
            return ApiResponse::error('Forbidden.', 403, null, 'FORBIDDEN');
        }

        $validated = $request->validate([
            'user_uuids' => ['required', 'array', 'min:1', 'max:100'],
            'user_uuids.*' => ['required', 'uuid'],
        ]);

        $viewer = $request->user()->load('tenant');
        $allowedSalesmanIds = $scope->salesmanIds($viewer);
        $settings = $settingsService->get($viewer->tenant);
        $staleBefore = now()->subMinutes($settings['gps_stale_after_minutes']);

        $users = User::where('tenant_id', $viewer->tenant_id)
            ->whereIn('uuid', $validated['user_uuids'])
            ->with('salesman')
            ->get();

        $sent = [];

        foreach ($users as $target) {
            if (! $target->salesman || ! $allowedSalesmanIds->contains($target->salesman->id)) {
                continue;
            }

            $activeSession = WorkSession::where('tenant_id', $viewer->tenant_id)
                ->where('user_id', $target->id)
                ->where('status', 'active')
                ->exists();

            $fresh = CurrentLocation::where('tenant_id', $viewer->tenant_id)
                ->where('user_id', $target->id)
                ->where('recorded_at', '>=', $staleBefore)
                ->exists();

            if (! $activeSession) {
                $title = 'Start your work day';
                $body = 'Please open Field Sales and start your work day.';
                $reason = 'no_work_session';
            } elseif (! $fresh) {
                $title = 'Tracking is offline';
                $body = 'Please open Field Sales and check your location service so tracking can resume.';
                $reason = 'gps_stale';
            } else {
                continue;
            }

            $cooldown = NotificationLog::where('tenant_id', $viewer->tenant_id)
                ->where('recipient_user_id', $target->id)
                ->where('type', 'tracking_reminder')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->exists();

            if ($cooldown) {
                continue;
            }

            $sent[] = $notifications->send(
                $target,
                'tracking_reminder',
                $title,
                $body,
                ['reason' => $reason],
                $viewer
            );
        }

        return ApiResponse::success($sent);
    }
}
