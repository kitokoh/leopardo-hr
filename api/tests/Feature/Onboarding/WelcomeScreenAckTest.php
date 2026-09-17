<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7604 — acquittement de l'écran de bienvenue de première connexion
 * (tranche du critère 2 de #7490 : « affiché UNE fois, persisté côté serveur,
 * pas en localStorage »).
 *
 * Contrat verrouillé :
 *  1. un responsable (`principal`/`rh`) acquitte : `welcome_seen_at` est écrit
 *     dans `public.companies.metadata` (lecture par requête QUALIFIÉE, même
 *     précaution que le contrôleur) ;
 *  2. l'écran ne s'affiche qu'une fois : la date n'est JAMAIS réécrite, le
 *     second appel répond `already_acknowledged: true` avec la même date ;
 *  3. RBAC : employé et comptable → 403, et AUCUNE écriture ;
 *  4. non authentifié → 401 ;
 *  5. écriture PARTIELLE : aucune clé existante de `metadata` n'est perdue.
 */
class WelcomeScreenAckTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function company(array $metadata = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => $metadata,
        ]);

        return $company;
    }

    private function actingAsRole(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * Relecture qualifiée de `public.companies` — le modèle sous `search_path`
     * tenant pointerait ailleurs (piège documenté).
     *
     * @return array<string, mixed>
     */
    private function persistedMetadata(Company $company): array
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        return json_decode((string) ($row->metadata ?? '{}'), true) ?? [];
    }

    public function test_manager_acknowledges_welcome_screen_exactly_once(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $first = $this->postJson('/api/v1/onboarding/welcome-ack');

        $first->assertOk()
            ->assertJsonPath('data.already_acknowledged', false)
            ->assertJsonStructure(['data' => ['welcome_seen_at', 'already_acknowledged']]);

        $seenAt = (string) $first->json('data.welcome_seen_at');
        $this->assertNotSame('', $seenAt, 'La date d’acquittement doit être persistée.');
        $this->assertSame($seenAt, $this->persistedMetadata($company)['welcome_seen_at'] ?? null);

        // Second appel (rechargement, double rendu) : idempotent.
        $second = $this->postJson('/api/v1/onboarding/welcome-ack');

        $second->assertOk()
            ->assertJsonPath('data.already_acknowledged', true)
            ->assertJsonPath('data.welcome_seen_at', $seenAt);

        $this->assertSame(
            $seenAt,
            $this->persistedMetadata($company)['welcome_seen_at'] ?? null,
            'La date d’origine ne doit JAMAIS être réécrite.'
        );
    }

    public function test_hr_manager_can_acknowledge(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'rh');

        $this->postJson('/api/v1/onboarding/welcome-ack')
            ->assertOk()
            ->assertJsonPath('data.already_acknowledged', false);
    }

    public function test_employee_cannot_acknowledge(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'employee');

        $this->postJson('/api/v1/onboarding/welcome-ack')->assertForbidden();

        $this->assertArrayNotHasKey('welcome_seen_at', $this->persistedMetadata($company));
    }

    public function test_accountant_cannot_acknowledge(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'comptable');

        $this->postJson('/api/v1/onboarding/welcome-ack')->assertForbidden();

        $this->assertArrayNotHasKey('welcome_seen_at', $this->persistedMetadata($company));
    }

    public function test_guest_is_rejected(): void
    {
        $this->postJson('/api/v1/onboarding/welcome-ack')->assertUnauthorized();
    }

    public function test_existing_metadata_keys_are_preserved(): void
    {
        $company = $this->company([
            'onboarding_completed' => true,
            'onboarding_completed_at' => '2026-09-01T08:00:00+00:00',
            'branding' => ['primary_color' => '#123456'],
        ]);
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/onboarding/welcome-ack')->assertOk();

        $metadata = $this->persistedMetadata($company);

        // Écriture partielle : rien n'est perdu, la clé d'onboarding est intacte.
        $this->assertTrue($metadata['onboarding_completed'] ?? null);
        $this->assertSame('2026-09-01T08:00:00+00:00', $metadata['onboarding_completed_at'] ?? null);
        $this->assertSame(['primary_color' => '#123456'], $metadata['branding'] ?? null);
        $this->assertArrayHasKey('welcome_seen_at', $metadata);
    }
}
