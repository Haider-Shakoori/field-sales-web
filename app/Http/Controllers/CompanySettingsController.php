<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    public function edit(): View
    {
        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        $this->authorize('update', $tenant);

        $currencies = Currency::where('is_active', true)->orderBy('code')->get();
        $locales = config('tenancy.locales');
        $timezones = collect(\DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $zone) => [$zone => str_replace('_', ' ', $zone)])
            ->all();

        return view('pages.settings.company', compact('tenant', 'currencies', 'locales', 'timezones'));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::tenant();

        abort_if($tenant === null, 403);

        $this->authorize('update', $tenant);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:50', 'timezone'],
            'locale' => ['required', 'in:'.implode(',', array_keys(config('tenancy.locales')))],
            'default_currency' => ['required', 'exists:currencies,code'],
        ]);

        $changes = collect($data)->filter(
            fn ($value, $key) => $tenant->getAttribute($key) != $value,
        )->all();

        $tenant->update($data);

        if ($changes !== []) {
            AuditLogger::changed('tenant.updated', $tenant, $data);
        }

        return back()->with('status', 'Company settings updated.');
    }
}
