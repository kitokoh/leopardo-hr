<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Auth\Domain\Models\Employee;
use App\Mail\CommunicationMail;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProvisionDemoTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $companyName,
        public readonly ?string $country = null,
        public readonly ?string $provisioningToken = null,
    ) {}

    // Issue #3600 : provisioning trial = opération lourde et critique — retries
    // espacés (30s, 1min, 2min, 5min) au lieu d'une rafale par défaut, et
    // échec final visible via failed().
    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(ProvisionGuidedTrial $provisioner): void
    {
        Log::info('ProvisionDemoTenantJob started', ['company_name' => $this->companyName, 'email' => $this->email, 'country' => $this->country]);

        try {
            $result = $provisioner->execute($this->email, $this->companyName, $this->country);

            // #2437 : le statut du provisioning est persisté pour que le
            // prospect puisse poller GET /trial/status (login_url = le portail
            // client ; le magic link email est un bonus, jamais le seul canal).
            if ($this->provisioningToken !== null) {
                DB::table('trial_provisionings')
                    ->where('provisioning_token', $this->provisioningToken)
                    ->update([
                        'status' => 'ready',
                        'company_id' => $result['company']->id,
                        'login_url' => '/auth/login',
                        'access_sent_at' => now(),
                        'provisioned_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // Issue #2620 : magic link envoyé au manager après provisioning.
            // QA #3002 : un SEUL appel — un 2e appel révoquerait le hash du 1er
            // token (2 emails, 1 lien mort).
            $this->issueDemoAccess($result['manager']);

            Log::info('Sandbox provisioned successfully', ['company_id' => $result['company']->id]);

        } catch (\Throwable $e) {
            Log::error('Failed to provision sandbox', ['error' => $e->getMessage()]);

            // Issue #3600 : rethrow — le job doit être re-tenté par la queue
            // (tries=5, backoff 30/60/120/300) au lieu de « réussir » en
            // silence. Le statut reste `pending` pendant les retries ; la
            // méthode `failed()` le passe à `failed` après le dernier essai.
            throw $e;
        }
    }

    /**
     * Issue #3600 : épuisement des retries — alerte visible en logs
     * (les jobs « réussis » en silence étaient invisibles).
     */
    public function failed(\Throwable $e): void
    {
        Log::error('ProvisionDemoTenantJob failed permanently after retries', [
            'email' => $this->email,
            'company_name' => $this->companyName,
            'error' => $e->getMessage(),
        ]);

        if ($this->provisioningToken !== null) {
            DB::table('trial_provisionings')
                ->where('provisioning_token', $this->provisioningToken)
                ->update([
                    'status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 500),
                    'updated_at' => now(),
                ]);
        }
    }

    private function issueDemoAccess(Employee $manager): void
    {
        $token = Str::random(48);
        $expiresAt = now()->addHours(72);

        $extraData = $manager->extra_data ?? [];
        $extraData['demo_access_token_hash'] = hash('sha256', $token);
        $extraData['demo_access_token_expires_at'] = $expiresAt->toIso8601String();
        $manager->update(['extra_data' => $extraData]);

        $magicUrl = rtrim((string) config('app.url'), '/').'/demo-login/'.$token;

        // Best-effort : un échec d'envoi (mailer non configuré) ne doit pas
        // faire échouer le provisioning — le lien est loggé pour support.
        try {
            Mail::to($this->email)->send(new CommunicationMail(
                subjectLine: __('emails.demo_access_subject'),
                bodyText: __('emails.demo_access_body', ['url' => $magicUrl]),
            ));
        } catch (\Throwable $exception) {
            Log::warning('Demo access email could not be sent', [
                'email' => $this->email,
                'error' => $exception->getMessage(),
            ]);
        }

        Log::info('Demo magic link issued', [
            'company_id' => $manager->company_id,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }
}
