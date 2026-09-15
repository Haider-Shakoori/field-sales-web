<?php

namespace App\Support\FieldProfiles;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EmployeeCodeGenerator
{
    private const PREFIX_SALESMAN = 'SLM-';

    private const PREFIX_SUPERVISOR = 'SUP-';

    private const PADDING = 6;

    public static function nextSalesmanCode(int $tenantId, ?string $manual = null): string
    {
        return self::generate(self::PREFIX_SALESMAN, 'salesmen', $tenantId, $manual);
    }

    public static function nextSupervisorCode(int $tenantId, ?string $manual = null): string
    {
        return self::generate(self::PREFIX_SUPERVISOR, 'supervisors', $tenantId, $manual);
    }

    private static function generate(string $prefix, string $table, int $tenantId, ?string $manual): string
    {
        $manual = $manual !== null ? trim($manual) : null;

        if (is_string($manual) && $manual !== '') {
            return $manual;
        }

        $lockKey = 'employee-code:'.$table.':'.$tenantId;
        $lock = Cache::lock($lockKey, 10);

        try {
            $lock->block(10);

            $max = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where('employee_code', 'like', $prefix.'%')
                ->max('employee_code');

            $seq = 1;
            if ($max !== null) {
                $numPart = substr($max, strlen($prefix));
                if (is_numeric($numPart)) {
                    $seq = max(1, (int) $numPart + 1);
                }
            }

            for ($attempt = 0; $attempt < 10000; $attempt++) {
                $code = $prefix.str_pad((string) ($seq + $attempt), self::PADDING, '0', STR_PAD_LEFT);

                $exists = DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('employee_code', $code)
                    ->exists();

                if (! $exists) {
                    return $code;
                }
            }

            throw new \RuntimeException('Unable to generate unique employee code after 10000 attempts.');
        } finally {
            $lock->release();
        }
    }
}
