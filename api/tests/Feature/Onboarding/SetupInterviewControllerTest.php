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
 * #7493 — entretien de préparation conversationnel.
 *
 * Contrat verrouillé :
 *  1. brouillon SERVEUR : PATCH answers fusionne incrémentalement, l'état est
 *     relisible par GET (reprise sur un autre appareil) ;
 *  2. allowlist fail-closed : question/valeur inconnue → 422, aucune écriture ;
 *  3. `complete` active les modules attendus (restaurateur avec employés →
 *     restaurant + RH/présence ; solo → aucun outil d'équipe) ;
 *  4. rejouer `complete` est un no-op (idempotence prouvée) ;
 *  5. `dismiss` persiste `dismissed` (relance douce) sans toucher un
 *     entretien complété ;
 *  6. RBAC : employé → 403 ; non authentifié → 401.
 */
class SetupInterviewControllerTest extends TestCase
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
     * Relecture qualifiée de `public.companies` (piège search_path documenté).
     *
     * @return array{metadata: array<string, mixed>, features: array<string, mixed>}
     */
    private function persistedCompany(Company $company): array
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        return [
            'metadata' => json_decode((string) ($row->metadata ?? '{}'), true) ?? [],
            'features' => json_decode((string) ($row->features ?? '{}'), true) ?? [],
        ];
    }

    public function test_draft_answers_are_merged_and_resumable(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_type' => 'team', 'team_size' => '11-50'],
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        // Deuxième brouillon : fusion, pas d'écrasement.
        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['sector' => 'restaurant', 'premises' => null],
        ])->assertOk();

        $state = $this->getJson('/api/v1/setup-interview');
        $state->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.answers.company_type', 'team')
            ->assertJsonPath('data.answers.team_size', '11-50')
            ->assertJsonPath('data.answers.sector', 'restaurant');

        // Le brouillon vit côté SERVEUR (reprise sur un autre appareil).
        $persisted = $this->persistedCompany($company)['metadata']['setup_interview'] ?? [];
        $this->assertSame('restaurant', $persisted['answers']['sector'] ?? null);
    }

    public function test_unknown_question_or_value_is_rejected_without_write(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['sector' => 'restaurant', 'hack' => 'dynamite'],
        ])->assertStatus(422);

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_type' => 'pirate'],
        ])->assertStatus(422);

        $this->assertArrayNotHasKey(
            'setup_interview',
            $this->persistedCompany($company)['metadata'],
            'Une réponse refusée ne doit produire AUCUNE écriture.'
        );
    }

    public function test_complete_activates_restaurant_and_team_tools(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => [
                'company_type' => 'team',
                'team_size' => '11-50',
                'sector' => 'restaurant',
                'premises' => 'single',
                'scheduled_hours' => 'yes',
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v1/setup-interview/complete');
        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.already_completed', false);

        $this->assertContains('restaurant', $response->json('data.activated.solutions'));
        $this->assertContains('employees', $response->json('data.activated.tools'));
        $this->assertContains('attendance', $response->json('data.activated.tools'));

        // Les deux sources de vérité sont persistées (features + metadata.modules).
        $persisted = $this->persistedCompany($company);
        $this->assertTrue((bool) ($persisted['features']['restaurant'] ?? false));
        $this->assertTrue((bool) ($persisted['metadata']['modules']['employees'] ?? false));
        $this->assertTrue((bool) ($persisted['metadata']['modules']['attendance'] ?? false));
        $this->assertSame('completed', $persisted['metadata']['setup_interview']['status'] ?? null);
    }

    public function test_replaying_complete_is_a_no_op(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_type' => 'team', 'sector' => 'restaurant'],
        ])->assertOk();

        $first = $this->postJson('/api/v1/setup-interview/complete');
        $first->assertOk()->assertJsonPath('data.already_completed', false);

        $featuresAfterFirst = $this->persistedCompany($company)['features'];

        $second = $this->postJson('/api/v1/setup-interview/complete');
        $second->assertOk()
            ->assertJsonPath('data.already_completed', true)
            ->assertJsonPath('data.completed_at', $first->json('data.completed_at'));

        // Idempotence : le récapitulatif est rejoué tel quel, rien ne bouge.
        $this->assertSame($first->json('data.activated'), $second->json('data.activated'));
        $this->assertSame($featuresAfterFirst, $this->persistedCompany($company)['features']);
    }

    public function test_solo_profile_gets_no_team_tools(): void
    {
        $company = $this->company(['company_type' => 'solo']);
        $this->actingAsRole($company, 'manager', 'principal');

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_type' => 'solo', 'sector' => 'services', 'priorities' => ['showcase']],
        ])->assertOk();

        $response = $this->postJson('/api/v1/setup-interview/complete');
        $response->assertOk();

        $tools = $response->json('data.activated.tools');
        $this->assertNotContains('employees', $tools);
        $this->assertContains('showcase', $tools);
        $this->assertSame([], $response->json('data.activated.solutions'));
    }

    /**
     * #7853 — le nom d'entreprise n'est plus demandé à l'inscription : la
     * question `company_name` de l'entretien (texte libre, zappable) renomme
     * la société à la clôture — champ `name` uniquement, le SLUG existant
     * n'est JAMAIS réécrit (il porte des URLs déjà distribuées).
     */
    public function test_complete_renames_company_from_interview_answer_without_touching_slug(): void
    {
        $company = $this->company();
        $originalSlug = $company->slug;
        $this->actingAsRole($company, 'manager', 'principal');

        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_name' => 'Boulangerie El Amel', 'sector' => 'commerce'],
        ])->assertOk();

        $this->postJson('/api/v1/setup-interview/complete')->assertOk();

        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        $this->assertSame('Boulangerie El Amel', $row->name);
        $this->assertSame($originalSlug, $row->slug);
    }

    public function test_complete_without_company_name_keeps_existing_name(): void
    {
        $company = $this->company();
        $originalName = $company->name;
        $this->actingAsRole($company, 'manager', 'principal');

        // Question sautée (`null`) : aucun renommage.
        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_name' => null, 'sector' => 'commerce'],
        ])->assertOk();

        $this->postJson('/api/v1/setup-interview/complete')->assertOk();

        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        $this->assertSame($originalName, $row->name);
    }

    public function test_company_name_answer_is_rejected_when_out_of_bounds(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        // Fail-closed : hors bornes (1 caractère) → 422, aucune écriture.
        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_name' => 'A'],
        ])->assertStatus(422);

        $persisted = $this->persistedCompany($company);
        $this->assertArrayNotHasKey('setup_interview', $persisted['metadata']);
    }

    public function test_dismiss_persists_soft_state_and_never_reopens_completed(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/setup-interview/dismiss')
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        // « Terminer plus tard » puis reprise : le brouillon reste possible.
        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_type' => 'team'],
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->postJson('/api/v1/setup-interview/complete')->assertOk();

        // Un dismiss tardif ne rouvre pas un entretien complété.
        $this->postJson('/api/v1/setup-interview/dismiss')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_rbac_employee_forbidden(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'employee');

        $this->getJson('/api/v1/setup-interview')->assertStatus(403);
        $this->patchJson('/api/v1/setup-interview/answers', [
            'answers' => ['company_type' => 'solo'],
        ])->assertStatus(403);
        $this->postJson('/api/v1/setup-interview/complete')->assertStatus(403);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/setup-interview')->assertUnauthorized();
        $this->postJson('/api/v1/setup-interview/complete')->assertUnauthorized();
    }
}
