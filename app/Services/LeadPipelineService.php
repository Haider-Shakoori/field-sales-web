<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadPipelineService
{
    public function activity(
        Lead $lead,
        User $user,
        string $type,
        ?string $notes = null,
        array $metadata = [],
        ?string $uuid = null,
    ): LeadActivity {
        $activity = $lead->activities()->create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'type' => $type,
            'occurred_at' => now(),
            'notes' => $notes,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        $lead->forceFill(['last_activity_at' => $activity->occurred_at])->save();

        return $activity;
    }

    public function applyStage(Lead $lead, User $user, string $stage, ?string $lostReason = null): Lead
    {
        if ($lead->stage === $stage && ($stage !== 'lost' || $lead->lost_reason === $lostReason)) {
            return $lead;
        }

        $before = $lead->stage;
        $lead->update([
            'stage' => $stage,
            'probability' => Lead::STAGE_PROBABILITIES[$stage] ?? $lead->probability,
            'lost_reason' => $stage === 'lost' ? $lostReason : null,
        ]);

        $this->activity(
            $lead,
            $user,
            'status_change',
            'Stage changed from '.(string) str($before)->replace('_', ' ')->title().' to '.(string) str($stage)->replace('_', ' ')->title().'.',
            ['from' => $before, 'to' => $stage],
        );

        return $lead->refresh();
    }

    public function convert(Lead $lead, User $user): Customer
    {
        return DB::transaction(function () use ($lead, $user): Customer {
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($locked->converted_customer_id) {
                return $locked->convertedCustomer()->firstOrFail();
            }

            $customer = $this->matchingCustomer($locked);
            $reused = $customer !== null;

            if (! $customer) {
                $customer = Customer::create([
                    'branch_id' => $locked->branch_id,
                    'territory_id' => $locked->territory_id,
                    'code' => $this->customerCode($locked),
                    'name' => $locked->name,
                    'contact_person' => $locked->contact_person,
                    'phone' => $locked->phone,
                    'email' => $locked->email,
                    'address' => $locked->address,
                    'geofence_radius_meters' => 100,
                    'credit_currency' => $locked->currency ?: 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $user->id,
                    'is_active' => true,
                ]);
            }

            $locked->update([
                'stage' => 'won',
                'probability' => 100,
                'converted_customer_id' => $customer->id,
                'converted_at' => now(),
                'converted_by' => $user->id,
                'lost_reason' => null,
            ]);

            $this->activity(
                $locked,
                $user,
                'conversion',
                $reused ? 'Lead linked to an existing customer.' : 'Lead converted to a customer.',
                ['customer_id' => $customer->id, 'reused_existing_customer' => $reused],
            );

            return $customer;
        });
    }

    private function matchingCustomer(Lead $lead): ?Customer
    {
        $phone = trim((string) $lead->phone);
        $email = trim((string) $lead->email);

        if ($phone === '' && $email === '') {
            return null;
        }

        return Customer::query()
            ->where(function ($query) use ($phone, $email): void {
                if ($phone !== '') {
                    $query->where('phone', $phone);
                }
                if ($email !== '') {
                    $phone !== '' ? $query->orWhere('email', $email) : $query->where('email', $email);
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function customerCode(Lead $lead): string
    {
        $base = 'LEAD-'.str_replace('-', '', strtoupper($lead->uuid));

        foreach ([10, 12, 16, 24, 32] as $length) {
            $code = substr($base, 0, min(strlen($base), 5 + $length));
            if (! Customer::where('code', $code)->exists()) {
                return $code;
            }
        }

        return 'LEAD-'.Str::upper(Str::random(20));
    }
}
