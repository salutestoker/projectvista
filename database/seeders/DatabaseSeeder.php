<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\ApprovalTemplate;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\PaymentMilestone;
use App\Models\PaymentTemplate;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Selection;
use App\Models\SelectionCategory;
use App\Models\TimelineTask;
use App\Models\TimelineTemplate;
use App\Models\User;
use App\Support\ProjectVista\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $superAdmin = User::query()->create([
            'name' => 'Nick ProjectVista',
            'email' => 'super@projectvista.test',
            'password' => $password,
            'email_verified_at' => now(),
            'is_super_admin' => true,
        ]);

        $companyAdmin = User::query()->create([
            'name' => 'Olivia Bennett',
            'email' => 'admin@omnipools.test',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $manager = User::query()->create([
            'name' => 'Terry Marshall',
            'email' => 'manager@omnipools.test',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $client = User::query()->create([
            'name' => 'Avery Smith',
            'email' => 'client@omnipools.test',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $subcontractor = User::query()->create([
            'name' => 'Marco Ruiz',
            'email' => 'sub@omnipools.test',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $otherAdmin = User::query()->create([
            'name' => 'Parker Cross',
            'email' => 'admin@desertstone.test',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $company = Company::query()->create([
            'name' => 'Omni Pool Builders',
            'slug' => 'omni-pool-builders',
            'plan' => 'signature',
            'subscription_status' => 'trial',
            'brand_primary_color' => '#090b0f',
            'brand_accent_color' => '#d6b36a',
            'logo_path' => 'demo/omni-mark.png',
            'feature_flags' => [
                'media_messaging' => true,
                'payments_visible' => true,
                'document_uploads' => true,
                'subcontractor_access' => true,
                'branded_portal' => true,
                'ai_update_writer' => false,
                'custom_domain' => false,
            ],
            'settings' => [
                'market' => 'Luxury pool construction',
                'portal_tone' => 'calm, polished, precise',
            ],
        ]);

        $otherCompany = Company::query()->create([
            'name' => 'Desert Stone Works',
            'slug' => 'desert-stone-works',
            'plan' => 'demo',
            'subscription_status' => 'trial',
            'brand_primary_color' => '#16120d',
            'brand_accent_color' => '#c79545',
            'feature_flags' => [
                'media_messaging' => false,
                'payments_visible' => true,
                'document_uploads' => true,
                'subcontractor_access' => false,
                'branded_portal' => true,
                'ai_update_writer' => false,
                'custom_domain' => false,
            ],
        ]);

        $company->users()->attach($companyAdmin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'title' => 'Owner',
            'joined_at' => now(),
        ]);
        $company->users()->attach($manager->id, [
            'role' => Roles::COMPANY_MANAGER,
            'title' => 'Senior Project Manager',
            'joined_at' => now(),
        ]);
        $company->users()->attach($subcontractor->id, [
            'role' => Roles::SUBCONTRACTOR,
            'title' => 'Tile Subcontractor',
            'joined_at' => now(),
        ]);
        $otherCompany->users()->attach($otherAdmin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'title' => 'Owner',
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
            'name' => 'Smith Residence',
            'slug' => 'smith-residence',
            'address_line' => '7420 N Silver Palm Drive',
            'city' => 'Scottsdale',
            'state' => 'Arizona',
            'postal_code' => '85255',
            'project_type' => 'Luxury Pool & Outdoor Living',
            'status' => 'active',
            'phase' => 'Tile Installation',
            'percent_complete' => 62,
            'health_status' => 'needs_client_decision',
            'contract_amount' => 286500,
            'starts_on' => now()->subMonths(3),
            'estimated_completion_on' => now()->addWeeks(7),
            'hero_image_path' => 'demo/smith-residence-hero.png',
            'client_summary' => 'A resort-style pool, spa, sun shelf, fire feature, and outdoor kitchen designed around evening entertaining.',
            'latest_update' => 'Your pool tile installation has officially started, and the coping phase is now complete.',
            'next_step' => 'Please approve the proposed decking selection by Friday to keep the next finish stage on schedule.',
        ]);

        Project::query()->create([
            'company_id' => $otherCompany->id,
            'manager_id' => $otherAdmin->id,
            'name' => 'Canyon Courtyard',
            'slug' => 'canyon-courtyard',
            'address_line' => '1107 W Ocotillo Lane',
            'city' => 'Phoenix',
            'state' => 'Arizona',
            'project_type' => 'Masonry',
            'status' => 'active',
            'phase' => 'Stone Layout',
            'percent_complete' => 31,
            'health_status' => 'on_track',
        ]);

        $project->users()->attach($manager->id, [
            'role' => Roles::COMPANY_MANAGER,
            'assigned_scope' => 'Full project management',
        ]);
        $project->users()->attach($client->id, [
            'role' => Roles::CLIENT,
            'assigned_scope' => 'Homeowner portal',
        ]);
        $project->users()->attach($subcontractor->id, [
            'role' => Roles::SUBCONTRACTOR,
            'assigned_scope' => 'Tile and waterline coordination',
            'permissions' => json_encode(['timeline', 'approved_selections', 'visible_documents']),
        ]);

        $timelineTemplate = TimelineTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Luxury Pool Build',
            'description' => 'Default phase sequence for high-touch pool construction.',
            'is_default' => true,
        ]);

        $timelineRows = [
            ['Design Approval', 'Preconstruction', 'completed', -82, -74, true],
            ['Permitting & Engineering', 'Preconstruction', 'completed', -73, -47, false],
            ['Excavation', 'Construction', 'completed', -38, -35, false],
            ['Steel & Plumbing Rough-In', 'Construction', 'completed', -34, -24, true],
            ['Gunite Shell', 'Construction', 'completed', -23, -19, false],
            ['Coping Complete', 'Finishes', 'completed', -12, -4, true],
            ['Tile Installation', 'Finishes', 'in_progress', -3, 8, true],
            ['Decking Approval Needed', 'Finishes', 'blocked', 2, 5, false],
            ['Interior Finish', 'Startup', 'upcoming', 16, 24, false],
            ['Water Fill & Orientation', 'Handoff', 'upcoming', 28, 35, false],
        ];

        foreach ($timelineRows as $index => [$title, $phase, $status, $startsOffset, $dueOffset, $subVisible]) {
            TimelineTask::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'timeline_template_id' => $timelineTemplate->id,
                'title' => $title,
                'phase' => $phase,
                'description' => $title === 'Decking Approval Needed'
                    ? 'Homeowner approval keeps the next finish stage moving without a schedule pause.'
                    : 'Project milestone for the Smith Residence build.',
                'sort_order' => $index + 1,
                'status' => $status,
                'starts_on' => now()->addDays($startsOffset),
                'due_on' => now()->addDays($dueOffset),
                'completed_on' => $status === 'completed' ? now()->addDays($dueOffset) : null,
                'client_visible' => true,
                'subcontractor_visible' => $subVisible,
                'requires_acknowledgement' => $title === 'Decking Approval Needed',
            ]);
        }

        $categories = collect([
            ['Tile', 'Waterline and accent tile selections', 1],
            ['Decking', 'Pool deck finish and color choices', 2],
            ['Lighting', 'Exterior lighting packages', 3],
        ])->map(fn (array $row) => SelectionCategory::query()->create([
            'company_id' => $company->id,
            'name' => $row[0],
            'description' => $row[1],
            'sort_order' => $row[2],
        ]));

        $tileSelection = Selection::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'selection_category_id' => $categories[0]->id,
            'approved_by' => $client->id,
            'name' => 'Mediterranean Blue Waterline Tile',
            'description' => 'A deep blue porcelain tile with slight variation for a resort-style waterline.',
            'image_path' => 'demo/selection-tile.png',
            'status' => 'approved',
            'manager_note' => 'Installed phase has started with this approved selection.',
            'client_response' => 'Approved. This is the tile we want.',
            'due_on' => now()->subWeeks(2),
            'approved_at' => now()->subWeeks(2),
        ]);

        $deckingSelection = Selection::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'selection_category_id' => $categories[1]->id,
            'name' => 'Ivory Travertine Decking',
            'description' => 'Cool-touch ivory travertine pavers for the main patio and pool deck.',
            'image_path' => 'demo/selection-decking.png',
            'status' => 'waiting_client',
            'manager_note' => 'Manager approved. Waiting for homeowner approval by Friday.',
            'due_on' => now()->addDays(4),
        ]);

        $approvalTemplate = ApprovalTemplate::query()->create([
            'company_id' => $company->id,
            'title' => 'Selection Approval',
            'description' => 'Client approval for material selections.',
            'default_due_days' => 4,
        ]);

        Approval::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'approval_template_id' => $approvalTemplate->id,
            'selection_id' => $deckingSelection->id,
            'requested_by_id' => $manager->id,
            'title' => 'Decking Selection Approval',
            'body' => 'Please review and approve the proposed decking selection. Approval by Friday keeps the next phase on schedule.',
            'status' => 'pending',
            'due_on' => now()->addDays(4),
        ]);

        Approval::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'approval_template_id' => $approvalTemplate->id,
            'selection_id' => $tileSelection->id,
            'requested_by_id' => $manager->id,
            'responded_by_id' => $client->id,
            'title' => 'Waterline Tile Approval',
            'body' => 'Please approve the waterline tile before installation begins.',
            'status' => 'approved',
            'due_on' => now()->subWeeks(2),
            'responded_at' => now()->subWeeks(2),
            'response_note' => 'Approved.',
        ]);

        foreach ([
            ['deposit', 'Design deposit', 1],
            ['gunite', 'Gunite shell', 2],
            ['tile-coping', 'Tile / Coping', 3],
            ['final', 'Final orientation', 4],
        ] as [$name, $label, $order]) {
            PaymentTemplate::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'label' => $label,
                'sort_order' => $order,
            ]);
        }

        foreach ([
            ['Design Deposit', 'Paid before design and engineering began.', 31500, 'paid', -88, -86, null, null, 1],
            ['Gunite Shell', 'Marked paid after shell inspection.', 82500, 'paid', -21, -20, null, null, 2],
            ['Tile / Coping', 'Current milestone due for the finish stage.', 68000, 'due', 3, null, 'https://pay.example.test/projectvista/smith-tile-coping', 'External payment link', 3],
            ['Final Orientation', 'Due before final handoff and orientation.', 42000, 'scheduled', 31, null, null, null, 4],
        ] as [$label, $description, $amount, $status, $dueOffset, $completedOffset, $paymentUrl, $provider, $order]) {
            PaymentMilestone::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'label' => $label,
                'description' => $description,
                'amount' => $amount,
                'status' => $status,
                'due_on' => now()->addDays($dueOffset),
                'completed_on' => $completedOffset === null ? null : now()->addDays($completedOffset),
                'payment_url' => $paymentUrl,
                'provider_label' => $provider,
                'client_visible' => true,
                'sort_order' => $order,
            ]);
        }

        foreach ([
            ['Construction Agreement', 'Contracts', 'demo/construction-agreement.pdf', true, false, 'client'],
            ['Tile Layout Sheet', 'Selections', 'demo/tile-layout.pdf', true, true, 'shared'],
            ['Internal Sub Schedule', 'Operations', 'demo/internal-sub-schedule.pdf', false, false, 'internal'],
            ['Equipment Manual Packet', 'Handoff', 'demo/equipment-manual.pdf', true, false, 'client'],
        ] as [$title, $category, $path, $clientVisible, $subVisible, $visibility]) {
            ProjectDocument::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'uploaded_by_id' => $manager->id,
                'title' => $title,
                'category' => $category,
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => 128000,
                'visibility' => $visibility,
                'client_visible' => $clientVisible,
                'subcontractor_visible' => $subVisible,
                'internal_notes' => $visibility === 'internal' ? 'Not visible to homeowners.' : null,
            ]);
        }

        foreach ([
            ['progress', 'demo/smith-progress-1.png', 'Coping complete and tile layout staged.'],
            ['selection', 'demo/selection-tile.png', 'Approved Mediterranean blue waterline tile.'],
            ['selection', 'demo/selection-decking.png', 'Proposed ivory travertine decking.'],
        ] as [$collection, $path, $altText]) {
            MediaAsset::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'uploaded_by_id' => $manager->id,
                'collection' => $collection,
                'path' => $path,
                'alt_text' => $altText,
            ]);
        }

        $thread = MessageThread::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'subject' => 'Decking approval and next finish stage',
            'status' => 'open',
            'last_message_at' => now()->subHours(3),
        ]);

        foreach ([
            [$manager->id, 'Tile started today and the coping phase is complete. The next decision is decking approval, which keeps the project moving smoothly into the next finish stage.', -7],
            [$client->id, 'Thanks, the update is clear. We are reviewing the decking selection tonight.', -5],
            [$manager->id, 'Perfect. Approval by Friday keeps the installation window reserved.', -3],
        ] as [$userId, $body, $hours]) {
            Message::query()->create([
                'message_thread_id' => $thread->id,
                'company_id' => $company->id,
                'project_id' => $project->id,
                'user_id' => $userId,
                'body' => $body,
                'created_at' => now()->addHours($hours),
                'updated_at' => now()->addHours($hours),
            ]);
        }

        foreach ([
            ['Johnson Residence', 'johnson-residence', 'Paradise Valley', 'Plumbing Rough-in', 48, 82500, 45000, 1, 3, -2, 4],
            ['Williams Residence', 'williams-residence', 'Phoenix', 'Decking Layout', 75, 120000, 96000, 3, 8, 1, 5],
            ['Brown Residence', 'brown-residence', 'Chandler', 'Excavation', 35, 80000, 32000, 2, 2, -4, 1],
            ['Davis Residence', 'davis-residence', 'Scottsdale', 'Startup & Balance', 90, 120000, 108000, 1, 5, 3, 6],
        ] as [$projectName, $slug, $city, $phase, $progress, $contractAmount, $paidAmount, $approvalCount, $messageCount, $startOffset, $dueOffset]) {
            $extraProject = Project::query()->create([
                'company_id' => $company->id,
                'manager_id' => $manager->id,
                'name' => $projectName,
                'slug' => $slug,
                'address_line' => fake()->streetAddress(),
                'city' => $city,
                'state' => 'AZ',
                'postal_code' => '85255',
                'project_type' => 'Luxury Pool & Outdoor Living',
                'status' => 'active',
                'phase' => $phase,
                'percent_complete' => $progress,
                'health_status' => $approvalCount > 1 ? 'needs_client_decision' : 'on_track',
                'contract_amount' => $contractAmount,
                'starts_on' => now()->subWeeks(8),
                'estimated_completion_on' => now()->addWeeks(6),
                'hero_image_path' => 'demo/smith-residence-hero.png',
                'client_summary' => 'A premium outdoor living project tracked through ProjectVista.',
                'latest_update' => "{$phase} is the current focus for {$projectName}.",
                'next_step' => 'Keep approvals and assigned work moving this week.',
            ]);

            $extraProject->users()->attach($manager->id, [
                'role' => Roles::COMPANY_MANAGER,
                'assigned_scope' => 'Full project management',
            ]);
            $extraProject->users()->attach($subcontractor->id, [
                'role' => Roles::SUBCONTRACTOR,
                'assigned_scope' => 'Tile Contractor',
                'permissions' => json_encode(['timeline', 'approved_selections', 'visible_documents']),
            ]);

            foreach ([
                ['Preconstruction Complete', 'Preconstruction', 'completed', -30, -20, false],
                [$phase, 'Construction', $progress > 80 ? 'upcoming' : 'in_progress', $startOffset, $dueOffset, true],
                ['Client Review', 'Finishes', $approvalCount > 1 ? 'blocked' : 'upcoming', $dueOffset + 1, $dueOffset + 4, false],
            ] as $index => [$title, $taskPhase, $status, $startsOffset, $taskDueOffset, $subVisible]) {
                TimelineTask::query()->create([
                    'company_id' => $company->id,
                    'project_id' => $extraProject->id,
                    'timeline_template_id' => $timelineTemplate->id,
                    'title' => $title,
                    'phase' => $taskPhase,
                    'description' => 'Demo milestone for the expanded ProjectVista home dashboard.',
                    'sort_order' => $index + 1,
                    'status' => $status,
                    'starts_on' => now()->addDays($startsOffset),
                    'due_on' => now()->addDays($taskDueOffset),
                    'completed_on' => $status === 'completed' ? now()->addDays($taskDueOffset) : null,
                    'client_visible' => true,
                    'subcontractor_visible' => $subVisible,
                    'requires_acknowledgement' => $status === 'blocked',
                ]);
            }

            $extraSelection = Selection::query()->create([
                'company_id' => $company->id,
                'project_id' => $extraProject->id,
                'selection_category_id' => $categories[1]->id,
                'name' => "{$phase} Confirmation",
                'description' => 'Demo selection supporting the role-specific home dashboards.',
                'image_path' => 'demo/selection-decking.png',
                'status' => $approvalCount > 0 ? 'waiting_client' : 'approved',
                'manager_note' => 'Waiting for customer review.',
                'due_on' => now()->addDays($dueOffset),
            ]);

            for ($i = 1; $i <= $approvalCount; $i++) {
                Approval::query()->create([
                    'company_id' => $company->id,
                    'project_id' => $extraProject->id,
                    'approval_template_id' => $approvalTemplate->id,
                    'selection_id' => $extraSelection->id,
                    'requested_by_id' => $manager->id,
                    'title' => "{$phase} Approval {$i}",
                    'body' => "Please review the {$phase} approval for {$projectName}.",
                    'status' => 'pending',
                    'due_on' => now()->addDays($dueOffset + $i),
                ]);
            }

            foreach ([
                ['Paid Milestone', 'Completed project milestone.', $paidAmount, 'paid', -5, -4, 1],
                ['Current Milestone', 'Upcoming external payment milestone.', $contractAmount - $paidAmount, 'due', $dueOffset, null, 2],
            ] as [$label, $description, $amount, $status, $paymentDueOffset, $completedOffset, $order]) {
                PaymentMilestone::query()->create([
                    'company_id' => $company->id,
                    'project_id' => $extraProject->id,
                    'label' => $label,
                    'description' => $description,
                    'amount' => $amount,
                    'status' => $status,
                    'due_on' => now()->addDays($paymentDueOffset),
                    'completed_on' => $completedOffset === null ? null : now()->addDays($completedOffset),
                    'payment_url' => $status === 'due' ? 'https://pay.example.test/projectvista/'.$slug : null,
                    'provider_label' => $status === 'due' ? 'External payment link' : null,
                    'client_visible' => true,
                    'sort_order' => $order,
                ]);
            }

            ProjectDocument::query()->create([
                'company_id' => $company->id,
                'project_id' => $extraProject->id,
                'uploaded_by_id' => $manager->id,
                'title' => "{$phase} Field Packet",
                'category' => 'Operations',
                'path' => 'demo/tile-layout.pdf',
                'mime_type' => 'application/pdf',
                'size' => 128000,
                'visibility' => 'shared',
                'client_visible' => true,
                'subcontractor_visible' => true,
            ]);

            MediaAsset::query()->create([
                'company_id' => $company->id,
                'project_id' => $extraProject->id,
                'uploaded_by_id' => $manager->id,
                'collection' => 'progress',
                'path' => 'demo/smith-progress-1.png',
                'alt_text' => "{$phase} update",
            ]);

            $extraThread = MessageThread::query()->create([
                'company_id' => $company->id,
                'project_id' => $extraProject->id,
                'subject' => "{$phase} update",
                'status' => 'open',
                'last_message_at' => now()->subHours($messageCount),
            ]);

            for ($i = 1; $i <= $messageCount; $i++) {
                Message::query()->create([
                    'message_thread_id' => $extraThread->id,
                    'company_id' => $company->id,
                    'project_id' => $extraProject->id,
                    'user_id' => $client->id,
                    'body' => "Customer message {$i} about {$phase}.",
                    'created_at' => now()->subHours($messageCount - $i + 1),
                    'updated_at' => now()->subHours($messageCount - $i + 1),
                ]);
            }
        }

        Invitation::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'invited_by_id' => $companyAdmin->id,
            'email' => 'new-client@smith.test',
            'role' => Roles::CLIENT,
            'token' => Str::random(40),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $superAdmin->companies()->syncWithoutDetaching([
            $company->id => ['role' => Roles::COMPANY_ADMIN, 'title' => 'Platform Support', 'joined_at' => now()],
        ]);
    }
}
