<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Parcours tenant VTC de bout en bout (revue produit 2026-10-11) :
 * de l'inscription self-service à la vitrine publiée pour le grand public.
 *
 *   signup (solution `vtc` + outil `showcase`) → verify OTP → set-password
 *   → login Sanctum → /auth/me (manager principal, geo+vtc actifs) →
 *   configuration de l'entreprise (branding) → PLUSIEURS véhicules →
 *   vitrine configurée (héro + services) → publiée →
 *   GET /public/vitrine/{slug} lisible SANS authentification.
 *
 * Ce test est la preuve exécutable de la promesse « un responsable VTC/taxi
 * s'inscrit, se connecte, configure son compte et son entreprise, ajoute un
 * ou plusieurs véhicules et publie sa vitrine pour le grand public ».
 * Chaque maillon est adressé par le VRAI chemin HTTP (jamais de raccourci
 * factory après le signup) : une régression RBAC signup, activation
 * geo→vtc, flags vitrine ou publication casse CE test.
 */
class VtcTenantJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'VTC-Express-2026-Solide!';

    private const EMAIL = 'patron@express-vtc.cm';

    public function test_vtc_tenant_journey_from_signup_to_published_showcase(): void
    {
        Mail::fake();
        $this->withHeader('Accept-Language', 'fr');

        // ── 1. Inscription self-service ─────────────────────────────────
        // La solution `vtc` est demandée seule : sa dépendance dure `geo`
        // (BC-34) doit être résolue automatiquement (activateWithDependencies).
        $signup = $this->postJson('/api/v1/trial/signup', [
            'email' => self::EMAIL,
            'company' => 'Express VTC Douala',
            'first_name' => 'Patron',
            'last_name' => 'Express',
            'role' => 'founder',
            'employees' => '1-10',
            'country' => 'CM',
            'solutions' => ['vtc'],
            'modules' => ['showcase'],
        ])->assertStatus(200);

        $provisioningToken = $signup->json('data.provisioning_token');
        self::assertIsString($provisioningToken);

        // ── 2. Vérification OTP → tenant + manager provisionnés ─────────
        /** @var CompanyRequest $companyRequest */
        $companyRequest = CompanyRequest::query()->where('email', self::EMAIL)->firstOrFail();

        $verify = $this->postJson('/api/v1/trial/verify', [
            'email' => self::EMAIL,
            'code' => $companyRequest->verification_token,
        ])->assertStatus(201)->assertJson(['success' => true]);

        /** @var Company $company */
        $company = Company::query()->findOrFail($verify->json('data.company.id'));

        // Dépendance dure résolue : geo ET vtc actifs ; vitrine active.
        self::assertTrue($company->hasFeature('geo'), 'geo devrait être auto-activé avec vtc');
        self::assertTrue($company->hasFeature('vtc'));
        self::assertTrue($company->hasFeature('company_showcase'));

        // ── 3. Le responsable définit son mot de passe puis se connecte ──
        $this->postJson('/api/v1/trial/set-password', [
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], ['X-Token' => $provisioningToken])
            ->assertOk()
            ->assertJson(['success' => true]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'device_name' => 'journey-test',
        ])->assertOk();

        $token = $login->json('token');
        self::assertIsString($token);
        $auth = ['Authorization' => 'Bearer '.$token];

        // ── 4. /auth/me : compte rattaché à SON entreprise, manager principal ──
        $this->getJson('/api/v1/auth/me', $auth)
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.manager_role', 'principal');

        // ── 5. Configuration de l'entreprise (branding) ──────────────────
        $this->patchJson('/api/v1/company/branding', [
            'display_name' => 'Express VTC',
            'primary_color' => '#0ea5e9',
        ], $auth)->assertOk();

        // ── 6. Un responsable VTC enregistre UN PUIS PLUSIEURS véhicules ──
        $this->postJson('/api/v1/vtc/vehicles', [
            'plate' => 'lt-700-ab',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'category' => 'berline',
        ], $auth)
            ->assertCreated()
            ->assertJsonPath('data.plate', 'LT-700-AB');

        $this->postJson('/api/v1/vtc/vehicles', [
            'plate' => 'LT-701-AB',
            'brand' => 'Honda',
            'model' => 'Civic',
            'category' => 'berline',
        ], $auth)->assertCreated();

        // La flotte complète est listée (2 véhicules, scope de SON tenant).
        $fleet = $this->getJson('/api/v1/vtc/vehicles', $auth)->assertOk();
        self::assertCount(2, $fleet->json('data'));

        // ── 7. La vitrine : le tenant organise sa présentation publique ──
        $this->postJson('/api/v1/showcase', [], $auth)->assertCreated();

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => [
                'heading' => 'Express VTC Douala',
                'subheading' => 'Votre chauffeur en 5 minutes, jour et nuit.',
            ],
        ], $auth)->assertCreated();

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'features',
            'content' => [
                'title' => 'Nos services',
                'items' => [
                    ['title' => 'Courses ville', 'description' => 'Douala et environs'],
                    ['title' => 'Navette aéroport', 'description' => 'Sur réservation'],
                ],
            ],
        ], $auth)->assertCreated();

        // Publication : exige au moins une section (garde PublishShowcase).
        $this->postJson('/api/v1/showcase/publish', [], $auth)
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // ── 8. Grand public : la vitrine est lisible SANS authentification ──
        $slug = (string) $company->slug;

        $this->getJson('/api/v1/public/vitrine/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $slug);
    }
}
