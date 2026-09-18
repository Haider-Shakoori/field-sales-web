<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceListItemRequest;
use App\Http\Requests\UpdatePriceListItemRequest;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PriceListItemController extends Controller
{
    public function store(
        StorePriceListItemRequest $request,
        PriceList $priceList,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $priceList);

        $item = PriceListItem::create([
            ...$request->validated(),
            'price_list_id' => $priceList->id,
        ]);

        $audit->record('price_list_item.created', $item, [], $this->auditValues($item));

        return back()->with('status', 'Price tier added.');
    }

    public function update(
        UpdatePriceListItemRequest $request,
        PriceList $priceList,
        PriceListItem $priceListItem,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $priceList);

        abort_unless((int) $priceListItem->price_list_id === (int) $priceList->id, 404);

        $before = $this->auditValues($priceListItem);
        $priceListItem->update($request->validated());

        $audit->record(
            'price_list_item.updated',
            $priceListItem,
            $before,
            $this->auditValues($priceListItem)
        );

        return back()->with('status', 'Price tier updated.');
    }

    public function destroy(
        PriceList $priceList,
        PriceListItem $priceListItem,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $priceList);

        abort_unless((int) $priceListItem->price_list_id === (int) $priceList->id, 404);

        $before = $this->auditValues($priceListItem);
        $audit->record('price_list_item.deleted', $priceListItem, $before);
        $priceListItem->delete();

        return back()->with('status', 'Price tier removed.');
    }

    private function auditValues(PriceListItem $item): array
    {
        return [
            'price_list_id' => $item->price_list_id,
            'product_id' => $item->product_id,
            'min_quantity' => $item->min_quantity,
            'price' => $item->price,
        ];
    }
}
