<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.index', [
            'promotions' => Promotion::with(['qualifyingProduct', 'rewardProduct'])->latest()->paginate(30),
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'qualifying_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'qualifying_quantity' => ['nullable', 'numeric', 'gt:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'gt:0'],
            'reward_type' => ['required', 'in:product,cash'],
            'reward_product_id' => ['nullable', 'required_if:reward_type,product', 'integer', 'exists:products,id'],
            'reward_quantity' => ['nullable', 'required_if:reward_type,product', 'numeric', 'gt:0'],
            'cash_reward_amount' => ['nullable', 'required_if:reward_type,cash', 'numeric', 'gt:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        if (empty($data['qualifying_product_id']) && empty($data['minimum_order_amount'])) {
            return back()->withErrors(['qualifying_product_id' => 'Choose a qualifying product or enter a minimum order amount.'])->withInput();
        }

        Promotion::create($data + ['is_active' => true]);

        return back()->with('status', 'Customer bonus promotion created.');
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('status', 'Promotion status updated.');
    }
}
