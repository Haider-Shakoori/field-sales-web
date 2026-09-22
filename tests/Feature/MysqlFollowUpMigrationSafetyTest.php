<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MysqlFollowUpMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_indexes_use_mysql_safe_explicit_names(): void
    {
        $indexes = collect(Schema::getIndexes('customer_follow_ups'))
            ->keyBy('name');

        $this->assertTrue($indexes->has('cfu_tenant_status_due_idx'));
        $this->assertTrue($indexes->has('cfu_salesman_status_due_idx'));
        $this->assertTrue($indexes->has('cfu_customer_status_idx'));

        foreach ($indexes->keys() as $indexName) {
            $this->assertLessThanOrEqual(64, strlen((string) $indexName));
        }

        $orderIndexes = collect(Schema::getIndexes('orders'))
            ->keyBy('name');

        $this->assertTrue($orderIndexes->has('orders_tenant_due_status_idx'));
    }

    public function test_credit_follow_up_migration_can_be_reentered_safely(): void
    {
        $migration = require database_path(
            'migrations/2026_09_23_000000_add_credit_and_followup_domain.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumn('customers', 'credit_limit'));
        $this->assertTrue(Schema::hasColumn('customers', 'credit_currency'));
        $this->assertTrue(Schema::hasColumn('customers', 'credit_terms_days'));
        $this->assertTrue(Schema::hasColumn('orders', 'due_date'));
        $this->assertTrue(Schema::hasIndex(
            'customer_follow_ups',
            ['tenant_id', 'assigned_salesman_id', 'status', 'due_at'],
        ));
    }
}
