<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontractor_types', function (Blueprint $table): void {
            $table->boolean('allows_same_project_overlap')
                ->default(false)
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_types', function (Blueprint $table): void {
            $table->dropColumn('allows_same_project_overlap');
        });
    }
};
