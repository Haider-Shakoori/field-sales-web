<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private function filtered(Request $request): Builder
    {
        $query = AuditLog::query()->with('actor');

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($term): void {
                $q->where('event', 'like', '%'.$term.'%')
                    ->orWhere('subject_type', 'like', '%'.$term.'%')
                    ->orWhere('ip_address', 'like', '%'.$term.'%')
                    ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', '%'.$term.'%'));
            });
        }
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        return $query;
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit:view'), 403);
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $query = $this->filtered($request);

        return view('admin.audit.index', [
            'logs' => (clone $query)->latest()->paginate(min(100, max(10, $request->integer('per_page', 25))))->withQueryString(),
            'totalLogs' => (clone $query)->count(),
            'recentLogs' => (clone $query)->where('created_at', '>=', now()->subDay())->count(),
            'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('audit:view'), 403);
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $query = $this->filtered($request)->orderBy('id');
        $filename = 'fieldpulse-audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date (UTC)', 'Event', 'Actor', 'Subject', 'Subject ID', 'IP Address', 'Before', 'After']);
            foreach ($query->cursor() as $log) {
                $safe = static function ($value): string {
                    $text = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $text = (string) ($text ?? '');
                    return preg_match('/^[\\s]*[=+@-]/u', $text) ? "'".$text : $text;
                };
                fputcsv($output, [
                    $safe($log->created_at?->utc()->format('Y-m-d H:i:s')),
                    $safe($log->event),
                    $safe($log->actor?->name ?? 'System'),
                    $safe($log->subject_type),
                    $safe($log->subject_id),
                    $safe($log->ip_address),
                    $safe($log->old_values),
                    $safe($log->new_values),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
