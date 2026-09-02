<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5709 — comportement des modèles CRM client V0.
 *
 * Vérifie le scoping tenant automatique (BelongsToCompany), l'auto-fill de
 * `company_id` depuis le contexte tenant, les défauts portés par le schéma
 * (status/source du lead, stage de l'opportunité) et les drapeaux
 * gagnant/perdant des stages de pipeline.
 */
class CrmModelsTenantScopingTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_pipeline_is_invisible_from_another_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);

        $pipeline = CrmPipeline::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Ventes A',
            'stages' => ['prospecting', 'won', 'lost'],
        ]);

        app()->instance('current_company', $companyB);

        $this->assertNull(
            CrmPipeline::query()->whereKey($pipeline->id)->first(),
            'Le pipeline d’un autre tenant ne doit pas être visible.'
        );
    }

    public function test_company_id_is_autofilled_from_tenant_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        app()->instance('current_company', $company);

        $pipeline = CrmPipeline::query()->create([
            'name' => 'Ventes auto',
            'stages' => ['prospecting'],
        ]);

        $this->assertSame($company->id, $pipeline->company_id);
    }

    public function test_lead_defaults_and_full_name(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $lead = CrmLead::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $lead->refresh();

        $this->assertSame(CrmLeadStatus::New->value, $lead->status);
        $this->assertSame('manual', $lead->source);
        $this->assertSame('Jean Dupont', $lead->getFullNameAttribute());
    }

    public function test_opportunity_is_scoped_and_stage_defaults(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);

        $opportunity = CrmOpportunity::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Deal Alpha',
        ]);
        $opportunity->refresh();

        $this->assertSame('prospecting', $opportunity->stage);

        app()->instance('current_company', $companyB);

        $this->assertNull(
            CrmOpportunity::query()->whereKey($opportunity->id)->first(),
            'L’opportunité d’un autre tenant ne doit pas être visible.'
        );
    }

    public function test_pipeline_stage_tenant_scoping_and_win_flags(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);

        $pipeline = CrmPipeline::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Pipeline',
            'stages' => ['prospecting', 'won', 'lost'],
        ]);

        $wonStage = CrmPipelineStage::query()->create([
            'company_id' => $companyA->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Gagné',
            'position' => 0,
            'is_won' => true,
        ]);
        $lostStage = CrmPipelineStage::query()->create([
            'company_id' => $companyA->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Perdu',
            'position' => 1,
            'is_lost' => true,
        ]);

        $this->assertTrue($wonStage->is_won);
        $this->assertFalse($wonStage->is_lost);
        $this->assertTrue($lostStage->is_lost);
        $this->assertFalse($lostStage->is_won);

        app()->instance('current_company', $companyB);

        $this->assertNull(
            CrmPipelineStage::query()->whereKey($wonStage->id)->first(),
            'Le stage d’un autre tenant ne doit pas être visible.'
        );
    }
}
