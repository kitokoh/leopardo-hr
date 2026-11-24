<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Interfaces\Api\V1\Requests\TransitionOpportunityStageRequest;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #eval-2026-11-24 — verrouille la regle `prohibited_if` sur `lost_reason`.
 *
 * ## Pourquoi un test dedie
 *
 * `CrmOpportunityStageTest::test_reason_on_a_won_deal_is_refused` couvre le
 * chemin HTTP : il prouve que l'endpoint repond 422. Il ne prouve PAS que le
 * refus vient de la regle de validation — un `withValidator()` qui ajouterait
 * une erreur sur `lost_reason` produirait le meme 422, et la regle pourrait
 * disparaitre sans qu'aucun test ne tombe.
 *
 * Ce fichier verifie la regle ELLE-MEME, sur la requete reelle, et couvre les
 * trois angles morts que le test HTTP laisse ouverts :
 *
 *   1. la regle est bien presente dans `rules()` (et pas seulement son effet) ;
 *   2. elle ne se declenche PAS sur `lost` (sinon on interdirait la donnee
 *      qu'on exige par ailleurs — `required_if:stage,lost`) ;
 *   3. elle ne se declenche PAS sur les etapes ouvertes, ou `lost_reason` est
 *      simplement ignoree : un client qui renvoie le formulaire complet avec
 *      une raison heritee d'un brouillon ne doit pas etre bloque.
 *
 * Le message d'erreur est aussi verifie : c'est lui que lit l'utilisateur, et
 * une cle de traduction manquante afficherait `crm.opportunities.…` en clair.
 */
class CrmOpportunityLostReasonRuleTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function opportunity(string $stage = 'prospecting'): CrmOpportunity
    {
        /** @var CrmOpportunity $opportunity */
        $opportunity = CrmOpportunity::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Affaire de test',
            'stage' => $stage,
            'status' => 'open',
            'amount' => '1000.00',
            'currency' => 'DZD',
        ]);

        return $opportunity;
    }

    /**
     * Valide un payload contre la VRAIE requete, sans passer par HTTP.
     *
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validate(array $payload, ?CrmOpportunity $opportunity = null)
    {
        $request = TransitionOpportunityStageRequest::create(
            "/api/v1/crm/opportunities/{$opportunity?->id}/stage",
            'PATCH',
            $payload,
        );

        if ($opportunity !== null) {
            $request->setRouteResolver(fn () => new class($opportunity)
            {
                public function __construct(private CrmOpportunity $opportunity) {}

                public function __call(string $name, array $arguments): mixed
                {
                    return $name === 'opportunity' ? $this->opportunity : null;
                }
            });
        }

        return Validator::make($payload, $request->rules(), $request->messages());
    }

    // ─── La regle est bien celle qu'on croit ────────────────────────────────

    /**
     * Si `prohibited_if` disparait de `rules()`, ce test tombe — meme si
     * `withValidator()` masquait la regression cote HTTP.
     */
    public function test_the_rule_is_declared_on_lost_reason(): void
    {
        $rules = (new TransitionOpportunityStageRequest)->rules();

        $this->assertArrayHasKey('lost_reason', $rules);
        $this->assertContains(
            'prohibited_if:stage,won',
            $rules['lost_reason'],
            'La regle prohibited_if a disparu de TransitionOpportunityStageRequest::rules().',
        );
    }

    // ─── Le refus ───────────────────────────────────────────────────────────

    public function test_a_lost_reason_on_a_won_deal_fails_validation(): void
    {
        $validator = $this->validate([
            'stage' => 'won',
            'lost_reason' => "n'a pas de sens",
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lost_reason', $validator->errors()->toArray());
    }

    /**
     * Le message doit etre traduit : une cle absente afficherait
     * `crm.opportunities.lost_reason_only_when_lost` a l'utilisateur.
     */
    public function test_the_refusal_message_is_translated(): void
    {
        $validator = $this->validate([
            'stage' => 'won',
            'lost_reason' => 'raison trompeuse',
        ]);

        $message = (string) $validator->errors()->first('lost_reason');

        $this->assertNotSame('', $message);
        $this->assertStringNotContainsString('crm.opportunities.', $message);
        $this->assertSame(__('crm.opportunities.lost_reason_only_when_lost'), $message);
    }

    /**
     * Le refus doit etre un 422 de validation, pas un 500 : la regle est une
     * contrainte de contrat, elle se rend dans `errors`.
     */
    public function test_the_endpoint_returns_422_and_leaves_the_row_untouched(): void
    {
        Sanctum::actingAs($this->manager());

        $opportunity = $this->opportunity();

        $this->patchJson("/api/v1/crm/opportunities/{$opportunity->id}/stage", [
            'stage' => 'won',
            'lost_reason' => 'raison trompeuse',
        ])->assertStatus(422)->assertJsonValidationErrors('lost_reason');

        $opportunity->refresh();
        $this->assertSame('prospecting', $opportunity->stage, 'Le refus ne doit rien ecrire.');
        $this->assertSame('open', $opportunity->status);
    }

    // ─── Les cas qui ne doivent PAS etre bloques ────────────────────────────

    /**
     * `lost` EXIGE une raison (`required_if`). Si `prohibited_if` se
     * declenchait aussi sur `lost`, les deux regles se contrediraient et il
     * deviendrait impossible de perdre une affaire.
     */
    public function test_a_lost_reason_on_a_lost_deal_is_accepted(): void
    {
        $validator = $this->validate([
            'stage' => 'lost',
            'lost_reason' => 'Budget gele cote client',
        ]);

        $this->assertFalse($validator->fails(), (string) $validator->errors()->first('lost_reason'));
    }

    /**
     * Sur une etape ouverte, `lost_reason` est ignoree — pas interdite. Un
     * client qui renvoie le formulaire complet avec une raison heritee d'un
     * brouillon ne doit pas etre bloque.
     */
    public function test_a_lost_reason_on_an_open_stage_is_not_blocked(): void
    {
        foreach (['prospecting', 'qualification', 'proposal', 'negotiation'] as $stage) {
            $validator = $this->validate([
                'stage' => $stage,
                'lost_reason' => 'reste du brouillon',
            ]);

            $this->assertFalse(
                $validator->fails(),
                "L'etape ouverte `{$stage}` ne doit pas refuser `lost_reason`.",
            );
        }
    }

    /**
     * Sans raison du tout sur `lost`, c'est `required_if` qui parle — pas
     * `prohibited_if`. Les deux messages doivent rester distincts.
     */
    public function test_a_lost_deal_without_a_reason_still_fails_on_required_if(): void
    {
        $validator = $this->validate(['stage' => 'lost']);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('crm.opportunities.lost_reason_required'),
            (string) $validator->errors()->first('lost_reason'),
        );
    }
}
