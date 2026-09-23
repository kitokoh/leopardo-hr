<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7984 (audit 2026-09-20, point 3) — soft deletes CRM morts.
 *
 * Les migrations 2026_08_28_000101/000102 portent `deleted_at` sur
 * `crm_leads` et `crm_opportunities` mais les modèles n'utilisaient pas le
 * trait `SoftDeletes` : DELETE physique, `deleted_at` jamais renseigné.
 * Ces tests verrouillent le comportement attendu (rouges sans le trait).
 */
final class CrmSoftDeletesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_deleting_a_lead_is_a_soft_delete(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        app()->instance('current_company', $company);

        $lead = CrmLead::query()->create([
            'first_name' => 'Amina',
            'last_name' => 'B.',
            'email' => 'amina@example.test',
        ]);

        $lead->delete();

        $this->assertSoftDeleted('crm_leads', ['id' => $lead->id]);
        $this->assertNull(
            CrmLead::query()->whereKey($lead->id)->first(),
            'Un lead supprimé ne doit plus apparaître dans les requêtes par défaut.'
        );
        $this->assertNotNull(
            CrmLead::withTrashed()->whereKey($lead->id)->first(),
            'Un lead supprimé doit rester accessible via withTrashed().'
        );
    }

    public function test_deleting_an_opportunity_is_a_soft_delete(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        app()->instance('current_company', $company);

        $opportunity = CrmOpportunity::query()->create([
            'name' => 'Renouvellement licence',
            'stage' => 'prospecting',
        ]);

        $opportunity->delete();

        $this->assertSoftDeleted('crm_opportunities', ['id' => $opportunity->id]);
        $this->assertNull(
            CrmOpportunity::query()->whereKey($opportunity->id)->first(),
            'Une opportunité supprimée ne doit plus apparaître dans les requêtes par défaut.'
        );
        $this->assertNotNull(
            CrmOpportunity::withTrashed()->whereKey($opportunity->id)->first(),
            'Une opportunité supprimée doit rester accessible via withTrashed().'
        );
    }

    public function test_a_trashed_lead_can_be_restored(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        app()->instance('current_company', $company);

        $lead = CrmLead::query()->create([
            'first_name' => 'Karim',
            'last_name' => 'Z.',
        ]);

        $lead->delete();

        /** @var CrmLead $trashed */
        $trashed = CrmLead::withTrashed()->whereKey($lead->id)->firstOrFail();
        $trashed->restore();

        $this->assertNotNull(
            CrmLead::query()->whereKey($lead->id)->first(),
            'Un lead restauré doit réapparaître dans les requêtes par défaut.'
        );
    }
}
