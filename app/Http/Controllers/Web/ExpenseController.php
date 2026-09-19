<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));
        $category = trim((string) $request->string('category'));

        $expenses = Expense::with(['salesman.user', 'reviewer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderByDesc('spent_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.expenses.index', compact('expenses', 'status', 'category'));
    }

    public function show(Expense $expense): View
    {
        return view('admin.expenses.show', [
            'expense' => $expense->load(['salesman.user', 'device', 'reviewer']),
        ]);
    }

    public function updateStatus(
        Request $request,
        Expense $expense,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])],
            'review_note' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($expense->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Reviewed expenses are terminal and cannot be changed.',
            ]);
        }

        if (
            in_array($validated['status'], ['rejected', 'cancelled'], true)
            && trim((string) ($validated['review_note'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'review_note' => 'A reason is required when rejecting or cancelling an expense.',
            ]);
        }

        $before = [
            'status' => $expense->status,
            'review_note' => $expense->review_note,
        ];

        $expense->update([
            'status' => $validated['status'],
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $audit->record('expense.status_changed', $expense, $before, [
            'status' => $expense->status,
            'review_note' => $expense->review_note,
        ]);

        $expense->loadMissing('salesman.user');
        if ($expense->salesman?->user) {
            $notifications->notify(
                $expense->salesman->user,
                'expense.status_changed',
                'expense_updates',
                'Expense '.$expense->expense_number.' '.str($expense->status)->title(),
                'Your expense '.$expense->expense_number.' is now '.$expense->status.'.',
                [
                    'expense_id' => $expense->uuid,
                    'status' => $expense->status,
                ],
                $expense->status === 'rejected' ? 'high' : 'normal',
            );
        }

        return redirect()
            ->route('admin.expenses.show', $expense)
            ->with('status', 'Expense review updated.');
    }
}
