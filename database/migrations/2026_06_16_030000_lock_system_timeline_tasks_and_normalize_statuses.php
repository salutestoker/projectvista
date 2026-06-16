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
            $table->boolean('is_system')->default(false)->after('internal_only');
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('internal_only');
        });

        DB::table('timeline_tasks')
            ->where('status', 'completed')
            ->update(['status' => 'complete']);

        DB::table('timeline_task_templates')
            ->whereRaw('LOWER(name) = ?', ['contract signed'])
            ->update([
                'name' => 'Contract Signed',
                'sequence_order' => 1,
                'default_duration_working_days' => 1,
                'is_system' => true,
            ]);

        DB::table('timeline_tasks')
            ->whereRaw('LOWER(title) = ?', ['contract signed'])
            ->update([
                'title' => 'Contract Signed',
                'sequence_order' => 1,
                'sort_order' => 1,
                'default_duration_working_days' => 1,
                'status' => 'complete',
                'is_system' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('timeline_task_templates', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
