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
use App\Models\SubcontractorType;
use App\Models\TimelineTask;
use App\Models\TimelineTaskTemplate;
use App\Models\TimelineTemplate;
use App\Models\User;
use App\Support\ProjectVista\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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

        $subcontractorTypes = $this->seedSubcontractorTypes($company);
        $this->seedSubcontractorTypes($otherCompany);

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
            'title' => 'Tile Pro Solutions',
            'subcontractor_type_id' => $subcontractorTypes->get('tile-stone')->id,
            'joined_at' => now(),
        ]);
        $otherCompany->users()->attach($otherAdmin->id, [
            'role' => Roles::COMPANY_ADMIN,
            'title' => 'Owner',
            'joined_at' => now(),
        ]);

        $omniSubcontractors = collect([
            'tile-stone' => [
                'user' => $subcontractor,
                'title' => 'Tile Pro Solutions',
                'scope' => 'Tile and waterline coordination',
                'type' => $subcontractorTypes->get('tile-stone'),
            ],
        ]);

        foreach ([
            ['plumbing', 'Rob Walker', 'plumbing@omnipools.test', 'Precision Plumbing', 'Plumbing rough-in, equipment set, and pressure testing'],
            ['decking', 'Lisa Hernandez', 'decking@omnipools.test', 'Deck Pro AZ', 'Travertine decking, coping support, and finish layout'],
            ['electrical', 'Tom Becker', 'electrical@omnipools.test', 'Bright Electric', 'Electrical rough-in, controls, and low-voltage lighting'],
            ['pool-construction', 'Mike Anderson', 'pool-construction@omnipools.test', 'Aqua Blue Pools', 'Excavation, shell, startup, and balance'],
            ['landscaping', 'David Green', 'landscape@omnipools.test', 'Eco Landscape', 'Planting, turf, and landscape finish work'],
            ['hardscape', 'Elena Cruz', 'hardscape@omnipools.test', 'Cruz Hardscape', 'Pavers, retaining walls, and masonry flatwork'],
            ['irrigation', 'Nate Kim', 'irrigation@omnipools.test', 'Sonoran Irrigation', 'Irrigation, drainage, and water management'],
            ['outdoor-kitchen', 'Bianca Lee', 'outdoor-kitchen@omnipools.test', 'Desert Flame Outdoor Kitchens', 'Outdoor kitchens, counters, and appliance setting'],
            ['automation', 'Marcus Bell', 'automation@omnipools.test', 'ClearWater Controls', 'Pool automation, controls, and smart equipment setup'],
        ] as [$typeSlug, $name, $email, $title, $scope]) {
            $tradeUser = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            $type = $subcontractorTypes->get($typeSlug);

            $company->users()->attach($tradeUser->id, [
                'role' => Roles::SUBCONTRACTOR,
                'title' => $title,
                'subcontractor_type_id' => $type->id,
                'joined_at' => now()->subDays(random_int(30, 180)),
            ]);

            $omniSubcontractors->put($typeSlug, [
                'user' => $tradeUser,
                'title' => $title,
                'scope' => $scope,
                'type' => $type,
            ]);
        }

        $project = Project::query()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
            'name' => 'Smith Residence',
            'slug' => 'smith-residence',
            'address_line' => '7420 N Silver Palm Drive',
            'city' => 'Scottsdale',
            'state' => 'Arizona',
            'postal_code' => '85255',
            'client_name' => $client->name,
            'client_email' => $client->email,
            'percent_complete' => 62,
            'health_status' => 'needs_client_decision',
            'contract_amount' => 286500,
            'contract_signed_on' => now()->subMonths(4),
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
            'client_name' => 'Canyon Client',
            'client_email' => 'client@desertmasonry.test',
            'percent_complete' => 31,
            'health_status' => 'on_track',
            'contract_signed_on' => now()->subMonths(2),
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
        $this->seedDefaultTimelineTaskTemplates($timelineTemplate, $subcontractorTypes);

        $timelineRows = [
            ['Contract Signed', 'Preconstruction', 'complete', -90, -90, false, true],
            ['Design Approval', 'Preconstruction', 'complete', -82, -74, true, false],
            ['Permitting & Engineering', 'Preconstruction', 'complete', -73, -47, false, false],
            ['Excavation', 'Construction', 'complete', -38, -35, false, false],
            ['Steel & Plumbing Rough-In', 'Construction', 'complete', -34, -24, true, false],
            ['Gunite Shell', 'Construction', 'complete', -23, -19, false, false],
            ['Coping Complete', 'Finishes', 'complete', -12, -4, true, false],
            ['Tile Installation', 'Finishes', 'in_progress', -3, 8, true, false],
            ['Decking Approval Needed', 'Finishes', 'blocked', 2, 5, false, false],
            ['Interior Finish', 'Startup', 'upcoming', 16, 24, false, false],
            ['Water Fill & Orientation', 'Handoff', 'upcoming', 28, 35, false, false],
        ];

        foreach ($timelineRows as $index => [$title, $phase, $status, $startsOffset, $dueOffset, $subVisible, $isSystem]) {
            $assignment = $subVisible ? $this->omniSubcontractorForTask($omniSubcontractors, $title.' '.$phase) : null;

            if ($assignment !== null) {
                $this->attachProjectSubcontractor($project, $assignment);
            }

            $startsOn = $isSystem ? $project->contract_signed_on : now()->addDays($startsOffset);
            $dueOn = $isSystem ? $project->contract_signed_on : now()->addDays($dueOffset);

            TimelineTask::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'timeline_template_id' => $timelineTemplate->id,
                'assigned_subcontractor_id' => $assignment['user']->id ?? null,
                'subcontractor_type_id' => $assignment['type']->id ?? null,
                'title' => $title,
                'description' => $title === 'Decking Approval Needed'
                    ? 'Homeowner approval keeps the next finish stage moving without a schedule pause.'
                    : 'Project milestone for the Smith Residence build.',
                'sort_order' => $index + 1,
                'status' => $status,
                'starts_on' => $startsOn,
                'due_on' => $dueOn,
                'completed_on' => $status === 'complete' ? $dueOn : null,
                'internal_only' => false,
                'is_system' => $isSystem,
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
            ['Miller Residence', 'miller-residence', 'Scottsdale', 'Tile Installation', 58, 154000, 62000, 1, 4, 2, 4],
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
                'client_name' => str($projectName)->before(' Residence')->append(' Client')->toString(),
                'client_email' => Str::slug($projectName).'@example.test',
                'percent_complete' => $progress,
                'health_status' => $approvalCount > 1 ? 'needs_client_decision' : 'on_track',
                'contract_amount' => $contractAmount,
                'contract_signed_on' => now()->addDays($startOffset - 45),
                'hero_image_path' => 'demo/smith-residence-hero.png',
                'client_summary' => 'A premium outdoor living project tracked through ProjectVista.',
                'latest_update' => "{$phase} is the current focus for {$projectName}.",
                'next_step' => 'Keep approvals and assigned work moving this week.',
            ]);

            $extraProject->users()->attach($manager->id, [
                'role' => Roles::COMPANY_MANAGER,
                'assigned_scope' => 'Full project management',
            ]);
            $projectAssignment = $this->omniSubcontractorForTask($omniSubcontractors, $phase.' '.$projectName);
            $this->attachProjectSubcontractor($extraProject, $projectAssignment);
            $this->attachProjectSubcontractor($extraProject, $omniSubcontractors->get('tile-stone'));

            foreach ([
                ['Contract Signed', 'Preconstruction', 'complete', $startOffset - 45, $startOffset - 45, false, true],
                ['Preconstruction Complete', 'Preconstruction', 'complete', -30, -20, false, false],
                [$phase, 'Construction', $progress > 80 ? 'upcoming' : 'in_progress', $startOffset, $dueOffset, true, false],
                ['Client Review', 'Finishes', $approvalCount > 1 ? 'blocked' : 'upcoming', $dueOffset + 1, $dueOffset + 4, false, false],
            ] as $index => [$title, $taskPhase, $status, $startsOffset, $taskDueOffset, $subVisible, $isSystem]) {
                $assignment = $subVisible ? $this->omniSubcontractorForTask($omniSubcontractors, $title.' '.$phase.' '.$projectName) : null;

                if ($assignment !== null) {
                    $this->attachProjectSubcontractor($extraProject, $assignment);
                }

                $startsOn = $isSystem ? $extraProject->contract_signed_on : now()->addDays($startsOffset);
                $dueOn = $isSystem ? $extraProject->contract_signed_on : now()->addDays($taskDueOffset);

                TimelineTask::query()->create([
                    'company_id' => $company->id,
                    'project_id' => $extraProject->id,
                    'timeline_template_id' => $timelineTemplate->id,
                    'assigned_subcontractor_id' => $assignment['user']->id ?? null,
                    'subcontractor_type_id' => $assignment['type']->id ?? null,
                    'title' => $title,
                    'description' => 'Demo milestone for the expanded ProjectVista home dashboard.',
                    'sort_order' => $index + 1,
                    'status' => $status,
                    'starts_on' => $startsOn,
                    'due_on' => $dueOn,
                    'completed_on' => $status === 'complete' ? $dueOn : null,
                    'internal_only' => false,
                    'is_system' => $isSystem,
                    'requires_acknowledgement' => $status === 'blocked',
                ]);
            }

            TimelineTask::query()->create([
                'company_id' => $company->id,
                'project_id' => $extraProject->id,
                'timeline_template_id' => $timelineTemplate->id,
                'assigned_subcontractor_id' => $subcontractor->id,
                'subcontractor_type_id' => $subcontractorTypes->get('tile-stone')->id,
                'title' => 'Tile Detail Review',
                'description' => 'Shared tile coordination item for the expanded subcontractor demo account.',
                'sort_order' => 5,
                'status' => 'upcoming',
                'starts_on' => now()->addDays($startOffset + 2),
                'due_on' => now()->addDays($dueOffset + 2),
                'internal_only' => false,
            ]);

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
        $this->seedTimelineConflictExamples($company, $project, $omniSubcontractors, $subcontractorTypes);

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

        $this->seedAdditionalDemoTenants($password);

        $superAdmin->companies()->syncWithoutDetaching([
            $company->id => ['role' => Roles::COMPANY_ADMIN, 'title' => 'Platform Support', 'joined_at' => now()],
        ]);
    }

    /**
     * Add realistic tenant volume for the platform-level views without changing
     * the stable Omni demo accounts used throughout the feature tests.
     */
    private function seedAdditionalDemoTenants(string $password): void
    {
        $tenants = [
            [
                'company' => [
                    'name' => 'Aurelia Outdoor Living',
                    'slug' => 'aurelia-outdoor-living',
                    'plan' => 'signature',
                    'subscription_status' => 'active',
                    'brand_primary_color' => '#081111',
                    'brand_accent_color' => '#d7b56d',
                    'market' => 'Resort-style outdoor living',
                ],
                'owner' => ['name' => 'Vivian Harrow', 'email' => 'owner@aurelia.test'],
                'managers' => [
                    ['name' => 'Caleb Rios', 'email' => 'manager.caleb@aurelia.test', 'title' => 'Director of Construction'],
                    ['name' => 'Mina Wolfe', 'email' => 'manager.mina@aurelia.test', 'title' => 'Client Experience Manager'],
                ],
                'subs' => [
                    ['name' => 'Rafael Ortega', 'email' => 'tile@aurelia.test', 'title' => 'Tile Partner', 'scope' => 'Waterline tile and coping'],
                    ['name' => 'Noah Pierce', 'email' => 'landscape@aurelia.test', 'title' => 'Landscape Partner', 'scope' => 'Planting and lighting'],
                    ['name' => 'Maya Chen', 'email' => 'plumbing@aurelia.test', 'title' => 'Plumbing Partner', 'scope' => 'Plumbing rough-in and equipment set'],
                    ['name' => 'Eli Vargas', 'email' => 'electrical@aurelia.test', 'title' => 'Electrical Partner', 'scope' => 'Pool controls and landscape lighting'],
                    ['name' => 'Sofia Grant', 'email' => 'kitchen@aurelia.test', 'title' => 'Outdoor Kitchen Partner', 'scope' => 'Outdoor kitchen counters and appliances'],
                ],
                'projects' => [
                    ['name' => 'Silverleaf Retreat', 'city' => 'Scottsdale', 'state' => 'AZ', 'type' => 'Pool, Spa & Cabana', 'phase' => 'Water Feature Walls', 'progress' => 58, 'health' => 'needs_client_decision', 'contract' => 412000, 'paid' => 223000, 'approvals' => 3, 'messages' => 6, 'offset' => -74],
                    ['name' => 'Camelback Courtyard', 'city' => 'Paradise Valley', 'state' => 'AZ', 'type' => 'Outdoor Living Renovation', 'phase' => 'Travertine Decking', 'progress' => 71, 'health' => 'on_track', 'contract' => 268500, 'paid' => 181000, 'approvals' => 1, 'messages' => 4, 'offset' => -61],
                    ['name' => 'Ocotillo Modern Pool', 'city' => 'Phoenix', 'state' => 'AZ', 'type' => 'Modern Pool Build', 'phase' => 'Interior Finish', 'progress' => 84, 'health' => 'on_track', 'contract' => 197000, 'paid' => 154500, 'approvals' => 0, 'messages' => 2, 'offset' => -88],
                    ['name' => 'Desert Ridge Entertaining Yard', 'city' => 'Mesa', 'state' => 'AZ', 'type' => 'Pool & Landscape', 'phase' => 'Lighting Layout', 'progress' => 43, 'health' => 'at_risk', 'contract' => 329500, 'paid' => 109000, 'approvals' => 4, 'messages' => 8, 'offset' => -39],
                    ['name' => 'Mummy Mountain Spa Terrace', 'city' => 'Paradise Valley', 'state' => 'AZ', 'type' => 'Spa Terrace', 'phase' => 'Steel & Plumbing Rough-In', 'progress' => 36, 'health' => 'on_track', 'contract' => 226000, 'paid' => 68500, 'approvals' => 2, 'messages' => 5, 'offset' => -28],
                    ['name' => 'Arcadia Pool House', 'city' => 'Phoenix', 'state' => 'AZ', 'type' => 'Pool House & Plunge Pool', 'phase' => 'Permitting Review', 'progress' => 18, 'health' => 'needs_client_decision', 'contract' => 515000, 'paid' => 77000, 'approvals' => 2, 'messages' => 3, 'offset' => -14],
                ],
            ],
            [
                'company' => [
                    'name' => 'Northline Custom Homes',
                    'slug' => 'northline-custom-homes',
                    'plan' => 'growth',
                    'subscription_status' => 'active',
                    'brand_primary_color' => '#0b1020',
                    'brand_accent_color' => '#c9a85a',
                    'market' => 'Custom homes and renovations',
                ],
                'owner' => ['name' => 'Elias Mercer', 'email' => 'owner@northline.test'],
                'managers' => [
                    ['name' => 'Rowan Price', 'email' => 'manager.rowan@northline.test', 'title' => 'Senior Project Lead'],
                    ['name' => 'Camille Stone', 'email' => 'manager.camille@northline.test', 'title' => 'Selections Coordinator'],
                ],
                'subs' => [
                    ['name' => 'Lena Morris', 'email' => 'millwork@northline.test', 'title' => 'Millwork Subcontractor', 'scope' => 'Cabinetry and custom millwork'],
                    ['name' => 'Troy Becker', 'email' => 'electrical@northline.test', 'title' => 'Electrical Subcontractor', 'scope' => 'Electrical rough-in and lighting'],
                    ['name' => 'Graham Ellis', 'email' => 'framing@northline.test', 'title' => 'Framing Subcontractor', 'scope' => 'Framing, sheathing, and field layout'],
                    ['name' => 'Iris Coleman', 'email' => 'hvac@northline.test', 'title' => 'HVAC Subcontractor', 'scope' => 'Mechanical rough-in and equipment trim'],
                    ['name' => 'Dante Hughes', 'email' => 'painting@northline.test', 'title' => 'Painting Subcontractor', 'scope' => 'Interior paint and finish touchups'],
                ],
                'projects' => [
                    ['name' => 'Briar Lane Estate', 'city' => 'Austin', 'state' => 'TX', 'type' => 'Custom Home', 'phase' => 'Framing Walkthrough', 'progress' => 47, 'health' => 'on_track', 'contract' => 1860000, 'paid' => 714000, 'approvals' => 2, 'messages' => 7, 'offset' => -95],
                    ['name' => 'Westlake Guest House', 'city' => 'West Lake Hills', 'state' => 'TX', 'type' => 'Guest House', 'phase' => 'Mechanical Rough-In', 'progress' => 55, 'health' => 'needs_client_decision', 'contract' => 685000, 'paid' => 311000, 'approvals' => 3, 'messages' => 6, 'offset' => -66],
                    ['name' => 'Tarrytown Kitchen Renovation', 'city' => 'Austin', 'state' => 'TX', 'type' => 'Interior Renovation', 'phase' => 'Cabinet Shop Drawings', 'progress' => 32, 'health' => 'at_risk', 'contract' => 238000, 'paid' => 72000, 'approvals' => 5, 'messages' => 9, 'offset' => -24],
                    ['name' => 'Hill Country Ranch House', 'city' => 'Dripping Springs', 'state' => 'TX', 'type' => 'Custom Home', 'phase' => 'Foundation Pour', 'progress' => 24, 'health' => 'on_track', 'contract' => 1425000, 'paid' => 290000, 'approvals' => 1, 'messages' => 3, 'offset' => -38],
                    ['name' => 'Lakeside Primary Suite', 'city' => 'Lakeway', 'state' => 'TX', 'type' => 'Luxury Remodel', 'phase' => 'Finish Selections', 'progress' => 68, 'health' => 'needs_client_decision', 'contract' => 415000, 'paid' => 246000, 'approvals' => 4, 'messages' => 5, 'offset' => -72],
                ],
            ],
            [
                'company' => [
                    'name' => 'Verdant Grounds Studio',
                    'slug' => 'verdant-grounds-studio',
                    'plan' => 'starter',
                    'subscription_status' => 'trial',
                    'brand_primary_color' => '#08130e',
                    'brand_accent_color' => '#bda15a',
                    'market' => 'Landscape design-build',
                ],
                'owner' => ['name' => 'Priya Nair', 'email' => 'owner@verdant.test'],
                'managers' => [
                    ['name' => 'Simon Vale', 'email' => 'manager.simon@verdant.test', 'title' => 'Operations Manager'],
                    ['name' => 'Jules Hart', 'email' => 'manager.jules@verdant.test', 'title' => 'Design Lead'],
                ],
                'subs' => [
                    ['name' => 'Ana Silva', 'email' => 'hardscape@verdant.test', 'title' => 'Hardscape Partner', 'scope' => 'Pavers and retaining walls'],
                    ['name' => 'Micah Chen', 'email' => 'irrigation@verdant.test', 'title' => 'Irrigation Partner', 'scope' => 'Irrigation and drainage'],
                    ['name' => 'Theo Bennett', 'email' => 'masonry@verdant.test', 'title' => 'Masonry Partner', 'scope' => 'Stone walls, planters, and masonry details'],
                    ['name' => 'Lucia Reyes', 'email' => 'lighting@verdant.test', 'title' => 'Lighting Partner', 'scope' => 'Low-voltage landscape lighting'],
                    ['name' => 'Owen Park', 'email' => 'planting@verdant.test', 'title' => 'Planting Partner', 'scope' => 'Planting, soil prep, and finish mulch'],
                ],
                'projects' => [
                    ['name' => 'Sonoma Garden Rooms', 'city' => 'Sonoma', 'state' => 'CA', 'type' => 'Landscape Renovation', 'phase' => 'Planting Layout', 'progress' => 64, 'health' => 'on_track', 'contract' => 188500, 'paid' => 110000, 'approvals' => 1, 'messages' => 5, 'offset' => -49],
                    ['name' => 'Healdsburg Vineyard Terrace', 'city' => 'Healdsburg', 'state' => 'CA', 'type' => 'Outdoor Living', 'phase' => 'Stone Terrace', 'progress' => 52, 'health' => 'needs_client_decision', 'contract' => 296000, 'paid' => 136500, 'approvals' => 3, 'messages' => 4, 'offset' => -56],
                    ['name' => 'Marin Hillside Garden', 'city' => 'Mill Valley', 'state' => 'CA', 'type' => 'Hillside Landscape', 'phase' => 'Drainage Rough-In', 'progress' => 38, 'health' => 'on_track', 'contract' => 241000, 'paid' => 83000, 'approvals' => 2, 'messages' => 2, 'offset' => -31],
                    ['name' => 'Napa Poolside Planting', 'city' => 'Napa', 'state' => 'CA', 'type' => 'Poolside Landscape', 'phase' => 'Lighting Placement', 'progress' => 79, 'health' => 'on_track', 'contract' => 129500, 'paid' => 104000, 'approvals' => 0, 'messages' => 1, 'offset' => -67],
                ],
            ],
        ];

        foreach ($tenants as $tenant) {
            $this->seedDemoTenant($tenant, $password);
        }
    }

    /**
     * @param  array<string, mixed>  $tenant
     */
    private function seedDemoTenant(array $tenant, string $password): void
    {
        $companyData = $tenant['company'];

        $company = Company::query()->create([
            'name' => $companyData['name'],
            'slug' => $companyData['slug'],
            'plan' => $companyData['plan'],
            'subscription_status' => $companyData['subscription_status'],
            'brand_primary_color' => $companyData['brand_primary_color'],
            'brand_accent_color' => $companyData['brand_accent_color'],
            'logo_path' => 'brand/project-vista-logo-500x500.jpg',
            'feature_flags' => $this->demoFeatureFlags($companyData['plan'] !== 'starter'),
            'settings' => [
                'market' => $companyData['market'],
                'portal_tone' => 'premium, clear, client-first',
            ],
        ]);
        $subcontractorTypes = $this->seedSubcontractorTypes($company);

        $owner = $this->createDemoUser($tenant['owner']['name'], $tenant['owner']['email'], $password);
        $company->users()->attach($owner->id, [
            'role' => Roles::COMPANY_ADMIN,
            'title' => 'Owner',
            'joined_at' => now(),
        ]);

        $managers = collect($tenant['managers'])->map(function (array $manager) use ($company, $password): User {
            $user = $this->createDemoUser($manager['name'], $manager['email'], $password);
            $company->users()->attach($user->id, [
                'role' => Roles::COMPANY_MANAGER,
                'title' => $manager['title'],
                'joined_at' => now()->subDays(random_int(24, 160)),
            ]);

            return $user;
        })->values();

        $subs = collect($tenant['subs'])->map(function (array $sub) use ($company, $password, $subcontractorTypes): array {
            $user = $this->createDemoUser($sub['name'], $sub['email'], $password);
            $type = $this->subcontractorTypeFor($subcontractorTypes, $sub['title'].' '.$sub['scope']);
            $company->users()->attach($user->id, [
                'role' => Roles::SUBCONTRACTOR,
                'title' => $sub['title'],
                'subcontractor_type_id' => $type->id,
                'joined_at' => now()->subDays(random_int(18, 120)),
            ]);

            return ['user' => $user, 'scope' => $sub['scope'], 'type' => $type];
        })->values();

        $timelineTemplate = TimelineTemplate::query()->create([
            'company_id' => $company->id,
            'name' => $companyData['market'].' Standard Timeline',
            'description' => 'Demo timeline used to populate role-specific ProjectVista dashboards.',
            'is_default' => true,
        ]);
        $this->seedDefaultTimelineTaskTemplates($timelineTemplate, $subcontractorTypes);

        $categories = collect([
            ['Materials', 'Material and finish decisions', 1],
            ['Layout', 'Plan, layout, and field coordination decisions', 2],
            ['Lighting', 'Lighting, automation, and ambiance decisions', 3],
            ['Landscape', 'Planting and exterior finish decisions', 4],
        ])->map(fn (array $row) => SelectionCategory::query()->create([
            'company_id' => $company->id,
            'name' => $row[0],
            'description' => $row[1],
            'sort_order' => $row[2],
        ]))->values();

        $approvalTemplate = ApprovalTemplate::query()->create([
            'company_id' => $company->id,
            'title' => 'Client Decision Approval',
            'description' => 'Client-facing approval for a project decision, selection, or document.',
            'default_due_days' => 5,
        ]);

        foreach ([
            ['deposit', 'Initial Deposit', 1],
            ['mobilization', 'Mobilization', 2],
            ['rough-in', 'Rough-In Complete', 3],
            ['finishes', 'Finish Stage', 4],
            ['final', 'Final Handoff', 5],
        ] as [$name, $label, $order]) {
            PaymentTemplate::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'label' => $label,
                'sort_order' => $order,
            ]);
        }

        foreach ($tenant['projects'] as $index => $projectData) {
            $manager = $managers[$index % $managers->count()];
            $sub = $subs[$index % $subs->count()];
            $client = $this->createDemoUser(
                $this->clientNameForProject($projectData['name']),
                'client.'.Str::slug($projectData['name'], '.').'@'.$companyData['slug'].'.test',
                $password,
            );

            $this->seedDemoProject(
                company: $company,
                manager: $manager,
                client: $client,
                subcontractor: $sub['user'],
                subcontractorScope: $sub['scope'],
                subcontractorType: $sub['type'],
                timelineTemplate: $timelineTemplate,
                categories: $categories,
                approvalTemplate: $approvalTemplate,
                projectData: $projectData,
                index: $index,
            );
        }

        foreach ([
            ['client-concierge@'.$companyData['slug'].'.test', Roles::COMPANY_MANAGER],
            ['new-homeowner@'.$companyData['slug'].'.test', Roles::CLIENT],
            ['trade-partner@'.$companyData['slug'].'.test', Roles::SUBCONTRACTOR],
        ] as [$email, $role]) {
            Invitation::query()->create([
                'company_id' => $company->id,
                'invited_by_id' => $owner->id,
                'email' => $email,
                'role' => $role,
                'subcontractor_type_id' => $role === Roles::SUBCONTRACTOR ? $subcontractorTypes->first()->id : null,
                'token' => Str::random(40),
                'status' => 'pending',
                'expires_at' => now()->addDays(10),
            ]);
        }
    }

    private function seedDemoProject(
        Company $company,
        User $manager,
        User $client,
        User $subcontractor,
        string $subcontractorScope,
        SubcontractorType $subcontractorType,
        TimelineTemplate $timelineTemplate,
        Collection $categories,
        ApprovalTemplate $approvalTemplate,
        array $projectData,
        int $index,
    ): void {
        $slug = Str::slug($projectData['name']);
        $contractAmount = (float) $projectData['contract'];
        $paidAmount = (float) $projectData['paid'];

        $project = Project::query()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
            'name' => $projectData['name'],
            'slug' => $slug,
            'address_line' => fake()->streetAddress(),
            'city' => $projectData['city'],
            'state' => $projectData['state'],
            'postal_code' => fake()->postcode(),
            'client_name' => str($projectData['name'])->before(' Residence')->append(' Client')->toString(),
            'client_email' => Str::slug($projectData['name']).'@example.test',
            'percent_complete' => $projectData['progress'],
            'health_status' => $projectData['health'],
            'contract_amount' => $contractAmount,
            'contract_signed_on' => now()->addDays($projectData['offset'] - 45),
            'hero_image_path' => 'demo/smith-residence-hero.png',
            'client_summary' => $projectData['type'].' managed through a polished client portal with decisions, progress, and documents in one place.',
            'latest_update' => $projectData['phase'].' is the current focus. The team added new notes, photos, and decision items for this week.',
            'next_step' => $projectData['approvals'] > 0
                ? 'Review the pending approval items so the next field window stays on schedule.'
                : 'The field team is continuing the next scheduled milestone.',
        ]);

        $project->users()->attach($manager->id, [
            'role' => Roles::COMPANY_MANAGER,
            'assigned_scope' => 'Lead project manager',
        ]);
        $project->users()->attach($client->id, [
            'role' => Roles::CLIENT,
            'assigned_scope' => 'Primary homeowner',
        ]);
        $project->users()->attach($subcontractor->id, [
            'role' => Roles::SUBCONTRACTOR,
            'assigned_scope' => $subcontractorScope,
            'permissions' => json_encode(['timeline', 'approved_selections', 'visible_documents']),
        ]);

        $phasePlan = [
            ['Contract Signed', 'Preconstruction', 'complete', -90, -90, false, true],
            ['Discovery & Scope', 'Preconstruction', 'complete', -82, -70, false, false],
            ['Design Confirmation', 'Preconstruction', 'complete', -69, -55, true, false],
            ['Permitting & Procurement', 'Preconstruction', $projectData['progress'] > 30 ? 'complete' : 'in_progress', -54, -31, false, false],
            ['Site Preparation', 'Construction', $projectData['progress'] > 45 ? 'complete' : 'in_progress', -30, -17, true, false],
            [$projectData['phase'], 'Construction', 'in_progress', -6, 8, true, false],
            ['Client Decision Review', 'Finishes', $projectData['approvals'] > 1 ? 'blocked' : 'upcoming', 9, 14, false, false],
            ['Finish Installation', 'Finishes', $projectData['progress'] > 75 ? 'in_progress' : 'upcoming', 18, 35, true, false],
            ['Final Walkthrough', 'Handoff', 'upcoming', 46, 50, false, false],
        ];

        foreach ($phasePlan as $order => [$title, $phase, $status, $startsOffset, $dueOffset, $subVisible, $isSystem]) {
            $scheduleOffset = (int) floor($projectData['offset'] / 8);
            $startsOn = $isSystem ? $project->contract_signed_on : now()->addDays($startsOffset + $scheduleOffset);
            $dueOn = $isSystem ? $project->contract_signed_on : now()->addDays($dueOffset + $scheduleOffset);

            TimelineTask::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'timeline_template_id' => $timelineTemplate->id,
                'assigned_subcontractor_id' => $subVisible ? $subcontractor->id : null,
                'subcontractor_type_id' => $subVisible ? $subcontractorType->id : null,
                'title' => $title,
                'description' => $title === 'Client Decision Review'
                    ? 'A client decision is needed before the team can release the next field window.'
                    : 'Demo timeline item for project visibility and role-specific work planning.',
                'sort_order' => $order + 1,
                'status' => $status,
                'starts_on' => $startsOn,
                'due_on' => $dueOn,
                'completed_on' => $status === 'complete' ? $dueOn : null,
                'internal_only' => false,
                'is_system' => $isSystem,
                'requires_acknowledgement' => $status === 'blocked',
            ]);
        }

        $approvedSelection = Selection::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'selection_category_id' => $categories[$index % $categories->count()]->id,
            'approved_by' => $client->id,
            'name' => $projectData['phase'].' Approved Finish',
            'description' => 'Approved finish package visible to clients and assigned subcontractors.',
            'image_path' => 'demo/selection-tile.png',
            'status' => 'approved',
            'manager_note' => 'This selection has been released to the field team.',
            'client_response' => 'Approved for installation.',
            'due_on' => now()->subDays(8),
            'approved_at' => now()->subDays(7),
        ]);

        $pendingSelection = Selection::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'selection_category_id' => $categories[($index + 1) % $categories->count()]->id,
            'name' => $projectData['phase'].' Decision Package',
            'description' => 'Client decision package for the next visible milestone.',
            'image_path' => 'demo/selection-decking.png',
            'status' => $projectData['approvals'] > 0 ? 'waiting_client' : 'manager_review',
            'manager_note' => 'Prepared for homeowner review with current field context.',
            'due_on' => now()->addDays(4 + $index),
        ]);

        Approval::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'approval_template_id' => $approvalTemplate->id,
            'selection_id' => $approvedSelection->id,
            'requested_by_id' => $manager->id,
            'responded_by_id' => $client->id,
            'title' => $approvedSelection->name,
            'body' => 'Approved selection retained for the field record.',
            'status' => 'approved',
            'due_on' => now()->subDays(8),
            'responded_at' => now()->subDays(7),
            'response_note' => 'Looks good.',
        ]);

        for ($approvalIndex = 1; $approvalIndex <= $projectData['approvals']; $approvalIndex++) {
            Approval::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'approval_template_id' => $approvalTemplate->id,
                'selection_id' => $pendingSelection->id,
                'requested_by_id' => $manager->id,
                'title' => $approvalIndex === 1 ? $pendingSelection->name : $projectData['phase'].' Field Clarification '.$approvalIndex,
                'body' => 'Please review this decision item so the project team can keep the current sequence moving.',
                'status' => $approvalIndex % 3 === 0 ? 'manager_review' : 'pending',
                'due_on' => now()->addDays(3 + $approvalIndex + $index),
            ]);
        }

        foreach ($this->paymentMilestonesFor($contractAmount, $paidAmount, $slug) as $milestone) {
            PaymentMilestone::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                ...$milestone,
            ]);
        }

        foreach ([
            ['Client Agreement', 'Contracts', 'demo/construction-agreement.pdf', true, false, 'client'],
            [$projectData['phase'].' Field Packet', 'Operations', 'demo/tile-layout.pdf', false, true, 'shared'],
            ['Selections Summary', 'Selections', 'demo/equipment-manual.pdf', true, false, 'client'],
            ['Internal Schedule Notes', 'Operations', 'demo/internal-sub-schedule.pdf', false, false, 'internal'],
        ] as [$title, $category, $path, $clientVisible, $subVisible, $visibility]) {
            ProjectDocument::query()->create([
                'company_id' => $company->id,
                'project_id' => $project->id,
                'uploaded_by_id' => $manager->id,
                'title' => $title,
                'category' => $category,
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => 112000 + ($index * 6000),
                'version' => $visibility === 'client' ? 2 : 1,
                'visibility' => $visibility,
                'client_visible' => $clientVisible,
                'subcontractor_visible' => $subVisible,
                'internal_notes' => $visibility === 'internal' ? 'Internal planning notes, hidden from client portal.' : null,
            ]);
        }

        foreach ([
            ['progress', 'demo/smith-progress-1.png', $projectData['phase'].' progress photo'],
            ['selection', 'demo/selection-tile.png', $approvedSelection->name],
            ['selection', 'demo/selection-decking.png', $pendingSelection->name],
            ['progress', 'demo/smith-residence-hero.png', $projectData['name'].' site overview'],
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
            'subject' => $projectData['phase'].' update',
            'status' => $projectData['health'] === 'at_risk' ? 'open' : 'open',
            'last_message_at' => now()->subHours(2),
        ]);

        $messages = [
            [$manager->id, 'We posted the latest update and highlighted the decision items that affect the next field window.', -18],
            [$client->id, 'Thanks for keeping this organized. We are reviewing the decision package this evening.', -12],
            [$manager->id, 'Perfect. Once that is approved, we will confirm the next crew date in the portal.', -7],
        ];

        for ($messageIndex = 1; $messageIndex <= $projectData['messages']; $messageIndex++) {
            $messages[] = [
                $messageIndex % 2 === 0 ? $manager->id : $client->id,
                $messageIndex % 2 === 0
                    ? 'We added a field note and updated the schedule card.'
                    : 'Could you confirm whether this affects our target completion week?',
                -6 + $messageIndex,
            ];
        }

        foreach ($messages as [$userId, $body, $hours]) {
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
    }

    private function createDemoUser(string $name, string $email, string $password): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function demoFeatureFlags(bool $premium): array
    {
        return [
            'media_messaging' => true,
            'payments_visible' => true,
            'document_uploads' => true,
            'subcontractor_access' => true,
            'branded_portal' => true,
            'ai_update_writer' => false,
            'custom_domain' => $premium,
        ];
    }

    private function seedDefaultTimelineTaskTemplates(TimelineTemplate $timelineTemplate, Collection $subcontractorTypes): void
    {
        foreach ([
            ['Contract Signed', 1, null],
            ['Permit Received', 1, null],
            ['Pre-Construction', 1, null],
            ['Layout', 1, 'pool-construction'],
            ['Excavation', 2, 'excavation'],
            ['Plumbing', 3, 'plumbing'],
            ['Electric', 2, 'electrical'],
            ['Steel', 3, null],
            ['Shotcrete', 1, null],
            ['Tile', 5, 'tile-stone'],
            ['Hardscape', 7, 'hardscape'],
            ['Interior', 2, 'pool-construction'],
            ['Startup', 2, null],
        ] as $index => [$name, $duration, $typeSlug]) {
            $type = $typeSlug === null ? null : $subcontractorTypes->get($typeSlug);

            TimelineTaskTemplate::query()->create([
                'company_id' => $timelineTemplate->company_id,
                'timeline_template_id' => $timelineTemplate->id,
                'default_subcontractor_type_id' => $type?->id,
                'name' => $name,
                'description' => 'Default ProjectVista pool construction milestone.',
                'sequence_order' => $index + 1,
                'default_duration_working_days' => $duration,
                'internal_only' => false,
                'is_system' => $index === 0,
            ]);
        }
    }

    private function seedTimelineConflictExamples(
        Company $company,
        Project $smithProject,
        Collection $omniSubcontractors,
        Collection $subcontractorTypes,
    ): void {
        $tileAssignment = $omniSubcontractors->get('tile-stone');
        $deckAssignment = $omniSubcontractors->get('decking');
        $landscapeAssignment = $omniSubcontractors->get('landscaping');
        $millerProject = Project::query()
            ->where('company_id', $company->id)
            ->where('slug', 'miller-residence')
            ->first();
        $williamsProject = Project::query()
            ->where('company_id', $company->id)
            ->where('slug', 'williams-residence')
            ->first();
        $tileConflictDate = now()->month(5)->day(29);
        $tileConflictDate = $tileConflictDate->isPast() ? $tileConflictDate->addYear() : $tileConflictDate;
        $tradeConflictDate = $tileConflictDate->copy()->addDay();

        if ($tileAssignment !== null) {
            $this->attachProjectSubcontractor($smithProject, $tileAssignment);
            $this->pinTaskConflictDate(
                $smithProject,
                'Tile Installation',
                $tileConflictDate->toDateString(),
                $tileConflictDate->toDateString(),
                $tileAssignment['user']->id,
                $tileAssignment['type']->id,
            );

            if ($millerProject !== null) {
                $this->attachProjectSubcontractor($millerProject, $tileAssignment);
                $this->pinTaskConflictDate(
                    $millerProject,
                    'Tile Installation',
                    $tileConflictDate->toDateString(),
                    $tileConflictDate->copy()->addDays(2)->toDateString(),
                    $tileAssignment['user']->id,
                    $tileAssignment['type']->id,
                );
            }
        }

        if ($williamsProject !== null && $deckAssignment !== null && $landscapeAssignment !== null) {
            $this->attachProjectSubcontractor($williamsProject, $deckAssignment);
            $this->attachProjectSubcontractor($williamsProject, $landscapeAssignment);
            $this->pinTaskConflictDate(
                $williamsProject,
                'Decking Layout',
                $tradeConflictDate->toDateString(),
                $tradeConflictDate->toDateString(),
                $deckAssignment['user']->id,
                $deckAssignment['type']->id,
            );

            TimelineTask::query()->updateOrCreate(
                [
                    'project_id' => $williamsProject->id,
                    'title' => 'Landscape Layout',
                ],
                [
                    'company_id' => $company->id,
                    'timeline_template_id' => $williamsProject->timelineTasks()->first()?->timeline_template_id,
                    'assigned_subcontractor_id' => $landscapeAssignment['user']->id,
                    'subcontractor_type_id' => $subcontractorTypes->get('landscaping')->id,
                    'description' => 'Demo same-day trade overlap for conflict detection.',
                    'sort_order' => 5,
                    'sequence_order' => 5,
                    'status' => 'upcoming',
                    'starts_on' => $tradeConflictDate->toDateString(),
                    'due_on' => $tradeConflictDate->toDateString(),
                    'internal_only' => false,
                    'requires_acknowledgement' => false,
                ],
            );
        }
    }

    private function pinTaskConflictDate(
        Project $project,
        string $title,
        string $startsOn,
        string $dueOn,
        int $subcontractorId,
        int $subcontractorTypeId,
    ): void {
        $task = TimelineTask::query()
            ->where('project_id', $project->id)
            ->where('title', $title)
            ->first();

        $task?->update([
            'assigned_subcontractor_id' => $subcontractorId,
            'subcontractor_type_id' => $subcontractorTypeId,
            'starts_on' => $startsOn,
            'due_on' => $dueOn,
            'status' => 'upcoming',
            'internal_only' => false,
        ]);
    }

    private function seedSubcontractorTypes(Company $company): Collection
    {
        return collect([
            'Tile & Stone',
            'Plumbing',
            'Decking',
            'Electrical',
            'Pool Construction',
            'Landscaping',
            'Hardscape',
            'Irrigation',
            'Outdoor Kitchen',
            'Automation',
            'Concrete',
            'Excavation',
            'Masonry',
            'Framing',
            'Millwork',
            'HVAC',
            'Painting',
            'Roofing',
        ])->mapWithKeys(fn (string $name, int $index): array => [
            Str::slug($name) => SubcontractorType::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'sort_order' => $index + 1,
                'is_active' => true,
            ]),
        ]);
    }

    private function subcontractorTypeFor(Collection $types, string $label): SubcontractorType
    {
        $normalized = Str::lower($label);

        return match (true) {
            str_contains($normalized, 'tile'), str_contains($normalized, 'stone') => $types->get('tile-stone'),
            str_contains($normalized, 'plumb') => $types->get('plumbing'),
            str_contains($normalized, 'deck') => $types->get('decking'),
            str_contains($normalized, 'outdoor kitchen'), str_contains($normalized, 'appliance'), str_contains($normalized, 'counter') => $types->get('outdoor-kitchen'),
            str_contains($normalized, 'automation'), str_contains($normalized, 'control'), str_contains($normalized, 'smart') => $types->get('automation'),
            str_contains($normalized, 'concrete'), str_contains($normalized, 'foundation'), str_contains($normalized, 'pour') => $types->get('concrete'),
            str_contains($normalized, 'excavat'), str_contains($normalized, 'shell'), str_contains($normalized, 'gunite') => $types->get('excavation'),
            str_contains($normalized, 'masonry'), str_contains($normalized, 'retaining wall'), str_contains($normalized, 'planter') => $types->get('masonry'),
            str_contains($normalized, 'framing'), str_contains($normalized, 'sheathing') => $types->get('framing'),
            str_contains($normalized, 'millwork'), str_contains($normalized, 'cabinet') => $types->get('millwork'),
            str_contains($normalized, 'hvac'), str_contains($normalized, 'mechanical') => $types->get('hvac'),
            str_contains($normalized, 'paint') => $types->get('painting'),
            str_contains($normalized, 'roof') => $types->get('roofing'),
            str_contains($normalized, 'electric'), str_contains($normalized, 'lighting') => $types->get('electrical'),
            str_contains($normalized, 'landscape'), str_contains($normalized, 'planting') => $types->get('landscaping'),
            str_contains($normalized, 'hardscape'), str_contains($normalized, 'paver') => $types->get('hardscape'),
            str_contains($normalized, 'irrigation'), str_contains($normalized, 'drainage') => $types->get('irrigation'),
            default => $types->get('pool-construction'),
        };
    }

    /**
     * @param  Collection<string, array{user: User, title: string, scope: string, type: SubcontractorType}>  $subcontractors
     * @return array{user: User, title: string, scope: string, type: SubcontractorType}|null
     */
    private function omniSubcontractorForTask(Collection $subcontractors, string $label): ?array
    {
        $normalized = Str::lower($label);

        $typeSlug = match (true) {
            str_contains($normalized, 'plumb') => 'plumbing',
            str_contains($normalized, 'deck') => 'decking',
            str_contains($normalized, 'electric'), str_contains($normalized, 'lighting') => 'electrical',
            str_contains($normalized, 'landscape'), str_contains($normalized, 'planting') => 'landscaping',
            str_contains($normalized, 'hardscape'), str_contains($normalized, 'paver') => 'hardscape',
            str_contains($normalized, 'irrigation'), str_contains($normalized, 'drainage') => 'irrigation',
            str_contains($normalized, 'kitchen'), str_contains($normalized, 'appliance'), str_contains($normalized, 'counter') => 'outdoor-kitchen',
            str_contains($normalized, 'automation'), str_contains($normalized, 'control'), str_contains($normalized, 'orientation') => 'automation',
            str_contains($normalized, 'excavat'), str_contains($normalized, 'gunite'), str_contains($normalized, 'shell'), str_contains($normalized, 'startup'), str_contains($normalized, 'balance'), str_contains($normalized, 'water fill') => 'pool-construction',
            default => 'tile-stone',
        };

        return $subcontractors->get($typeSlug) ?? $subcontractors->get('tile-stone');
    }

    /**
     * @param  array{user: User, title: string, scope: string, type: SubcontractorType}  $assignment
     */
    private function attachProjectSubcontractor(Project $project, array $assignment): void
    {
        $alreadyAssigned = $project->users()
            ->whereKey($assignment['user']->id)
            ->wherePivot('role', Roles::SUBCONTRACTOR)
            ->exists();

        if ($alreadyAssigned) {
            return;
        }

        $project->users()->attach($assignment['user']->id, [
            'role' => Roles::SUBCONTRACTOR,
            'assigned_scope' => $assignment['scope'],
            'permissions' => json_encode(['timeline', 'approved_selections', 'visible_documents']),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentMilestonesFor(float $contractAmount, float $paidAmount, string $slug): array
    {
        $deposit = round($contractAmount * 0.12);
        $secondPaid = max(0, $paidAmount - $deposit);
        $dueAmount = max(0, round($contractAmount * 0.24));
        $scheduledAmount = max(0, $contractAmount - $deposit - $secondPaid - $dueAmount);

        return [
            [
                'label' => 'Initial Deposit',
                'description' => 'Paid before design, procurement, and kickoff.',
                'amount' => $deposit,
                'status' => 'paid',
                'due_on' => now()->subDays(72),
                'completed_on' => now()->subDays(70),
                'payment_url' => null,
                'provider_label' => null,
                'client_visible' => true,
                'sort_order' => 1,
            ],
            [
                'label' => 'Progress Billing',
                'description' => 'Progress payment after major field milestone.',
                'amount' => $secondPaid,
                'status' => $secondPaid > 0 ? 'paid' : 'scheduled',
                'due_on' => now()->subDays(24),
                'completed_on' => $secondPaid > 0 ? now()->subDays(22) : null,
                'payment_url' => null,
                'provider_label' => null,
                'client_visible' => true,
                'sort_order' => 2,
            ],
            [
                'label' => 'Current Milestone',
                'description' => 'External payment link for the current project stage.',
                'amount' => $dueAmount,
                'status' => 'due',
                'due_on' => now()->addDays(5),
                'completed_on' => null,
                'payment_url' => 'https://pay.example.test/projectvista/'.$slug,
                'provider_label' => 'External payment link',
                'client_visible' => true,
                'sort_order' => 3,
            ],
            [
                'label' => 'Final Handoff',
                'description' => 'Due before final orientation and closeout packet.',
                'amount' => $scheduledAmount,
                'status' => 'scheduled',
                'due_on' => now()->addDays(42),
                'completed_on' => null,
                'payment_url' => null,
                'provider_label' => null,
                'client_visible' => true,
                'sort_order' => 4,
            ],
        ];
    }

    private function clientNameForProject(string $projectName): string
    {
        $lastName = str($projectName)
            ->beforeLast(' ')
            ->explode(' ')
            ->filter()
            ->last() ?: 'Client';

        return fake()->firstName().' '.$lastName;
    }
}
