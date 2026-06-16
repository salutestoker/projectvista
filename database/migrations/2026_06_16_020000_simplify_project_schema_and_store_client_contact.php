<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('client_name')->nullable()->after('postal_code');
            $table->string('client_email')->nullable()->after('client_name');
        });

        DB::table('project_user')
            ->join('users', 'users.id', '=', 'project_user.user_id')
            ->where('project_user.role', 'client')
            ->select([
                'project_user.project_id',
                'users.name',
                'users.email',
            ])
            ->orderBy('project_user.project_id')
            ->each(function (object $client): void {
                DB::table('projects')
                    ->where('id', $client->project_id)
                    ->update([
                        'client_name' => $client->name,
                        'client_email' => $client->email,
                    ]);
            });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_company_id_status_index');
            $table->dropColumn([
                'project_type',
                'status',
                'phase',
                'starts_on',
                'estimated_completion_on',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('project_type')->default('pool')->after('postal_code');
            $table->string('status')->default('active')->after('project_type');
            $table->string('phase')->default('Planning')->after('status');
            $table->date('starts_on')->nullable()->after('contract_amount');
            $table->date('estimated_completion_on')->nullable()->after('starts_on');
            $table->index(['company_id', 'status']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['client_name', 'client_email']);
        });
    }
};
