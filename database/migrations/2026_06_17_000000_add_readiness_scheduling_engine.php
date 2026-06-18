<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table): void {
            $table->unsignedSmallInteger('scheduling_capacity_daily')->default(1)->after('subcontractor_type_id');
            $table->unsignedSmallInteger('reliability_score')->default(80)->after('scheduling_capacity_daily');
            $table->boolean('scheduling_is_active')->default(true)->after('reliability_score');
        });

        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->string('phase')->default('Construction')->after('name');
            $table->boolean('uses_calendar_days')->default(false)->after('default_duration_working_days');
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->string('phase')->default('Construction')->after('title');
            $table->string('readiness_status')->default('not_ready')->after('status');
            $table->timestamp('ready_since')->nullable()->after('readiness_status');
            $table->unsignedTinyInteger('priority')->default(2)->after('default_duration_working_days');
            $table->unsignedTinyInteger('customer_urgency')->default(1)->after('priority');
            $table->integer('schedule_score')->default(0)->after('customer_urgency');
            $table->json('score_breakdown')->nullable()->after('schedule_score');
            $table->boolean('is_schedule_locked')->default(false)->after('score_breakdown');
            $table->string('schedule_locked_reason')->nullable()->after('is_schedule_locked');
            $table->boolean('uses_calendar_days')->default(false)->after('schedule_locked_reason');
            $table->timestamp('last_scheduled_at')->nullable()->after('uses_calendar_days');

            $table->index(['company_id', 'status', 'readiness_status'], 'timeline_tasks_readiness_index');
            $table->index(['company_id', 'is_schedule_locked'], 'timeline_tasks_schedule_lock_index');
        });

        Schema::create('timeline_task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('predecessor_task_id')->constrained('timeline_tasks')->cascadeOnDelete();
            $table->foreignId('successor_task_id')->constrained('timeline_tasks')->cascadeOnDelete();
            $table->string('dependency_type')->default('finish_to_start');
            $table->unsignedSmallInteger('lag_days')->default(0);
            $table->string('lag_unit')->default('working_days');
            $table->timestamps();

            $table->unique(['predecessor_task_id', 'successor_task_id', 'dependency_type'], 'timeline_task_dependencies_unique');
            $table->index(['project_id', 'successor_task_id'], 'timeline_task_dependencies_successor_index');
        });

        Schema::create('timeline_task_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeline_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['timeline_task_id', 'status'], 'timeline_task_blocks_active_index');
            $table->index(['company_id', 'type', 'status'], 'timeline_task_blocks_type_index');
        });

        Schema::create('schedule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'started_at']);
        });

        Schema::create('schedule_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeline_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_subcontractor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('readiness_status');
            $table->date('scheduled_start')->nullable();
            $table->date('scheduled_end')->nullable();
            $table->integer('score')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->json('block_reasons')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['schedule_run_id', 'timeline_task_id'], 'schedule_run_items_task_index');
            $table->index(['company_id', 'status', 'readiness_status'], 'schedule_run_items_status_index');
        });

        DB::table('timeline_tasks')
            ->where('status', 'upcoming')
            ->update(['status' => 'scheduled']);
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_run_items');
        Schema::dropIfExists('schedule_runs');
        Schema::dropIfExists('timeline_task_blocks');
        Schema::dropIfExists('timeline_task_dependencies');

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->dropIndex('timeline_tasks_readiness_index');
            $table->dropIndex('timeline_tasks_schedule_lock_index');
            $table->dropColumn([
                'phase',
                'readiness_status',
                'ready_since',
                'priority',
                'customer_urgency',
                'schedule_score',
                'score_breakdown',
                'is_schedule_locked',
                'schedule_locked_reason',
                'uses_calendar_days',
                'last_scheduled_at',
            ]);
        });

        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->dropColumn(['phase', 'uses_calendar_days']);
        });

        Schema::table('company_user', function (Blueprint $table): void {
            $table->dropColumn([
                'scheduling_capacity_daily',
                'reliability_score',
                'scheduling_is_active',
            ]);
        });
    }
};
