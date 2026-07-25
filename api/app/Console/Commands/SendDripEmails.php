<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\TrialDripMail;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\NotificationPreferenceProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * PA2-ONB-005 — Trial drip campaign (day3 / expiring / expired).
 *
 * Runs daily (see routes/console.php) across every trial company. Unlike
 * SendTrialDripEmailJob (day 1/3/7, queued once at signup with a delay),
 * this command re-evaluates every trial company on every run against
 * created_at/subscription_end, so it also catches trials whose delayed job
 * never ran (deploy restart, queue outage, subscription_end changed by a
 * manual extension, ...).
 *
 * Each dispatch:
 *  - Switches to the company's tenant DB context (search_path) via
 *    TenantManager, mirroring what EnsureTenantContext does for queued
 *    jobs, so the real principal manager is resolved instead of a
 *    placeholder — this matters once a trial company runs on an isolated
 *    "schema" tenancy (Enterprise), not only the shared default schema.
 *  - Honours the manager's own `email_enabled` notification preference:
 *    an opted-out manager is skipped, never emailed (opt-out).
 *  - Always records a `communication_events` row (sent/skipped/failed),
 *    so the drip campaign is auditable like every other channel dispatch
 *    handled by CommunicationService.
 */
class SendDripEmails extends Command
{
    protected $signature = 'app:send-drip-emails';

    protected $description = 'Send drip campaign emails (day3/expiring/expired) to companies in trial, honouring email opt-out and logging every dispatch.';

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly NotificationPreferenceProvisioner $preferences,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companies = Company::query()
            ->where('status', 'trial')
            ->get();

        $today = now()->startOfDay();
        $sent = 0;
        $skipped = 0;

        foreach ($companies as $company) {
            $type = $this->dueType($company, $today);

            if ($type === null) {
                continue;
            }

            $this->tenantManager->withinTenant($company, function () use ($company, $type, &$sent, &$skipped): void {
                if ($this->sendDripEmail($company, $type)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            });
        }

        $this->info("Drip emails processed: {$sent} sent, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function dueType(Company $company, Carbon $today): ?string
    {
        if ($company->created_at === null) {
            return null;
        }

        $created = $company->created_at->copy()->startOfDay();
        $subscriptionEnd = $company->subscription_end->copy()->startOfDay();

        // Drip 1: Day 3 after signup.
        if ($today->isAfter($created) && (int) $created->diffInDays($today) === 3) {
            return 'day3';
        }

        // Drip 2: 3 days before trial expiration.
        if ($subscriptionEnd->isAfter($today) && (int) $today->diffInDays($subscriptionEnd) === 3) {
            return 'expiring';
        }

        // Drip 3: expiration day.
        if ($today->isSameDay($subscriptionEnd)) {
            return 'expired';
        }

        return null;
    }

    private function sendDripEmail(Company $company, string $type): bool
    {
        $manager = Employee::query()
            ->where('company_id', $company->id)
            ->where('manager_role', 'principal')
            ->first();

        if (! $manager instanceof Employee) {
            $this->warn("No principal manager found for company {$company->id}, skipping {$type} drip email.");

            return false;
        }

        $preference = $this->preferences->ensureForEmployee($manager);

        if ($preference->email_enabled === false) {
            $this->recordEvent($company, $manager, $type, 'skipped', 'Recipient opted out of email notifications.');
            $this->info("Skipped {$type} drip email to {$manager->email} (opted out).");

            return false;
        }

        try {
            Mail::to($manager->email)->send(new TrialDripMail($company, $manager, $type));
        } catch (Throwable $exception) {
            $this->recordEvent($company, $manager, $type, 'failed', $exception->getMessage());
            $this->error("Failed to send {$type} drip email to {$manager->email}: {$exception->getMessage()}");

            return false;
        }

        $this->recordEvent($company, $manager, $type, 'sent');
        $this->info("Sent {$type} drip email to {$manager->email}.");

        return true;
    }

    private function recordEvent(Company $company, Employee $manager, string $type, string $status, ?string $errorMessage = null): void
    {
        CommunicationEvent::query()->create([
            'company_id' => (string) $company->id,
            'employee_id' => $manager->id,
            'event_name' => 'trial_drip_email',
            'channel' => 'email',
            'status' => $status,
            'provider' => 'mail',
            'template_key' => 'trial_drip_'.$type,
            'metadata' => ['type' => $type],
            'error_message' => $errorMessage,
            'occurred_at' => now(),
        ]);
    }
}
