<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5709 — comportement des modèles CRM client V0.
 *
 * Vérifie le scoping tenant automatique (BelongsToCompany) et les
 * comportements métier portés par les modèles (constants, transition de
 * conversion lead → opportunité, statut won/lost dérivé du stage).
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

        $pipeline = CrmPipeline::query()->create(['name' => 'Ventes auto']);

        $this->assertSame($company->id, $pipeline->company_id);
    }

    public function test_lead_constants_and_conversion_transition(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $lead = CrmLead::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);

        $this->assertSame(CrmLead::STATUS_NEW, $lead->status);
        $this->assertSame(CrmLead::PRIORITY_MEDIUM, $lead->priority);

        $lead->markConverted(42);
        $lead->save();

        $this->assertSame(CrmLead::STATUS_CONVERTED, $lead->status);
        $this->assertSame(42, $lead->converted_opportunity_id);
        $this->assertNotNull($lead->converted_at);
    }

    public function test_opportunity_won_lost_derived_from_stage(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $pipeline = CrmPipeline::query()->create([
            'company_id' => $company->id,
            'name' => 'Pipeline',
        ]);
        $wonStage = CrmPipelineStage::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Gagné',
            'position' => 0,
            'is_won' => true,
        ]);
        $lostStage = CrmPipelineStage::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Perdu',
            'position' => 1,
            'is_lost' => true,
        ]);

        $won = CrmOpportunity::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $wonStage->id,
            'name' => 'Affaire gagnée',
        ]);
        $lost = CrmOpportunity::query()->create([
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $lostStage->id,
            'name' => 'Affaire perdue',
        ]);

        $this->assertTrue($won->isWon());
        $this->assertFalse($won->isLost());
        $this->assertTrue($lost->isLost());
        $this->assertFalse($lost->isWon());
    }
}
