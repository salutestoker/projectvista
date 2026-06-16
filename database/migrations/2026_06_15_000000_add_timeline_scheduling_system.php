<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('contract_signed_on')->nullable()->after('contract_amount');
            $table->index(['company_id', 'contract_signed_on', 'created_at'], 'projects_schedule_priority_index');
        });

        Schema::create('timeline_task_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeline_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_subcontractor_type_id')->nullable()->constrained('subcontractor_types')->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('customer_facing_name')->nullable();
            $table->string('phase')->default('Construction');
            $table->text('description')->nullable();
            $table->unsignedInteger('sequence_order');
            $table->unsignedSmallInteger('default_duration_working_days')->default(1);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_customer_visible')->default(true);
            $table->boolean('is_subcontractor_visible')->default(false);
            $table->timestamps();

            $table->unique(['timeline_template_id', 'sequence_order'], 'timeline_task_templates_sequence_unique');
            $table->index(['company_id', 'sequence_order']);
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->foreignId('timeline_task_template_id')
                ->nullable()
                ->after('timeline_template_id')
                ->constrained('timeline_task_templates')
                ->nullOnDelete();
            $table->foreignId('created_by_id')
                ->nullable()
                ->after('subcontractor_type_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by_id')
                ->nullable()
                ->after('created_by_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('customer_facing_name')->nullable()->after('title');
            $table->unsignedInteger('sequence_order')->nullable()->after('sort_order');
            $table->unsignedSmallInteger('default_duration_working_days')->default(1)->after('sequence_order');
            $table->date('actual_start_date')->nullable()->after('completed_on');
            $table->date('actual_end_date')->nullable()->after('actual_start_date');
            $table->boolean('is_job_site_ready')->default(true)->after('requires_acknowledgement');
            $table->boolean('are_materials_ready')->default(true)->after('is_job_site_ready');
            $table->boolean('is_customer_approval_required')->default(false)->after('are_materials_ready');
            $table->boolean('is_customer_approval_received')->default(false)->after('is_customer_approval_required');
            $table->text('internal_notes')->nullable()->after('is_customer_approval_received');
            $table->text('customer_notes')->nullable()->after('internal_notes');

            $table->index(['project_id', 'sequence_order']);
        });

        Schema::create('schedule_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeline_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->date('old_start_date')->nullable();
            $table->date('new_start_date')->nullable();
            $table->date('old_end_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->foreignId('old_subcontractor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_subcontractor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->unsignedInteger('conflicts_detected_count')->default(0);
            $table->boolean('saved_with_override')->default(false);
            $table->boolean('blocked_by_conflicts')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'timeline_task_id'], 'schedule_logs_task_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_logs');

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'sequence_order']);
            $table->dropConstrainedForeignId('timeline_task_template_id');
            $table->dropConstrainedForeignId('created_by_id');
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropColumn([
                'customer_facing_name',
                'sequence_order',
                'default_duration_working_days',
                'actual_start_date',
                'actual_end_date',
                'is_job_site_ready',
                'are_materials_ready',
                'is_customer_approval_required',
                'is_customer_approval_received',
                'internal_notes',
                'customer_notes',
            ]);
        });

        Schema::dropIfExists('timeline_task_templates');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_schedule_priority_index');
            $table->dropColumn('contract_signed_on');
        });
    }
};
