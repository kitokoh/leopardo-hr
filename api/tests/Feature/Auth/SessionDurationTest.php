<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Carbon\Carbon;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7491 — Session client glissante.
 *
 * #9344 (audit sécurité 2026-11-10, F2) — la durée par défaut est passée de
 * 30 jours (43 200 min) à 12 h (720 min). Le raisonnement de #7491 (un
 * utilisateur actif ne voit jamais l'écran de connexion) reste servi par
 * `TokenAutoRefreshMiddleware` : le jeton est pivoté dans la fenêtre
 * `sanctum.auto_refresh_window` avant son échéance. Ce qui change est la
 * fenêtre d'exposition d'un jeton VOLÉ : 12 h au lieu d'un mois.
 *
 * La durée longue (30 j) reste disponible pour les appareils ENRÔLÉS
 * (kiosque, Edge) via `sanctum.device_expiration`.
 */
class SessionDurationTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // `factory()->create()` est typé `Model` par Larastan : l'annotation
        // locale (convention du dépôt, cf. AuthLoginDefensiveTest) rétrécit le
        // type avant l'affectation aux propriétés typées (PHPStan level 8,
        // `assign.propertyType`).
        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_login_issues_a_token_expiring_in_twelve_hours(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->manager->email,
            'password' => 'password123', // défaut EmployeeFactory
        ]);

        $response->assertOk();

        // #9093 — `token_expires_at` est exposé à la RACINE de la réponse
        // (`EmployeeResource::additional()`), pas sous `data` : le test lisait
        // `data.token_expires_at`, toujours null, et échouait donc sur une
        // erreur de chemin, pas sur une absence d'expiration. Prouvé par
        // `IdentityShapesProbeTest` (root_keys=data,token,token_type,token_expires_at).
        $expiresAt = $response->json('token_expires_at');
        $this->assertIsString($expiresAt);

        $expected = now()->addMinutes(720);
        $actual = Carbon::parse($expiresAt);

        // Tolérance de 5 minutes (temps d'exécution de la requête).
        $this->assertTrue(
            $actual->diffInMinutes($expected) <= 5,
            "Le token expire à {$actual->toIso8601String()} au lieu de ~12 h ({$expected->toIso8601String()}).",
        );
    }

    public function test_sanctum_default_expiration_is_twelve_hours(): void
    {
        // Garde de configuration (#9344) : si le défaut remonte à 30 jours,
        // ce test le signale avant même le premier login.
        $this->assertSame(720, (int) config('sanctum.expiration'));
    }

    public function test_enrolled_device_tokens_keep_the_long_lifetime(): void
    {
        // #9344 — la durée longue est réservée aux appareils enrôlés, et
        // n'est PAS atteignable par le `device_name` fourni par le client.
        $this->assertSame(43200, (int) config('sanctum.device_expiration'));

        $service = app(\App\Core\Auth\Infrastructure\Services\TokenExpiryService::class);

        $interactive = $service->forNewToken('Mobile App');
        $device = $service->forNewToken('kiosk-1', isEnrolledDevice: true);

        $this->assertNotNull($interactive);
        $this->assertNotNull($device);
        $this->assertTrue(
            $device->diffInMinutes($interactive) > 40000,
            'Un appareil enrôlé doit obtenir une durée nettement plus longue qu\'une surface interactive.',
        );
    }
}
