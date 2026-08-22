<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchCommunicationJob;
use App\Jobs\ProvisionDemoTenantJob;
use App\Jobs\WarmPaySlipPdfPathsForPayrollRunJob;
use App\Mail\CommunicationMail;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    /**
     * #3600 : une erreur transitoire doit être RETHROWN (retry du worker) et
     * le statut final 'failed' posé par failed() après le dernier essai.
     */
    public function test_job_rethrows_on_failure_and_marks_provisioning_failed(): void
    {
        Mail::fake();

        $email = 'fail-'.uniqid().'@example.com';
        $token = 'token-'.Str::random(32);

        DB::table('trial_provisionings')->insert([
            'email' => $email,
            'provisioning_token' => $token,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $provisioner = $this->createMock(ProvisionGuidedTrial::class);
        $provisioner->method('execute')->willThrowException(new \RuntimeException('DB transient error'));

        $job = new ProvisionDemoTenantJob($email, 'Fail Sandbox', 'DZ', $token);

        // Le handle() doit propager l'exception (retry worker, pas d'avalement).
        try {
            $job->handle($provisioner);
            $this->fail('handle() should rethrow the transient error');
        } catch (\RuntimeException $e) {
            $this->assertSame('DB transient error', $e->getMessage());
        }

        // Comportement #3600 (état actuel du code, commit 2e887c301 « keep
        // trial pending during retries ») : le catch de handle() LOGUE et
        // rethrow SANS écrire en base — le statut reste `pending` pendant les
        // retries du worker (l'écriture `failed` n'a lieu que dans failed()).
        // Le test avait été aligné à tort sur un comportement « failed pendant
        // les retries » qui n'existe pas dans le code (issue #5201).
        $this->assertSame('pending', DB::table('trial_provisionings')->where('provisioning_token', $token)->value('status'));

        // failed() (dernier essai) pose le statut définitif.
        $job->failed(new \RuntimeException('DB transient error'));
        $this->assertSame('failed', DB::table('trial_provisionings')->where('provisioning_token', $token)->value('status'));
    }

    /**
     * #3600 : tries/backoff bornés sur les jobs concernés.
     */
    public function test_jobs_expose_bounded_tries_and_backoff(): void
    {
        $job = new ProvisionDemoTenantJob('retry@example.com', 'Retry Sandbox', 'DZ');
        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 60, 120, 300], $job->backoff());

        $comm = new DispatchCommunicationJob(1, null, 'welcome', [], null);
        $this->assertSame(3, $comm->tries);
        $this->assertSame([10, 60], $comm->backoff());

        $warm = new WarmPaySlipPdfPathsForPayrollRunJob(1);
        $this->assertSame(3, $warm->tries);
        $this->assertSame(300, $warm->timeout);
        $this->assertSame([30, 120], $warm->backoff());
    }

    /**
     * #3600 : ProvisionGuidedTrial est idempotent — un retry (ou une double
     * soumission) ne crée pas un second tenant sandbox pour le même email.
     */
    public function test_provisioning_is_idempotent_for_same_email(): void
    {
        Mail::fake();

        $email = 'idem-'.uniqid().'@example.com';
        $companyName = 'Idem Sandbox '.uniqid();

        /** @var ProvisionGuidedTrial $provisioner */
        $provisioner = app(ProvisionGuidedTrial::class);

        $first = $provisioner->execute($email, $companyName, 'DZ');
        $second = $provisioner->execute($email, $companyName.' bis', 'DZ');

        $this->assertSame($first['company']->id, $second['company']->id);
        $this->assertSame($first['manager']->id, $second['manager']->id);
        $this->assertSame(1, Company::query()
            ->where('email', $email)
            ->where('metadata->provisioned_by', 'guided_trial')
            ->count());
    }

    /**
     * #5161 : le manager sandbox est créé avec `password_hash` dans le MÊME
     * INSERT — la colonne est NOT NULL sans défaut dans le schéma tenant.
     * Un `Employee::create()` sans `password_hash` (puis update post-hoc)
     * échouait en SQLSTATE 23502 → statut 'failed' ~8 s après signup en prod
     * (régression #4558, non couverte par le fix #4947 qui ne touchait
     * qu'EmployeeService). Pattern canonique #3677/#4151 : new Employee +
     * forceFill + save (cf. VerifyTrialSignup).
     */
    public function test_guided_trial_manager_persisted_with_password_hash(): void
    {
        Mail::fake();

        $email = 'guided-hash-'.uniqid().'@example.com';

        /** @var ProvisionGuidedTrial $provisioner */
        $provisioner = app(ProvisionGuidedTrial::class);
        $result = $provisioner->execute($email, 'Sandbox Guided Hash '.uniqid(), 'DZ');

        /** @var Employee $manager */
        $manager = Employee::query()
            ->where('email', $email)
            ->firstOrFail();

        $this->assertSame($result['manager']->id, $manager->id);
        $this->assertIsString($manager->password_hash);
        $this->assertStringStartsWith('$2y$', (string) $manager->password_hash);
        $this->assertSame('manager', $manager->role);
        $this->assertSame('principal', $manager->manager_role);
        $this->assertSame('active', $manager->status);
    }
}
