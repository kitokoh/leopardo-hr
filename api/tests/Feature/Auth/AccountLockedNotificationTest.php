<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8163 (suite #8124) — verrouillage de compte : notification du titulaire
 * et alerte sur les verrouillages répétés.
 *
 * - Un verrouillage (5 échecs) crée UNE notification `security.account_locked`
 *   dans le store canonique `notifications` (celui que lit
 *   `GET /notifications`) pour le titulaire — via `NotificationDispatcher`,
 *   aucun émetteur legacy (ADR-0013) ;
 * - 3 verrous du même compte en 24 h produisent un événement d'observabilité
 *   structuré (`auth.login_repeated_locks_detected` /
 *   `platform.login_repeated_locks_detected`) — identifiants uniquement,
 *   JAMAIS d'email en clair (doctrine #8144) ;
 * - le contrat de login (401/423) est inchangé, et un échec de notification
 *   ne casse jamais le parcours de login.
 */
class AccountLockedNotificationTest extends TestCase
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

    public function test_lock_creation_notifies_the_account_owner(): void
    {
        $employee = $this->employee('owner.notifie@company.test');

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'owner.notifie@company.test',
                'password' => 'faux',
            ])->assertStatus(401);
        }

        $this->assertSame(
            0,
            Notification::query()->where('type', 'security.account_locked')->count(),
            'aucune notification avant le seuil de verrouillage',
        );

        // 5e échec → le verrou est créé (contrat 401 conservé à la création).
        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner.notifie@company.test',
            'password' => 'faux',
        ])->assertStatus(401);

        $notification = Notification::query()
            ->where('type', 'security.account_locked')
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($notification, 'le titulaire doit être notifié du verrouillage');
        $this->assertSame((string) $employee->company_id, (string) $notification->company_id);
        $this->assertSame(__('auth.account_locked_notify_title'), $notification->title);
        $this->assertNotNull($notification->data['locked_until'] ?? null);

        // Contrat inchangé : tentative FAUSSE sous verrou actif → 423.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner.notifie@company.test',
            'password' => 'faux',
        ])->assertStatus(423);

        // ... et aucune notification supplémentaire sous verrou actif.
        $this->assertSame(1, Notification::query()->where('type', 'security.account_locked')->count());
    }

    public function test_repeated_tenant_locks_emit_a_structured_observability_event(): void
    {
        $employee = $this->employee('attaque.ciblee@company.test');

        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();

        // 3 verrous en 24 h : le 1er à 5 échecs, les suivants dès l'échec
        // suivant l'expiration (le compteur d'échecs n'est pas remis à zéro
        // par le verrou — expiration simulée en levant `locked_until`).
        $this->failTenantLogin('attaque.ciblee@company.test', 5);
        $this->expireTenantLock($employee);
        $this->failTenantLogin('attaque.ciblee@company.test', 1);
        $this->expireTenantLock($employee);
        $this->failTenantLogin('attaque.ciblee@company.test', 1);

        $log->shouldHaveReceived('warning')->withArgs(function (mixed $message, array $context = []) use ($employee): bool {
            if ($message !== 'auth.login_repeated_locks_detected') {
                return true;
            }

            $this->assertSame($employee->id, $context['employee_id'] ?? null);
            $this->assertSame(3, $context['locks_24h'] ?? null);
            // PII : jamais d'email en clair, ni dans le message ni le contexte.
            $this->assertStringNotContainsString('attaque.ciblee@company.test', (string) $message);
            $this->assertStringNotContainsString('attaque.ciblee@company.test', (string) json_encode($context));

            return true;
        })->atLeast()->once();
    }

    public function test_repeated_platform_locks_emit_a_structured_observability_event(): void
    {
        $superAdmin = new SuperAdmin(['name' => 'Admin Cible', 'email' => 'admin.cible@leopardo.test']);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123'), 'status' => 'active'])->save();

        // Le test enchaîne 15 requêtes de login : on neutralise le bucket
        // `auth-sensitive` (10/min par email+IP, hors objet du test — le
        // verrou applicatif, pas le throttle HTTP, est vérifié ici).
        config(['security.rate_limits.auth_per_minute' => 1000]);

        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();

        // 3 verrous : chaque cycle = 5 échecs (le compteur de tentatives est
        // oublié à la création du verrou) ; expiration simulée en oubliant la
        // clé de verrou.
        foreach (range(1, 3) as $cycle) {
            $this->failPlatformLogin('admin.cible@leopardo.test', 5);
            Cache::forget('platform_login_admin.cible@leopardo.test:lock');
        }

        $log->shouldHaveReceived('warning')->withArgs(function (mixed $message, array $context = []) use ($superAdmin): bool {
            if ($message !== 'platform.login_repeated_locks_detected') {
                return true;
            }

            $this->assertSame($superAdmin->id, $context['super_admin_id'] ?? null);
            $this->assertSame(3, $context['locks_24h'] ?? null);
            $this->assertSame(
                substr(hash('sha256', 'admin.cible@leopardo.test'), 0, 16),
                $context['email_hash'] ?? null,
            );
            $this->assertStringNotContainsString('admin.cible@leopardo.test', (string) json_encode($context));

            return true;
        })->atLeast()->once();
    }

    public function test_notification_failure_never_breaks_the_login_flow(): void
    {
        $employee = $this->employee('robuste@company.test');

        // Dispatcher en panne : le verrou doit quand même être posé et le
        // contrat HTTP conservé (notification best-effort).
        $this->app->bind(NotificationDispatcher::class, function (): NotificationDispatcher {
            $push = $this->app->make(PushNotificationService::class);

            return new class($push) extends NotificationDispatcher
            {
                public function dispatch(
                    int $userId,
                    string $type,
                    string $title,
                    ?string $body = null,
                    array $data = [],
                    ?string $actionUrl = null,
                ): Notification {
                    throw new RuntimeException('store notifications indisponible (simulé)');
                }
            };
        });

        $this->failTenantLogin('robuste@company.test', 5);

        $fresh = $employee->fresh();
        $this->assertInstanceOf(Employee::class, $fresh);
        $this->assertNotNull($fresh->locked_until, 'le verrou est posé même si la notification échoue');
    }

    /**
     * @return array{0: Employee}
     */
    private function employee(string $email): Employee
    {
        /** @var Company $company */
        $company = Company::query()->create([
            'name' => 'Lock Notify Co',
            'slug' => 'lock-notify-'.uniqid(),
            'sector' => 'services',
            'country' => 'CM',
            'city' => 'Douala',
            'email' => 'lock-notify@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = new Employee(['email' => $email]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'failed_login_attempts' => 0,
        ])->save();

        return $employee;
    }

    private function failTenantLogin(string $email, int $times): void
    {
        foreach (range(1, $times) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'faux',
            ])->assertStatus(401);
        }
    }

    private function expireTenantLock(Employee $employee): void
    {
        $fresh = $employee->fresh();
        $this->assertInstanceOf(Employee::class, $fresh);
        $this->assertNotNull($fresh->locked_until, 'un verrou doit exister avant de simuler son expiration');
        $fresh->forceFill(['locked_until' => null])->save();
    }

    private function failPlatformLogin(string $email, int $times): void
    {
        foreach (range(1, $times) as $attempt) {
            $this->postJson('/api/v1/platform/auth/login', [
                'email' => $email,
                'password' => 'faux',
            ])->assertStatus(401);
        }
    }
}
