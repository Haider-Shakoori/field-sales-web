<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationalNotification;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $page = OperationalNotification::query()
            ->where('user_id', $request->user()->id)
            ->when(
                (bool) ($validated['unread_only'] ?? false),
                fn ($query) => $query->whereNull('read_at'),
            )
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (OperationalNotification $notification) => $this->payload($notification))
                ->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function read(
        Request $request,
        OperationalNotification $notification,
    ): JsonResponse {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        $notification->markRead();

        return ApiResponse::success($this->payload($notification->fresh()));
    }

    public function preferences(
        Request $request,
        NotificationService $notifications,
    ): JsonResponse {
        return ApiResponse::success(
            $notifications->preferences($request->user())->only([
                'database_enabled',
                'push_enabled',
                'order_updates',
                'collection_updates',
                'expense_updates',
                'suspicious_alerts',
            ]),
        );
    }

    public function updatePreferences(
        Request $request,
        NotificationService $notifications,
    ): JsonResponse {
        $validated = $request->validate([
            'database_enabled' => ['required', 'boolean'],
            'push_enabled' => ['required', 'boolean'],
            'order_updates' => ['required', 'boolean'],
            'collection_updates' => ['required', 'boolean'],
            'expense_updates' => ['required', 'boolean'],
            'suspicious_alerts' => ['required', 'boolean'],
        ]);

        $preferences = $notifications->preferences($request->user());
        $preferences->update($validated);

        return ApiResponse::success($preferences->fresh()->only(array_keys($validated)));
    }

    private function payload(OperationalNotification $notification): array
    {
        return [
            'id' => $notification->uuid,
            'type' => $notification->type,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data ?? [],
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
