<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7646 — écriture cross-tenant par mass assignment de `company_id`.
 *
 * Le hook `creating` de BelongsToCompany ne remplissait `company_id` que
 * s'il était vide : tout payload validé laissant passer `company_id`
 * permettait d'écrire dans un autre tenant (~270 modèles l'exposent en
 * $fillable). Ces tests verrouillent le nouveau contrat :
 *   - tenant actif → `company_id` est FORCÉ depuis le tenant courant, toute
 *     valeur différente fournie est écrasée et journalisée (spoof) ;
 *   - tenant actif + `company_id` absent → auto-rempli (inchangé) ;
 *   - modèles CRM → `company_id` n'est plus mass-assignable du tout
 *     ($fillable explicite, plus de $guarded = []) ;
 *   - hors contexte tenant (jobs/CLI/seeders) → comportement permissif
 *     inchangé, la valeur fournie est conservée.
 */
class TenantCompanyIdSpoofingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    public function test_spoofed_company_id_is_overwritten_by_active_tenant(): void
    {
        app()->instance('current_company', $this->companyA);
        $log = Log::spy();

        // AbsenceType garde `company_id` dans son $fillable (comme ~270
        // modèles du dépôt) : c'est exactement le vecteur de l'issue.
        /** @var AbsenceType $absenceType */
        $absenceType = AbsenceType::query()->create([
            'company_id' => $this->companyB->id, // spoof : tenant B visé
            'name' => 'Congé payé',
            'code' => 'CP-SPOOF',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $this->assertSame($this->companyA->id, $absenceType->company_id);
        $this->assertDatabaseHas('absence_types', [
            'code' => 'CP-SPOOF',
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('absence_types', [
            'code' => 'CP-SPOOF',
            'company_id' => $this->companyB->id,
        ]);

        // La tentative de spoof est journalisée (#7646).
        $log->shouldHaveReceived('warning', [
            Mockery::on(static fn (string $message): bool => str_contains($message, '#7646')),
            Mockery::on(fn (array $context): bool => ($context['provided_company_id'] ?? null) === $this->companyB->id
                && ($context['tenant_company_id'] ?? null) === $this->companyA->id),
        ]);
    }

    public function test_company_id_is_autofilled_when_absent_under_active_tenant(): void
    {
        app()->instance('current_company', $this->companyA);
        $log = Log::spy();

        /** @var AbsenceType $absenceType */
        $absenceType = AbsenceType::query()->create([
            'name' => 'Congé sans solde',
            'code' => 'CSS-AUTO',
            'is_paid' => false,
            'deducts_leave' => false,
            'requires_proof' => false,
        ]);

        $this->assertSame($this->companyA->id, $absenceType->company_id);
        // Auto-remplissage nominal : aucun spoof, donc aucun warning.
        $log->shouldNotHaveReceived('warning');
    }

    public function test_crm_model_spoofed_company_id_is_neither_fillable_nor_kept(): void
    {
        app()->instance('current_company', $this->companyA);

        // #7646 — les modèles CRM n'ont plus $guarded = [] : `company_id`
        // est hors $fillable (valeur écartée) ET le hook force le tenant.
        /** @var CrmChannel $channel */
        $channel = CrmChannel::query()->create([
            'company_id' => $this->companyB->id, // spoof : écarté + forcé
            'type' => 'whatsapp',
            'provider' => 'whatsapp_cloud_api',
            'status' => 'active',
            'is_configured' => true,
        ]);

        $this->assertSame($this->companyA->id, $channel->company_id);
        $this->assertDatabaseHas('crm_channels', [
            'id' => $channel->id,
            'company_id' => $this->companyA->id,
        ]);
    }

    public function test_provided_company_id_is_kept_outside_tenant_context(): void
    {
        // Hors contexte tenant (jobs/CLI/seeders/fixtures) : le comportement
        // permissif est conservé — la valeur fournie reste telle quelle.
        $this->assertFalse(app()->bound('current_company'));

        /** @var AbsenceType $absenceType */
        $absenceType = AbsenceType::query()->create([
            'company_id' => $this->companyB->id,
            'name' => 'Congé maladie',
            'code' => 'CM-CLI',
            'is_paid' => true,
            'deducts_leave' => false,
            'requires_proof' => true,
        ]);

        $this->assertSame($this->companyB->id, $absenceType->company_id);
        $this->assertDatabaseHas('absence_types', [
            'code' => 'CM-CLI',
            'company_id' => $this->companyB->id,
        ]);
    }
}
