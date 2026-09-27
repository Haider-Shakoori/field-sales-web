<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Salesman;
use App\Models\VisitAssignment;
use App\Models\VisitVoiceNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));
        $flagged = $request->boolean('flagged');

        $visits = CustomerVisit::with(['customer', 'salesman.user', 'route'])
            ->withCount('suspiciousFlags')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($flagged, fn ($query) => $query->whereHas('suspiciousFlags', fn ($flags) => $flags->whereNull('reviewed_at')))
            ->orderByDesc('checked_in_at')
            ->paginate(min(100, max(10, request()->integer('per_page', 30))))->withQueryString();

        $salesmen = Salesman::query()
            ->active()
            ->with('user')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $customers = Customer::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'code', 'address', 'latitude', 'longitude', 'assigned_salesman_id']);

        $upcomingAssignments = VisitAssignment::query()
            ->with(['customer', 'salesman.user'])
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereDate('visit_date', '>=', now()->toDateString())
            ->orderBy('visit_date')
            ->orderBy('scheduled_time')
            ->limit(20)
            ->get();

        return view('admin.visits.index', compact(
            'visits',
            'status',
            'flagged',
            'salesmen',
            'customers',
            'upcomingAssignments',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'salesman_id' => ['required', 'integer', Rule::exists('salesmen', 'id')],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'visit_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'purpose' => ['required', Rule::in(['sales', 'collection', 'follow_up', 'merchandising', 'survey', 'other'])],
            'priority' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'expected_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $salesman = Salesman::query()->findOrFail($validated['salesman_id']);
        $customer = Customer::query()->findOrFail($validated['customer_id']);

        abort_unless((int) $salesman->tenant_id === (int) $customer->tenant_id, 422);

        VisitAssignment::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        return back()->with('success', 'Visit scheduled and will appear in the salesman mobile app.');
    }

    public function show(CustomerVisit $visit): View
    {
        return view('admin.visits.show', [
            'visit' => $visit->load([
                'customer',
                'salesman.user',
                'device',
                'route',
                'workSession',
                'photos',
                'voiceNotes',
                'suspiciousFlags.reviewer',
                'formSubmissions.answers',
            ]),
        ]);
    }

    public function destroyAssignment(VisitAssignment $assignment): RedirectResponse
    {
        abort_if($assignment->status === 'completed', 422, 'Completed visit assignments cannot be cancelled.');

        $assignment->update(['status' => 'cancelled']);

        return back()->with('success', 'Visit assignment cancelled.');
    }

    public function voiceNoteAudio(
        CustomerVisit $visit,
        VisitVoiceNote $voiceNote,
    ): StreamedResponse {
        abort_unless((int) $voiceNote->visit_id === (int) $visit->id, 404);

        return Storage::disk($voiceNote->disk)->response(
            $voiceNote->path,
            'visit-voice-note-'.$voiceNote->uuid.'.m4a',
            [
                'Content-Type' => $voiceNote->mime_type ?: 'audio/mp4',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}