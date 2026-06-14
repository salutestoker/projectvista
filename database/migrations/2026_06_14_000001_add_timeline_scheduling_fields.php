<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractor_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::table('company_user', function (Blueprint $table): void {
            $table->foreignId('subcontractor_type_id')
                ->nullable()
                ->after('title')
                ->constrained('subcontractor_types')
                ->nullOnDelete();
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->foreignId('subcontractor_type_id')
                ->nullable()
                ->after('role')
                ->constrained('subcontractor_types')
                ->nullOnDelete();
        });

        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->foreignId('assigned_subcontractor_id')
                ->nullable()
                ->after('timeline_template_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('subcontractor_type_id')
                ->nullable()
                ->after('assigned_subcontractor_id')
                ->constrained('subcontractor_types')
                ->nullOnDelete();

            $table->index(['company_id', 'assigned_subcontractor_id', 'starts_on', 'due_on'], 'timeline_tasks_sub_schedule_index');
            $table->index(['project_id', 'starts_on', 'due_on'], 'timeline_tasks_project_schedule_index');
        });
    }

    public function down(): void
    {
        Schema::table('timeline_tasks', function (Blueprint $table): void {
            $table->dropIndex('timeline_tasks_sub_schedule_index');
            $table->dropIndex('timeline_tasks_project_schedule_index');
            $table->dropConstrainedForeignId('assigned_subcontractor_id');
            $table->dropConstrainedForeignId('subcontractor_type_id');
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subcontractor_type_id');
        });

        Schema::table('company_user', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subcontractor_type_id');
        });

        Schema::dropIfExists('subcontractor_types');
    }
};
