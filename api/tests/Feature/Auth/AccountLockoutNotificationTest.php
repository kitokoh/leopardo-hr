<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AccountLockoutNotifier;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Middleware\ResilientThrottleRequests;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8163 (suite #8124) — notifications & alertes de verrouillage de compte.
 *
 * - Un verrou POSÉ (5e échec) déclenche une notification in-app au titulaire
 *   via le canal canonique NotificationDispatcher — sans changer le contrat
 *   de login (401/423), même si l'envoi échoue (best-effort).
 * - Un même compte verrouillé 3 fois en 24 h produit une alerte visible :
 *   événement d'observabilité `auth.repeated_account_lockouts` (tenant) /
 *   `platform.repeated_account_lockouts` (plateforme) + notification in-app
 *   aux managers `principal`/`rh` de la société (une seule fois par fenêtre).
 */
class AccountLockoutNotificationTest extends TestCase
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

    public function test_lockout_notifies_the_account_owner_in_app(): void
    {
        $company = $this->company();
        $employee = $this->employee($company, 'owner-locked@company.test');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $employee->email,
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(401);
        }

        $notifications = Notification::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('type', AccountLockoutNotifier::TYPE_ACCOUNT_LOCKED)
            ->get();

        $this->assertCount(1, $notifications, 'le verrou posé doit notifier le titulaire une fois');
        $notification = $notifications->firstOrFail();
        $this->assertSame((string) $company->id, (string) $notification->company_id);
        $this->assertArrayHasKey('locked_until', $notification->data ?? []);

        // Le compte est bien verrouillé (protection inchangée).
        $this->assertNotNull($employee->fresh()?->locked_until);
    }

    public function test_failed_attempts_below_threshold_do_not_notify(): void
    {
        $company = $this->company();
        $employee = $this->employee($company, 'almost-locked@company.test');

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $employee->email,
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(401);
        }

        $this->assertSame(0, Notification::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('type', AccountLockoutNotifier::TYPE_ACCOUNT_LOCKED)
            ->count());
        $this->assertNull($employee->fresh()?->locked_until);
    }

    public function test_third_lockout_within_24h_alerts_principal_and_rh_managers_once(): void
    {
        // `channel()` est mappé sur le spy lui-même : sans cela,
        // `Log::channel('structured')` (middleware StructuredLogging,
        // dispatcher de notifications) renverrait null et casserait la
        // requête (même technique que TrialSignupLogsPiiTest).
        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();
        // Le sujet est le verrouillage applicatif, pas la politique de
        // throttle `auth-sensitive` (10 req/min, testée ailleurs) : ce test
        // joue 3 rounds de 5 échecs + connexions valides, au-delà du bucket.
        $this->withoutMiddleware(ResilientThrottleRequests::class);

        $company = $this->company();
        $employee = $this->employee($company, 'target@company.test');
        $principal = $this->manager($company, 'principal-co@company.test', 'principal');
        $rh = $this->manager($company, 'rh-co@company.test', 'rh');
        $dept = $this->manager($company, 'dept-co@company.test', 'dept');

        // 3 verrous dans la fenêtre 24 h : entre chaque, le titulaire lève
        // son verrou avec ses identifiants VALIDES (comportement #8124).
        foreach (range(1, 3) as $round) {
            foreach (range(1, 5) as $attempt) {
                $this->postJson('/api/v1/auth/login', [
                    'email' => $employee->email,
                    'password' => 'mauvais-mot-de-passe',
                ])->assertStatus(401);
            }

            $this->assertNotNull($employee->fresh()?->locked_until, "verrou posé au round {$round}");

            if ($round < 3) {
                $this->postJson('/api/v1/auth/login', [
                    'email' => $employee->email,
                    'password' => 'password123',
                ])->assertOk();
            }
        }

        // Événement d'observabilité émis (identifiants uniquement, pas d'email).
        $log->shouldHaveReceived('warning')
            ->with('auth.repeated_account_lockouts', Mockery::on(
                fn (array $context): bool => ($context['employee_id'] ?? null) === $employee->id
                    && ($context['lockouts_24h'] ?? null) >= 3
                    && ! str_contains((string) json_encode($context), '@')
            ))
            ->atLeast()->once();

        // Managers principal + rh alertés UNE fois ; le manager dept ne l'est pas.
        foreach ([$principal, $rh] as $manager) {
            $this->assertSame(1, Notification::withoutGlobalScopes()
                ->where('employee_id', $manager->id)
                ->where('type', AccountLockoutNotifier::TYPE_REPEATED_LOCKOUTS)
                ->count(), "manager {$manager->manager_role} doit recevoir exactement une alerte");
        }
        $this->assertSame(0, Notification::withoutGlobalScopes()
            ->where('employee_id', $dept->id)
            ->where('type', AccountLockoutNotifier::TYPE_REPEATED_LOCKOUTS)
            ->count());

        // Le titulaire a été notifié de chacun des 3 verrous.
        $this->assertSame(3, Notification::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('type', AccountLockoutNotifier::TYPE_ACCOUNT_LOCKED)
            ->count());
    }

    public function test_lockout_notification_failure_never_breaks_the_login_contract(): void
    {
        $dispatcher = Mockery::mock(NotificationDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('canal indisponible'));
        $this->app->instance(NotificationDispatcher::class, $dispatcher);

        $company = $this->company();
        $employee = $this->employee($company, 'resilient@company.test');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $employee->email,
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(401);
        }

        // Verrou posé malgré l'échec du canal de notification.
        $this->assertNotNull($employee->fresh()?->locked_until);
    }

    public function test_platform_third_lockout_within_24h_logs_an_observability_event(): void
    {
        // Voir test tenant : `channel()` mappé sur le spy, sinon
        // `Log::channel('structured')` renverrait null → 500.
        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();
        // Hors sujet ici : le throttle `auth-sensitive` (10 req/min) — ce
        // test joue 3 rounds de 5 échecs (18 requêtes) sur la même route.
        $this->withoutMiddleware(ResilientThrottleRequests::class);

        $superAdmin = new SuperAdmin(['name' => 'Admin Cible', 'email' => 'targeted-admin@leopardo.test']);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123'), 'status' => 'active'])->save();

        $lockKey = 'platform_login_targeted-admin@leopardo.test:lock';

        foreach (range(1, 3) as $round) {
            foreach (range(1, 5) as $attempt) {
                $this->postJson('/api/v1/platform/auth/login', [
                    'email' => 'targeted-admin@leopardo.test',
                    'password' => 'mauvais',
                ])->assertStatus(401);
            }

            // Contrat inchangé : tentative fausse pendant le verrou → 423.
            $this->postJson('/api/v1/platform/auth/login', [
                'email' => 'targeted-admin@leopardo.test',
                'password' => 'mauvais',
            ])->assertStatus(423);

            // Expiration simulée du verrou (15 min) avant le round suivant.
            Cache::forget($lockKey);
        }

        $log->shouldHaveReceived('warning')
            ->with('platform.repeated_account_lockouts', Mockery::on(
                fn (array $context): bool => ($context['super_admin_id'] ?? null) === $superAdmin->id
                    && ($context['lockouts_24h'] ?? null) >= 3
                    && ! str_contains((string) json_encode($context), '@')
            ))
            ->once();
    }

    private function employee(Company $company, string $email): Employee
    {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'first_name' => 'Titulaire',
            'last_name' => 'Ducompte',
            'role' => 'employee',
            'status' => 'active',
            'failed_login_attempts' => 0,
        ])->save();

        return $employee;
    }

    private function manager(Company $company, string $email, string $managerRole): Employee
    {
        $manager = new Employee(['email' => $email]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => ucfirst($managerRole),
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        return $manager;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::query()->create([
            'name' => 'Lockout Notify Co',
            'slug' => 'lockout-notify-co-'.uniqid(),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'lockout-notify@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        return $company;
    }
}
