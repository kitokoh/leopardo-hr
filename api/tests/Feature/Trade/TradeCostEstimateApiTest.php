<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRADE-007 (#8482) — cout total rendu et aide a la preparation douaniere.
 *
 * Invariants verifies par le VRAI chemin HTTP :
 *  - total calcule SERVEUR et statut `complete` quand tous les postes attendus
 *    sont chiffres ;
 *  - poste `unavailable` : signale, EXCLU du total, estimation `incomplete` ;
 *  - sans taux de change, les postes en devise etrangere ne sont PAS convertis
 *    (aucun total silencieusement faux) ;
 *  - poste attendu manquant selon l'Incoterm -> `incomplete` ;
 *  - checklist douanier : documents, informations manquantes, et un taux de
 *    droits explicitement INDISPONIBLE (jamais un chiffre invente) ;
 *  - le disclaimer accompagne TOUJOURS la reponse.
 */
class TradeCostEstimateApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_a_complete_estimate_sums_all_expected_lines(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/cost-estimates', [
            'goods_value_minor' => 100_000_000,
            'goods_currency' => 'XAF',
            'display_currency' => 'XAF',
            'origin_country' => 'VN',
            'destination_country' => 'CM',
            'incoterm' => 'DDP',
            'lines' => [
                ['type' => 'international_freight', 'amount_minor' => 4_500_000, 'currency' => 'XAF', 'source' => 'confirmed'],
                ['type' => 'insurance', 'amount_minor' => 500_000, 'currency' => 'XAF', 'source' => 'estimated'],
                ['type' => 'customs_duty', 'amount_minor' => 10_000_000, 'currency' => 'XAF', 'source' => 'estimated'],
            ],
        ])->assertStatus(201);

        // 100 000 000 (achat) + 4 500 000 + 500 000 + 10 000 000
        self::assertSame(115_000_000, $response->json('data.total_minor'));
        $response->assertJsonPath('data.status', 'complete');
        $response->assertJsonPath('data.is_complete', true);

        // Le disclaimer voyage avec CHAQUE reponse.
        self::assertIsString($response->json('disclaimer'));
        self::assertStringContainsString('AIDE A LA PREPARATION', (string) $response->json('disclaimer'));
    }

    public function test_an_unavailable_line_is_excluded_from_the_total_and_flagged(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/cost-estimates', [
            'goods_value_minor' => 10_000_000,
            'goods_currency' => 'XAF',
            'display_currency' => 'XAF',
            'incoterm' => 'DDP',
            'lines' => [
                ['type' => 'international_freight', 'amount_minor' => 2_000_000, 'currency' => 'XAF', 'source' => 'confirmed'],
                ['type' => 'insurance', 'amount_minor' => 200_000, 'currency' => 'XAF', 'source' => 'confirmed'],
                // Poste non chiffre : le tarif n'a pas ete communique.
                ['type' => 'customs_duty', 'amount_minor' => 0, 'currency' => 'XAF', 'source' => 'unavailable', 'note' => 'Tarif non communique.'],
            ],
        ])->assertStatus(201);

        // Le poste indisponible ne contribue PAS au total : 10 000 000 + 2 000 000 + 200 000.
        self::assertSame(12_200_000, $response->json('data.total_minor'));
        // ... et l'estimation est annoncee comme INCOMPLETE, jamais comme un prix.
        $response->assertJsonPath('data.status', 'incomplete');

        $duty = collect($response->json('data.lines'))->firstWhere('type', 'customs_duty');
        self::assertSame('unavailable', $duty['source']);
        self::assertFalse($duty['counts_toward_total']);
    }

    public function test_without_a_rate_foreign_currency_lines_are_not_converted(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/cost-estimates', [
            'goods_value_minor' => 1_000_000,
            'goods_currency' => 'XAF',
            'display_currency' => 'XAF',
            'incoterm' => 'DDP',
            // Aucun bloc `fx` : le taux est inconnu.
            'lines' => [
                ['type' => 'international_freight', 'amount_minor' => 4_000, 'currency' => 'USD', 'source' => 'confirmed'],
                ['type' => 'insurance', 'amount_minor' => 100_000, 'currency' => 'XAF', 'source' => 'confirmed'],
            ],
        ])->assertStatus(201);

        // 1 000 000 + 100 000 : le poste USD est EXCLU faute de taux connu.
        self::assertSame(1_100_000, $response->json('data.total_minor'));
        $response->assertJsonPath('data.status', 'incomplete');
        self::assertNull($response->json('data.fx_rate'));
    }

    public function test_a_provided_rate_is_traced_and_applied(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/cost-estimates', [
            'goods_value_minor' => 0,
            'goods_currency' => 'XAF',
            'display_currency' => 'XAF',
            'incoterm' => 'DDP',
            'fx' => ['rate' => 600.0, 'date' => '2026-10-14', 'source' => 'manual'],
            'lines' => [
                ['type' => 'international_freight', 'amount_minor' => 4_000, 'currency' => 'USD', 'source' => 'confirmed'],
                ['type' => 'insurance', 'amount_minor' => 0, 'currency' => 'XAF', 'source' => 'confirmed'],
            ],
        ])->assertStatus(201);

        // 4 000 x 600 = 2 400 000.
        self::assertSame(2_400_000, $response->json('data.total_minor'));
        // La provenance du taux est conservee avec le chiffre.
        self::assertSame('manual', $response->json('data.fx_source'));
        self::assertSame('2026-10-14', $response->json('data.fx_rate_date'));
    }

    public function test_a_missing_expected_line_makes_the_estimate_incomplete(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->postJson('/api/v1/trade/cost-estimates', [
            'goods_value_minor' => 5_000_000,
            'goods_currency' => 'XAF',
            'display_currency' => 'XAF',
            // Sans fret ni assurance en DDP, le total ne peut pas passer pour final.
            'incoterm' => 'DDP',
            'lines' => [],
        ])->assertStatus(201);

        $response->assertJsonPath('data.status', 'incomplete');
        self::assertSame(5_000_000, $response->json('data.total_minor'));
    }

    public function test_the_customs_checklist_never_pretends_to_estimate_duties(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $response = $this->getJson('/api/v1/trade/customs/checklist?incoterm=CIF&transport_mode=sea&origin_country=VN&destination_country=CM&goods_value_minor=1000000')
            ->assertStatus(200);

        // Documents conventionnels derives de faits objectifs.
        $documents = $response->json('data.required_documents');
        self::assertContains('commercial_invoice', $documents);
        self::assertContains('packing_list', $documents);
        self::assertContains('bill_of_lading', $documents);
        self::assertContains('insurance_certificate', $documents);

        // Le code douanier manquant est SIGNALE (jamais devine).
        self::assertContains('hs_code', $response->json('data.missing_information'));
        $response->assertJsonPath('data.is_quotable', false);

        // ⚠️ Capacite INDISPONIBLE, declaree comme telle.
        $response->assertJsonPath('data.duty_rate_percent', null);
        $response->assertJsonPath('data.duty_rate_available', false);

        self::assertIsString($response->json('disclaimer'));
    }

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('trade', true);
        $company->save();

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
