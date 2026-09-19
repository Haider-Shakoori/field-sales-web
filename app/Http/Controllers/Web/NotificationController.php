<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OperationalNotification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        NotificationService $notifications,
    ): View {
        $user = $request->user();

        $items = OperationalNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(30);

        return view('admin.notifications.index', [
            'notifications' => $items,
            'preferences' => $notifications->preferences($user),
        ]);
    }

    public function read(
        Request $request,
        OperationalNotification $notification,
    ): RedirectResponse {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        $notification->markRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        OperationalNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marked as read.');
    }

    public function updatePreferences(
        Request $request,
        NotificationService $notifications,
    ): RedirectResponse {
        $validated = $request->validate([
            'database_enabled' => ['required', 'boolean'],
            'push_enabled' => ['required', 'boolean'],
            'order_updates' => ['required', 'boolean'],
            'collection_updates' => ['required', 'boolean'],
            'expense_updates' => ['required', 'boolean'],
            'suspicious_alerts' => ['required', 'boolean'],
        ]);

        $notifications->preferences($request->user())->update($validated);

        return back()->with('status', 'Notification preferences updated.');
    }
}
