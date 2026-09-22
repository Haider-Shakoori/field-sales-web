<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('scope_type', 20)->default('all');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('required_on_checkout')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'vft_tenant_code_uq');
            $table->index(
                ['tenant_id', 'scope_type', 'is_active'],
                'vft_scope_active_idx',
            );
        });

        Schema::create('visit_form_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')
                ->constrained('visit_form_templates')
                ->cascadeOnDelete();
            $table->string('label', 255);
            $table->text('help_text')->nullable();
            $table->string('type', 30);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->unsignedSmallInteger('template_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(
                ['tenant_id', 'template_id', 'template_version', 'sort_order'],
                'vfq_template_ver_idx',
            );
        });

        Schema::create('visit_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')
                ->constrained('customer_visits')
                ->cascadeOnDelete();
            $table->foreignId('template_id')
                ->constrained('visit_form_templates')
                ->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salesman_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('template_version');
            $table->string('template_name', 150);
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique(
                ['visit_id', 'template_id'],
                'vfs_visit_template_uq',
            );
            $table->index(
                ['tenant_id', 'salesman_id', 'submitted_at'],
                'vfs_salesman_date_idx',
            );
        });

        Schema::create('visit_form_answers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')
                ->constrained('visit_form_submissions')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->nullable()
                ->constrained('visit_form_questions')
                ->nullOnDelete();
            $table->uuid('question_uuid');
            $table->string('question_label', 255);
            $table->string('question_type', 30);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['submission_id', 'question_uuid'],
                'vfa_submission_question_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_form_answers');
        Schema::dropIfExists('visit_form_submissions');
        Schema::dropIfExists('visit_form_questions');
        Schema::dropIfExists('visit_form_templates');
    }
};
