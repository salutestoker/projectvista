<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->boolean('internal_only')->default(false)->after('default_duration_working_days');
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->boolean('internal_only')->default(false)->after('default_duration_working_days');
        });

        DB::table('timeline_task_templates')
            ->where('is_customer_visible', false)
            ->update(['internal_only' => true]);

        DB::table('timeline_tasks')
            ->where('client_visible', false)
            ->update(['internal_only' => true]);

        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'title',
                'customer_facing_name',
                'phase',
                'is_required',
                'is_customer_visible',
                'is_subcontractor_visible',
            ]);
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_facing_name',
                'phase',
                'client_visible',
                'subcontractor_visible',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('name');
            $table->string('customer_facing_name')->nullable()->after('title');
            $table->string('phase')->default('Construction')->after('customer_facing_name');
            $table->boolean('is_required')->default(true)->after('default_duration_working_days');
            $table->boolean('is_customer_visible')->default(true)->after('is_required');
            $table->boolean('is_subcontractor_visible')->default(false)->after('is_customer_visible');
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->string('customer_facing_name')->nullable()->after('title');
            $table->string('phase')->default('Construction')->after('customer_facing_name');
            $table->boolean('client_visible')->default(true)->after('actual_end_date');
            $table->boolean('subcontractor_visible')->default(false)->after('client_visible');
        });

        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->dropColumn('internal_only');
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->dropColumn('internal_only');
        });
    }
};
