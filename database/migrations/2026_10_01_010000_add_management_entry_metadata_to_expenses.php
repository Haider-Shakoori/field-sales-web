<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('device_id')->nullable()->change();
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->decimal('accuracy', 8, 2)->nullable()->change();
            $table->foreignId('entered_by')
                ->nullable()
                ->after('device_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('entry_source', 30)
                ->default('mobile')
                ->after('entered_by');
            $table->text('correction_reason')
                ->nullable()
                ->after('review_note');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entered_by');
            $table->dropColumn(['entry_source', 'correction_reason']);
        });
    }
};
