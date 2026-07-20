<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Mail\TrialDayOneMail;
use App\Mail\TrialDaySevenMail;
use App\Mail\TrialDayThreeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job: send a drip email to a trial company at day 1, 3, or 7.
 *
 * Dispatch via:
 *   SendTrialDripEmailJob::dispatch($company, 1)->delay(now()->addDay());
 *   SendTrialDripEmailJob::dispatch($company, 3)->delay(now()->addDays(3));
 *   SendTrialDripEmailJob::dispatch($company, 7)->delay(now()->addDays(7));
 */
class SendTrialDripEmailJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    public function __construct(
        private readonly Company $company,
        private readonly int $dayNumber,
    ) {
        $this->onQueue('notifications');
    }

    public function tenantCompanyId(): ?string
    {
        return $this->company->id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(): void
    {
        if ($this->company->status !== 'trial') {
            Log::info('[DripEmail] Company no longer in trial, skipping.', [
                'company_id' => $this->company->id,
                'day' => $this->dayNumber,
            ]);

            return;
        }

        // Find the principal manager
        $manager = Employee::where('company_id', $this->company->id)
            ->where('manager_role', 'principal')
            ->first();

        if (! $manager) {
            Log::warning('[DripEmail] No principal manager found.', ['company_id' => $this->company->id]);

            return;
        }

        $name = trim($manager->first_name.' '.$manager->last_name);
        $email = $manager->email;
        $locale = $manager->preferred_language;

        $mailable = match ($this->dayNumber) {
            1 => new TrialDayOneMail($this->company, $name, $email, $locale),
            3 => new TrialDayThreeMail($this->company, $name, $locale),
            7 => new TrialDaySevenMail(
                $this->company,
                $name,
                Employee::where('company_id', $this->company->id)->count(),
                $locale,
            ),
            default => null,
        };

        if ($mailable === null) {
            Log::error('[DripEmail] Unknown day number.', ['day' => $this->dayNumber]);

            return;
        }

        Mail::to($email)->send($mailable);

        Log::info('[DripEmail] Sent.', [
            'company_id' => $this->company->id,
            'day' => $this->dayNumber,
            'to' => $email,
        ]);
    }
}
