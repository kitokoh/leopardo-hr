<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-O-06 / #8627 — reprise du parcours d'onboarding après interruption.
 *
 * Contrat verrouillé :
 *  1. sans état persisté → `current_step: null`, `resumed: false` (on n'invente
 *     pas une progression) ;
 *  2. après abandon à l'étape 4 → reprise **à 4** (c'est l'exigence de l'issue) ;
 *  3. revenir en arrière déplace `current_step` mais NE DÉCRÉMENTE JAMAIS
 *     `furthest_step` — une étape validée n'est pas rejouée ;
 *  4. rejouer la même étape est idempotent (`changed: false`, même état) ;
 *  5. un état de navigateur falsifié n'ouvre pas d'étape non autorisée : la clé
 *     inconnue du tenant renvoie 404 et n'écrit rien ;
 *  6. isolation multi-tenant : l'état d'une autre société n'est jamais lu ni
 *     écrasé ;
 *  7. RBAC : lecture ouverte au tenant, écriture réservée aux gestionnaires ;
 *     non authentifié → 401 ;
 *  8. `not_before_verification: true` — la limitation pré-OTP est DITE au
 *     client, pas laissée implicite.
 */
class OnboardingResumeTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);

        return $company;
    }

    private function actingAsRole(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
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
     * Lit l'état persisté par requête QUALIFIÉE (le modèle résolu par
     * `search_path` peut pointer vers la table shadow du schéma tenant).
     *
     * @return array<string, mixed>|null
     */
    private function persistedState(Company $company): ?array
    {
        $row = DB::table('public.companies')->where('id', $company->id)->first();

        if ($row === null) {
            return null;
        }

        $raw = $row->onboarding_state ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        /** @var array<string, mixed>|null $raw */
        return is_array($raw) ? $raw : null;
    }

    public function test_fresh_tenant_has_no_invented_progress(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);

        $response = $this->getJson('/api/v1/onboarding-setup/state');

        $response->assertOk()
            ->assertJsonPath('data.current_step', null)
            ->assertJsonPath('data.furthest_step', null)
            ->assertJsonPath('data.resumed', false)
            ->assertJsonPath('data.not_before_verification', true);

        // Aucune écriture : consulter son état ne crée pas d'état.
        $this->assertNull($this->persistedState($company));
    }

    public function test_resume_after_abandon_at_step_four_returns_step_four(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);

        // Le seed paresseux crée les étapes réelles du tenant.
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        $step4 = OnboardingStep::where('company_id', $company->id)->orderBy('order')->skip(3)->first();
        $this->assertInstanceOf(OnboardingStep::class, $step4, 'le jeu d\'étapes par défaut doit contenir au moins 4 étapes');

        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $step4->step_key])
            ->assertOk()
            ->assertJsonPath('data.current_step', $step4->step_key)
            ->assertJsonPath('data.current_step_order', (int) $step4->order)
            ->assertJsonPath('data.furthest_step', $step4->step_key)
            ->assertJsonPath('data.resumed', true);

        // Nouvelle session (autre requête) : le serveur — et non le navigateur —
        // dit où reprendre.
        $this->getJson('/api/v1/onboarding-setup/state')
            ->assertOk()
            ->assertJsonPath('data.current_step', $step4->step_key)
            ->assertJsonPath('data.resumed', true);
    }

    public function test_going_back_moves_current_step_but_never_decrements_furthest(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        $steps = OnboardingStep::where('company_id', $company->id)->orderBy('order')->get();
        $step4 = $steps->offsetGet(3);
        $step2 = $steps->offsetGet(1);
        $this->assertInstanceOf(OnboardingStep::class, $step4);
        $this->assertInstanceOf(OnboardingStep::class, $step2);

        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $step4->step_key])->assertOk();

        $response = $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $step2->step_key]);

        $response->assertOk()
            ->assertJsonPath('data.current_step', $step2->step_key)
            ->assertJsonPath('data.current_step_order', (int) $step2->order)
            // MONOTONE : le plus haut atteint reste l'étape 4.
            ->assertJsonPath('data.furthest_step', $step4->step_key)
            ->assertJsonPath('data.furthest_step_order', (int) $step4->order);

        $persisted = $this->persistedState($company);
        $this->assertSame($step4->step_key, $persisted['furthest_step'] ?? null);
        $this->assertSame($step2->step_key, $persisted['current_step'] ?? null);
    }

    public function test_replaying_the_same_step_is_idempotent(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        $step3 = OnboardingStep::where('company_id', $company->id)->orderBy('order')->skip(2)->first();
        $this->assertInstanceOf(OnboardingStep::class, $step3);

        $first = $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $step3->step_key]);
        $first->assertOk()->assertJsonPath('data.changed', true);

        $second = $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $step3->step_key]);
        $second->assertOk()->assertJsonPath('data.changed', false);

        // Aucun doublon d'effet : une seule étape courante, même valeur.
        $this->assertSame(
            $first->json('data.current_step'),
            $second->json('data.current_step')
        );
        $this->assertSame(
            $first->json('data.furthest_step'),
            $second->json('data.furthest_step')
        );
    }

    public function test_unknown_step_is_rejected_and_writes_nothing(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        // Un état navigateur falsifié proposerait une clé arbitraire.
        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => 'not_a_real_step'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'ONBOARDING_STEP_NOT_FOUND');

        $this->assertNull($this->persistedState($company));
    }

    public function test_malformed_step_key_fails_validation(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);

        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => 'Étape 4!'])
            ->assertStatus(422);

        $this->patchJson('/api/v1/onboarding-setup/state', [])
            ->assertStatus(422);
    }

    public function test_other_tenant_state_is_never_read_or_overwritten(): void
    {
        $tenantA = $this->company();
        $tenantB = $this->company();

        $foreignStep = OnboardingStep::create([
            'company_id' => $tenantB->id,
            'step_key' => 'tenant_b_secret_step',
            'title' => 'Étape du tenant B',
            'order' => 1,
            'required' => false,
            'status' => 'pending',
        ]);

        $this->actingAsRole($tenantA);
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        // La clé existe — mais chez un AUTRE tenant : 404, aucune fuite.
        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $foreignStep->step_key])
            ->assertStatus(404);

        $this->assertNull($this->persistedState($tenantA));
        $this->assertNull($this->persistedState($tenantB));
    }

    public function test_employee_cannot_write_state_but_can_read_it(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'employee', null);
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => 'company_info'])
            ->assertStatus(403);

        $this->assertNull($this->persistedState($company));
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/onboarding-setup/state')->assertStatus(401);
        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => 'company_info'])->assertStatus(401);
    }

    public function test_corrupted_state_is_not_silently_repaired(): void
    {
        $company = $this->company();
        $this->actingAsRole($company);

        DB::table('public.companies')->where('id', $company->id)->update([
            'onboarding_state' => json_encode(['current_step' => 42, 'furthest_step' => []]),
        ]);

        // Une forme invalide est ramenée à `null` : on n'invente pas une étape
        // à partir d'une valeur incohérente.
        $this->getJson('/api/v1/onboarding-setup/state')
            ->assertOk()
            ->assertJsonPath('data.current_step', null)
            ->assertJsonPath('data.furthest_step', null)
            ->assertJsonPath('data.resumed', false);
    }

    public function test_existing_metadata_is_preserved(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => ['welcome_seen_at' => '2026-10-01T09:00:00+00:00'],
        ]);

        $this->actingAsRole($company);
        $this->getJson('/api/v1/onboarding-setup/state')->assertOk();

        $step = OnboardingStep::where('company_id', $company->id)->orderBy('order')->first();
        $this->assertInstanceOf(OnboardingStep::class, $step);

        $this->patchJson('/api/v1/onboarding-setup/state', ['step_key' => $step->step_key])->assertOk();

        // L'état dédié n'écrase pas le canal `metadata` existant.
        $row = DB::table('public.companies')->where('id', $company->id)->first();
        $this->assertNotNull($row);
        $metadata = is_string($row->metadata ?? null) ? json_decode($row->metadata, true) : ($row->metadata ?? []);
        $this->assertSame('2026-10-01T09:00:00+00:00', $metadata['welcome_seen_at'] ?? null);
    }
}
