<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\ProvisionDemoTenantJob;
use App\Mail\CommunicationMail;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2253 — ProvisionDemoTenantJob : le sandbox provisionné doit
 * émettre un magic link d'accès (hash SHA-256 + expiration dans
 * extra_data) et envoyer l'email au prospect.
 */
class ProvisionDemoTenantJobTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_job_issues_magic_link_and_sends_email(): void
    {
        Mail::fake();

        $email = 'prospect-'.uniqid().'@example.com';

        /** @var ProvisionGuidedTrial $provisioner */
        $provisioner = app(ProvisionGuidedTrial::class);

        $job = new ProvisionDemoTenantJob($email, 'Sandbox Test '.uniqid(), 'DZ');
        $job->handle($provisioner);

        // Le manager sandbox porte le hash du jeton + l'expiration.
        /** @var Employee $manager */
        $manager = Employee::query()
            ->where('email', $email)
            ->firstOrFail();

        $this->assertIsString($manager->extra_data['demo_access_token_hash'] ?? null);
        $this->assertSame(64, strlen((string) $manager->extra_data['demo_access_token_hash']));
        $this->assertArrayHasKey('demo_access_token_expires_at', $manager->extra_data);

        // L'email contient le lien magic /demo-login/{token}.
        Mail::assertSent(CommunicationMail::class, function (CommunicationMail $mail) use ($email): bool {
            return $mail->hasTo($email)
                && str_contains($mail->bodyText, '/demo-login/');
        });
    }
}
