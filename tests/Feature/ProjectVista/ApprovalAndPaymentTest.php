<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectVista;

use App\Models\Approval;
use App\Models\PaymentMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApprovalAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_approve_pending_approval_and_related_selection(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $approval = Approval::query()->where('status', 'pending')->firstOrFail();

        $this->actingAs($client)
            ->patch(route('approvals.response', $approval), [
                'status' => 'approved',
                'response_note' => 'Approved for install.',
            ])
            ->assertRedirect();

        $approval->refresh();

        $this->assertSame('approved', $approval->status);
        $this->assertSame('Approved for install.', $approval->response_note);
        $this->assertSame('approved', $approval->selection->refresh()->status);
    }

    public function test_manager_can_mark_external_payment_milestone_paid(): void
    {
        $this->seed();

        $manager = User::query()->where('email', 'manager@omnipools.test')->firstOrFail();
        $payment = PaymentMilestone::query()->where('status', 'due')->firstOrFail();

        $this->actingAs($manager)
            ->patch(route('payment-milestones.complete', $payment))
            ->assertRedirect();

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertNotNull($payment->completed_on);
    }

    public function test_client_cannot_mark_payment_paid(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@omnipools.test')->firstOrFail();
        $payment = PaymentMilestone::query()->where('status', 'due')->firstOrFail();

        $this->actingAs($client)
            ->patch(route('payment-milestones.complete', $payment))
            ->assertForbidden();
    }
}
