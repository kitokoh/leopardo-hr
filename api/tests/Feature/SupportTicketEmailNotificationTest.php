<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Mail\SupportTicketOpenedMail;
use App\Mail\SupportTicketPlatformReplyMail;
use App\Mail\SupportTicketTenantReplyMail;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7760 — email notifications on the client ↔ platform support ticket
 * conversation: a new ticket notifies the platform `support.manage` team, a
 * platform reply notifies the ticket author, and a tenant reply notifies the
 * assigned super-admin (falling back to the team). No recipient from another
 * tenant may ever receive anything.
 */
class SupportTicketEmailNotificationTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function createSuperAdmin(string $email, string $platformRole = 'super_admin', string $status = 'active'): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform '.$platformRole,
            'email' => $email,
        ]);
        $superAdmin->forceFill([
            'password_hash' => Hash::make('password123'),
            'platform_role' => $platformRole,
            'status' => $status,
        ])->save();

        return $superAdmin;
    }

    public function test_new_ticket_notifies_every_support_manage_super_admin_and_nobody_else(): void
    {
        Mail::fake();

        // `super_admin` and `support` carry `support.manage`; `marketing`
        // does not, and an inactive support account must stay silent.
        $owner = $this->createSuperAdmin('owner@leopardo.test');
        $support = $this->createSuperAdmin('support@leopardo.test', 'support');
        $marketing = $this->createSuperAdmin('marketing@leopardo.test', 'marketing');
        $suspended = $this->createSuperAdmin('suspended@leopardo.test', 'support', 'suspended');

        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Payroll export fails',
            'category' => 'technical',
            'priority' => 'high',
            'message' => 'The payroll export button returns a 500 error since this morning.',
        ])->assertCreated();

        Mail::assertQueued(
            SupportTicketOpenedMail::class,
            fn (SupportTicketOpenedMail $mail): bool => $mail->hasTo($owner->email)
                && $mail->ticket->subject === 'Payroll export fails',
        );
        Mail::assertQueued(
            SupportTicketOpenedMail::class,
            fn (SupportTicketOpenedMail $mail): bool => $mail->hasTo($support->email),
        );
        Mail::assertNotQueued(
            SupportTicketOpenedMail::class,
            fn (SupportTicketOpenedMail $mail): bool => $mail->hasTo($marketing->email),
        );
        Mail::assertNotQueued(
            SupportTicketOpenedMail::class,
            fn (SupportTicketOpenedMail $mail): bool => $mail->hasTo($suspended->email),
        );
        // The ticket subject line carries the ticket reference.
        Mail::assertQueued(SupportTicketOpenedMail::class, 2);
    }

    public function test_platform_reply_notifies_the_ticket_author_and_never_another_tenant(): void
    {
        $superAdmin = $this->createSuperAdmin('owner@leopardo.test');

        $company = Company::factory()->create();
        /** @var Employee $author */
        $author = Employee::factory()->manager()->create(['company_id' => $company->id]);

        // Employee of ANOTHER tenant: must never be notified about this
        // conversation.
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($author);
        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Billing question',
            'category' => 'billing',
            'message' => 'Why was I charged twice this month?',
        ])->assertCreated();

        /** @var PlatformSupportTicket $ticket */
        $ticket = PlatformSupportTicket::query()->firstOrFail();

        Mail::fake();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
        $this->postJson("/api/v1/platform/support-tickets/{$ticket->id}/reply", [
            'message' => 'We are looking into the duplicate charge, refund incoming.',
        ])->assertOk();

        Mail::assertQueued(
            SupportTicketPlatformReplyMail::class,
            fn (SupportTicketPlatformReplyMail $mail): bool => $mail->hasTo($author->email)
                && $mail->recipient->is($author),
        );
        Mail::assertQueued(SupportTicketPlatformReplyMail::class, 1);
        Mail::assertNotQueued(
            SupportTicketPlatformReplyMail::class,
            fn (SupportTicketPlatformReplyMail $mail): bool => $mail->hasTo($otherEmployee->email),
        );
        // A platform reply never notifies the platform team itself.
        Mail::assertNotQueued(SupportTicketTenantReplyMail::class);
        Mail::assertNotQueued(SupportTicketOpenedMail::class);
    }

    public function test_employee_reply_notifies_the_assigned_super_admin_only(): void
    {
        $assigned = $this->createSuperAdmin('assigned@leopardo.test', 'support');
        $other = $this->createSuperAdmin('owner@leopardo.test');

        $company = Company::factory()->create();
        /** @var Employee $author */
        $author = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($author);
        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Onboarding help',
            'category' => 'onboarding',
            'message' => 'How do I import my employees?',
        ])->assertCreated();

        /** @var PlatformSupportTicket $ticket */
        $ticket = PlatformSupportTicket::query()->firstOrFail();
        $ticket->forceFill(['assigned_super_admin_id' => $assigned->id])->save();

        Mail::fake();

        $this->postJson("/api/v1/support-tickets/{$ticket->id}/reply", [
            'message' => 'I attached the CSV in my first message.',
        ])->assertOk();

        Mail::assertQueued(
            SupportTicketTenantReplyMail::class,
            fn (SupportTicketTenantReplyMail $mail): bool => $mail->hasTo($assigned->email),
        );
        Mail::assertQueued(SupportTicketTenantReplyMail::class, 1);
        Mail::assertNotQueued(
            SupportTicketTenantReplyMail::class,
            fn (SupportTicketTenantReplyMail $mail): bool => $mail->hasTo($other->email),
        );
    }

    public function test_employee_reply_falls_back_to_the_support_manage_team_when_unassigned(): void
    {
        $owner = $this->createSuperAdmin('owner@leopardo.test');
        $support = $this->createSuperAdmin('support@leopardo.test', 'support');
        $marketing = $this->createSuperAdmin('marketing@leopardo.test', 'marketing');

        $company = Company::factory()->create();
        /** @var Employee $author */
        $author = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($author);
        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'General question',
            'category' => 'general',
            'message' => 'Where can I find my invoices?',
        ])->assertCreated();

        /** @var PlatformSupportTicket $ticket */
        $ticket = PlatformSupportTicket::query()->firstOrFail();

        Mail::fake();

        $this->postJson("/api/v1/support-tickets/{$ticket->id}/reply", [
            'message' => 'Any update on this?',
        ])->assertOk();

        Mail::assertQueued(
            SupportTicketTenantReplyMail::class,
            fn (SupportTicketTenantReplyMail $mail): bool => $mail->hasTo($owner->email),
        );
        Mail::assertQueued(
            SupportTicketTenantReplyMail::class,
            fn (SupportTicketTenantReplyMail $mail): bool => $mail->hasTo($support->email),
        );
        Mail::assertNotQueued(
            SupportTicketTenantReplyMail::class,
            fn (SupportTicketTenantReplyMail $mail): bool => $mail->hasTo($marketing->email),
        );
        Mail::assertQueued(SupportTicketTenantReplyMail::class, 2);
    }

    public function test_notification_emails_carry_the_ticket_reference_in_the_subject(): void
    {
        $this->createSuperAdmin('owner@leopardo.test');

        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        Mail::fake();

        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Payroll export fails',
            'category' => 'technical',
            'message' => 'The payroll export button returns a 500 error.',
        ])->assertCreated();

        /** @var PlatformSupportTicket $ticket */
        $ticket = PlatformSupportTicket::query()->firstOrFail();

        Mail::assertQueued(SupportTicketOpenedMail::class, function (SupportTicketOpenedMail $mail) use ($ticket): bool {
            $built = $mail->build();
            $subject = (string) $built->subject;

            return str_contains($subject, "#{$ticket->id}")
                && str_contains($subject, 'Payroll export fails');
        });
    }
}
