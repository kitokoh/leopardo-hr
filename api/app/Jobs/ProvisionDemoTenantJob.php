<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
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
                        'provisioned_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // TODO: Generate magic link and send email to the user
            Log::info('Sandbox provisioned successfully', ['company_id' => $result['company']->id]);

        } catch (\Throwable $e) {
            Log::error('Failed to provision sandbox', ['error' => $e->getMessage()]);

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
    }

    /**
     * @param  \App\Core\Auth\Domain\Models\Employee  $manager
     */
    private function issueDemoAccess(\App\Core\Auth\Domain\Models\Employee $manager): void
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
