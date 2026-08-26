<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\OnboardingReminderMail;
use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * #R12 — Rappel d'onboarding J+1.
 *
 * Envoie un email de relance aux managers principaux dont :
 *   1. La société a été créée il y a 20h–28h (fenêtre J+1).
 *   2. L'onboarding comporte encore au moins une étape requise non complétée.
 *
 * La fenêtre de 20h–28h est intentionnellement large pour absorber la
 * variation des planifications du scheduler (toutes les 8 heures au minimum).
 * L'idempotence est garantie par la fenêtre temporelle : un tenant ne peut
 * entrer dans la fenêtre qu'une seule fois.
 *
 * Planification recommandée : quotidien à 09:00 UTC
 * (`$schedule->command('onboarding:send-reminders')->dailyAt('09:00')`)
 */
final class SendOnboardingRemindersCommand extends Command
{
    protected $signature = 'onboarding:send-reminders
                            {--dry-run : Affiche les managers concernés sans envoyer d\'email}';

    protected $description = 'Envoie les rappels d\'onboarding J+1 aux managers dont la configuration est incomplète.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $windowStart = now()->subHours(28);
        $windowEnd   = now()->subHours(20);

        // Sociétés créées dans la fenêtre J+1.
        $companies = Company::query()
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->get();

        $sent = 0;

        foreach ($companies as $company) {
            // Vérifier qu'il reste des étapes requises non complétées.
            $hasPendingRequired = OnboardingStep::query()
                ->where('company_id', $company->id)
                ->where('required', true)
                ->where('status', '!=', 'completed')
                ->exists();

            if (! $hasPendingRequired) {
                continue;
            }

            // Trouver le manager principal de la société.
            /** @var Employee|null $manager */
            $manager = Employee::query()
                ->where('company_id', $company->id)
                ->where('role', 'manager')
                ->where('manager_role', 'principal')
                ->where('status', 'active')
                ->first();

            if ($manager === null || $manager->email === null) {
                continue;
            }

            $managerName = trim(($manager->first_name ?? '') . ' ' . ($manager->last_name ?? ''))
                ?: $manager->email;

            if ($isDryRun) {
                $this->line("[dry-run] Would send reminder to {$manager->email} (company: {$company->name})");
                $sent++;
                continue;
            }

            try {
                Mail::to($manager->email, $managerName)
                    ->queue(new OnboardingReminderMail($company, $managerName, $manager->email));
                $sent++;
                $this->line("Queued reminder for {$manager->email} (company: {$company->name})");
            } catch (\Throwable $e) {
                $this->error("Failed to queue reminder for {$manager->email}: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$sent} reminder(s) " . ($isDryRun ? 'would be' : '') . ' sent.');

        return self::SUCCESS;
    }
}
