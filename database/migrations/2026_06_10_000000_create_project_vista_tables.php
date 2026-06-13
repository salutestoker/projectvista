<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan')->default('demo');
            $table->string('subscription_status')->default('trial');
            $table->string('brand_primary_color')->default('#0b1020');
            $table->string('brand_accent_color')->default('#d6b36a');
            $table->string('logo_path')->nullable();
            $table->json('feature_flags');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('title')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'role']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('address_line');
            $table->string('city');
            $table->string('state', 32);
            $table->string('postal_code', 32)->nullable();
            $table->string('project_type')->default('pool');
            $table->string('status')->default('active');
            $table->string('phase')->default('Planning');
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->string('health_status')->default('on_track');
            $table->decimal('contract_amount', 12, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('estimated_completion_on')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->text('client_summary')->nullable();
            $table->text('latest_update')->nullable();
            $table->text('next_step')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('project_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('assigned_scope')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id', 'role']);
            $table->index(['project_id', 'role']);
        });

        Schema::create('timeline_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('timeline_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('timeline_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('phase');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('upcoming');
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->boolean('client_visible')->default(true);
            $table->boolean('subcontractor_visible')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'sort_order']);
        });

        Schema::create('selection_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selection_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('manager_review');
            $table->text('manager_note')->nullable();
            $table->text('client_response')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('visibility')->default('internal');
            $table->boolean('client_visible')->default(false);
            $table->boolean('subcontractor_visible')->default(false);
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'visibility']);
        });

        Schema::create('approval_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_due_days')->default(3);
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('selection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->date('due_on')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('payment_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('amount_type')->default('fixed');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status')->default('scheduled');
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('payment_url')->nullable();
            $table->string('provider_label')->nullable();
            $table->boolean('client_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('collection')->default('project');
            $table->string('kind')->default('image');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'collection']);
        });

        Schema::create('message_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility')->default('manager_client');
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'created_at']);
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'email', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('payment_milestones');
        Schema::dropIfExists('payment_templates');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_templates');
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('selections');
        Schema::dropIfExists('selection_categories');
        Schema::dropIfExists('timeline_tasks');
        Schema::dropIfExists('timeline_templates');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }
};
