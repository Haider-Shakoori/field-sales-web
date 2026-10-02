<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GamificationBonus;
use App\Services\AuditLogger;
use App\Services\TerritoryAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GamificationBonusController extends Controller
{
    public function index(Request $request, TerritoryAccessService $access): View
    {
        $status = trim((string) $request->string('status'));
        $ids = $access->salesmanIds($request->user());
        $bonuses = GamificationBonus::with(['salesman', 'target'])->when($ids, fn ($q, $v) => $q->whereIn('salesman_id', $v))
            ->when($status !== '', fn ($q) => $q->where('status', $status))->latest('earned_at')->paginate(30)->withQueryString();

        return view('admin.gamification.bonuses', compact('bonuses', 'status'));
    }

    public function update(Request $request, GamificationBonus $bonus, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,paid']]);
        abort_if($bonus->status === 'paid', 409, 'Paid bonuses are immutable.');
        abort_if($data['status'] === 'paid' && $bonus->status !== 'approved', 409, 'Approve the bonus before marking it paid.');
        $before = $bonus->only(['status', 'approved_at', 'approved_by', 'paid_at', 'paid_by']);
        if ($data['status'] === 'approved') {
            $bonus->fill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        } else {
            $bonus->fill(['status' => 'paid', 'paid_at' => now(), 'paid_by' => $request->user()->id]);
        }
        $bonus->save();
        $audit->record('gamification_bonus.'.$data['status'], $bonus, $before, $bonus->only(['status', 'approved_at', 'approved_by', 'paid_at', 'paid_by']));

        return back()->with('status', 'Gamification bonus '.($data['status'] === 'approved' ? 'approved.' : 'marked as paid.'));
    }
}
