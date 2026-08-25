<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Infrastructure\Services\ChartOfAccountsService;
use App\Modules\Accounting\Infrastructure\Services\FinancialStatementService;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * États financiers (issue #5422) : bilan annuel et compte de résultat par
 * période, agrégés depuis le journal des écritures (#5234).
 *
 * Les routes `/api/v1/accounting/statements/*` sont ajoutées au fichier de
 * routes du module dans le même lot ; ce test enregistre localement les deux
 * endpoints avec le même middleware RBAC (principal/comptable) pour rester
 * autonome tant que le wiring n'est pas mergé.
 *
 * Fixtures : mêmes helpers que AccountingJournalTest (company/bindCompany/
 * contact/document via AccountingDocument + JournalPostingService). Le plan
 * comptable par défaut est provisionné (ChartOfAccountsService) comme en
 * production — les codes hors plan (6599, 1651) exercent le repli sans plan.
 *
 * Les totaux sont comparés en `assertEquals` (les valeurs décimales entières
 * traversent le JSON en int — ex. 1190.0 → 1190).
 */
class FinancialStatementTest extends TestCase
{
    use RefreshTenantDatabase;


    private function company(string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $country === 'MA' ? 'MAD' : 'DZD']);

        $this->bindCompany($company);
        // Même contrat qu'en production : le plan est provisionné à la
        // création de l'entreprise (ProvisionChartOfAccounts).
        app(ChartOfAccountsService::class)->ensureProvisioned($company->id);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function forgetCompany(): void
    {
        app()->forgetInstance('current_company');
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function contact(Company $company, string $email = 'client@exemple.dz'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => $email,
        ]);

        return $contact;
    }

    private function document(
        Company $company,
        string $type = 'invoice',
        string $status = 'sent',
        string $date = '2026-08-05',
        float $ht = 1000.0,
        float $tax = 190.0,
        string $number = 'FAC-2026-0001',
    ): AccountingDocument {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => $number,
            'status' => $status,
            'contact_id' => $contact->id,
            'issue_date' => $date,
            'currency' => 'DZD',
            'subtotal_ht' => $ht,
            'tax_amount' => $tax,
            'total_ttc' => $ht + $tax,
            'tva_rate' => $tax > 0 ? round($tax / $ht * 100, 2) : null,
        ]);

        return $document;
    }

    /**
     * Écriture manuelle (paire équilibrée) — exerce le chemin hors
     * JournalPostingService pour des comptes du plan et hors plan.
     */
    private function manualEntry(Company $company, string $code, string $label, string $date, float $debit, float $credit): void
    {
        AccountingJournalEntry::create([
            'company_id' => $company->id,
            'entry_date' => $date,
            'period' => substr($date, 0, 7),
            'source_type' => 'manual',
            'source_id' => 1,
            'account_code' => $code,
            'account_label' => $label,
            'debit' => $debit,
            'credit' => $credit,
            'piece' => 'MAN-'.$code,
        ]);
    }

    /**
     * Sections d'un côté du bilan (actif/passif/capitaux_propres) depuis le
     * payload décodé de la réponse.
     *
     * @return list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}>
     */
    private function sections(mixed $payload, string $path): array
    {
        $value = is_array($payload) ? ($payload[$path] ?? []) : [];

        return is_array($value) ? array_values($value) : [];
    }

    // ── (a) Bilan d'une année avec une facture ──────────────────────────────

    public function test_balance_sheet_year_with_invoice(): void
    {
        $company = $this->company();
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company));

        $response = $this->getJson('/api/v1/accounting/statements/balance-sheet?year=2026');
        $response->assertOk();
        $response->assertJsonPath('meta.year', 2026);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Totaux : actif (411) = passif (4457) + résultat (70).
        $this->assertEquals(1190.0, $data['total_actif']);
        $this->assertEquals(1190.0, $data['total_passif']);
        $this->assertEquals(1000.0, $data['resultat_net']);
        $this->assertTrue($data['balanced']);

        // 411 dans la section Créances de l'actif.
        $creances = collect($this->sections($data, 'actif'))->firstWhere('section', 'Créances');
        $this->assertIsArray($creances);
        $this->assertEquals(1190.0, $creances['total']);
        $this->assertSame('411', $creances['accounts'][0]['code']);
        $this->assertEquals(1190.0, $creances['accounts'][0]['balance']);

        // TVA collectée au passif (dettes tiers), résultat dans les capitaux.
        $dettes = collect($this->sections($data, 'passif'))->firstWhere('section', 'Dettes fournisseurs/tiers');
        $this->assertIsArray($dettes);
        $this->assertEquals(190.0, $dettes['total']);
        $this->assertSame('4457', $dettes['accounts'][0]['code']);

        $resultat = collect($this->sections($data, 'capitaux_propres'))->firstWhere('section', 'Résultat de l\'exercice');
        $this->assertIsArray($resultat);
        $this->assertEquals(1000.0, $resultat['total']);
    }

    // ── (b) Compte de résultat cohérent avec les écritures ──────────────────

    public function test_income_statement_matches_entries_and_keeps_balance(): void
    {
        $company = $this->company();

        // Facture : 411 D 1190 / 70 C 1000 / 4457 C 190.
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        // Avoir : 411 C 119 / 4457 D 19 / 709 D 100 (vient en diminution des produits).
        $creditNote = $this->document($company, 'credit_note', 'sent', '2026-08-06', 100.0, 19.0, 'AVR-2026-0002');
        app(JournalPostingService::class)->postDocument($creditNote);
        // Paire salaires (plan) : 641 D 300 / 421 C 300.
        $this->manualEntry($company, '641', 'Salaires et appointements', '2026-08-15', 300.0, 0.0);
        $this->manualEntry($company, '421', 'Personnel — rémunérations dues', '2026-08-15', 0.0, 300.0);
        // Paire hors plan (repli sans plan) : 6599 D 50 / 1651 C 50.
        $this->manualEntry($company, '6599', 'Charges diverses (hors plan)', '2026-08-16', 50.0, 0.0);
        $this->manualEntry($company, '1651', 'Dépôts et cautionnements reçus (hors plan)', '2026-08-16', 0.0, 50.0);
        // Financier : 661 D 20 / 164 C 20, et 76 C 10 / 512 D 10.
        $this->manualEntry($company, '661', 'Intérêts des emprunts', '2026-08-17', 20.0, 0.0);
        $this->manualEntry($company, '164', 'Emprunts auprès des établissements de crédit', '2026-08-17', 0.0, 20.0);
        $this->manualEntry($company, '76', 'Produits financiers', '2026-08-18', 0.0, 10.0);
        $this->manualEntry($company, '512', 'Banques', '2026-08-18', 10.0, 0.0);
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company));

        $response = $this->getJson('/api/v1/accounting/statements/income-statement?period=2026-08');
        $response->assertOk();
        $response->assertJsonPath('meta.period', '2026-08');

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Produits : 70 → 1000, 709 → −100 (avoirs), 76 → 10 → total 910.
        $this->assertEquals(910.0, $data['produits']['total']);
        // Charges : 641 → 300, 6599 → 50, 661 → 20 → total 370.
        $this->assertEquals(370.0, $data['charges']['total']);
        // Résultat = produits − charges.
        $this->assertEquals(540.0, $data['resultat']);

        // Regroupement exploitation / financier / exceptionnel.
        $produitsSections = collect($data['produits']['sections'])->keyBy('section');
        $this->assertEquals(900.0, $produitsSections['Produits d\'exploitation']['total']);
        $this->assertEquals(10.0, $produitsSections['Produits financiers']['total']);
        $this->assertEquals(0.0, $produitsSections['Produits exceptionnels']['total']);

        $chargesSections = collect($data['charges']['sections'])->keyBy('section');
        $this->assertEquals(350.0, $chargesSections['Charges d\'exploitation']['total']);
        $this->assertEquals(20.0, $chargesSections['Charges financières']['total']);
        $this->assertEquals(0.0, $chargesSections['Charges exceptionnelles']['total']);

        // Montants par compte (le compte hors plan 6599 garde son intitulé de journal).
        $exploitation = $chargesSections['Charges d\'exploitation']['accounts'];
        $byCode = collect($exploitation)->keyBy('code');
        $this->assertEquals(300.0, $byCode['641']['amount']);
        $this->assertEquals(50.0, $byCode['6599']['amount']);
        $this->assertSame('Charges diverses (hors plan)', $byCode['6599']['label']);
        $this->assertEquals(-100.0, collect($produitsSections['Produits d\'exploitation']['accounts'])->keyBy('code')['709']['amount']);

        // Cohérence : le bilan de la même année reste équilibré.
        $sheet = $this->getJson('/api/v1/accounting/statements/balance-sheet?year=2026');
        $sheet->assertOk();
        $sheetData = $sheet->json('data');
        $this->assertIsArray($sheetData);
        $this->assertEquals(1081.0, $sheetData['total_actif']);
        $this->assertEquals(491.0, $sheetData['total_passif']);
        $this->assertEquals(540.0, $sheetData['resultat_net']);
        $this->assertTrue($sheetData['balanced']);

        // Sections : créances 411 → 1071, trésorerie 512 → 10, emprunts 164 → 20.
        $actifBySection = collect($this->sections($sheetData, 'actif'))->keyBy('section');
        $this->assertEquals(1071.0, $actifBySection['Créances']['total']);
        $this->assertEquals(10.0, $actifBySection['Trésorerie']['total']);
        $passifBySection = collect($this->sections($sheetData, 'passif'))->keyBy('section');
        $this->assertEquals(471.0, $passifBySection['Dettes fournisseurs/tiers']['total']);
        $this->assertEquals(20.0, $passifBySection['Emprunts']['total']);
        // Le compte hors plan 1651 (classe 1) est reclassé en capitaux propres.
        $capitauxBySection = collect($this->sections($sheetData, 'capitaux_propres'))->keyBy('section');
        $this->assertEquals(50.0, $capitauxBySection['Capitaux propres']['total']);
        $this->assertEquals(540.0, $capitauxBySection['Résultat de l\'exercice']['total']);
    }

    // ── (c) Année sans écritures ────────────────────────────────────────────

    public function test_empty_year_returns_zero_totals_and_balanced(): void
    {
        $company = $this->company();
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company));

        $response = $this->getJson('/api/v1/accounting/statements/balance-sheet?year=2026');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertEquals(0.0, $data['total_actif']);
        $this->assertEquals(0.0, $data['total_passif']);
        $this->assertEquals(0.0, $data['resultat_net']);
        $this->assertTrue($data['balanced']);

        // Les sections canoniques sont présentes, vides, avec total 0.
        $actif = collect($this->sections($data, 'actif'))->keyBy('section');
        $this->assertSame(['Immobilisations', 'Stocks', 'Créances', 'Trésorerie'], $actif->keys()->all());
        $this->assertSame([], $actif['Créances']['accounts']);
        $this->assertEquals(0.0, $actif['Immobilisations']['total']);

        $statement = $this->getJson('/api/v1/accounting/statements/income-statement?period=2026-08');
        $statement->assertOk();
        $statementData = $statement->json('data');
        $this->assertIsArray($statementData);
        $this->assertEquals(0.0, $statementData['produits']['total']);
        $this->assertEquals(0.0, $statementData['charges']['total']);
        $this->assertEquals(0.0, $statementData['resultat']);
    }

    // ── (d) Validation : période / année invalides → 422 ────────────────────

    public function test_invalid_period_and_year_return_422(): void
    {
        $company = $this->company();
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company));

        // Période mal formée : mois 13 et format non YYYY-MM.
        $this->getJson('/api/v1/accounting/statements/income-statement?period=2026-13')
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');
        $this->getJson('/api/v1/accounting/statements/income-statement?period=202608')
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');
        $this->getJson('/api/v1/accounting/statements/income-statement')
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');

        // Année hors bornes ou non numérique.
        $this->getJson('/api/v1/accounting/statements/balance-sheet?year=1999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');
        $this->getJson('/api/v1/accounting/statements/balance-sheet?year=2101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');
        $this->getJson('/api/v1/accounting/statements/balance-sheet?year=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');
        $this->getJson('/api/v1/accounting/statements/balance-sheet')
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');
    }

    // ── (e) Isolation tenant ────────────────────────────────────────────────

    public function test_statements_are_tenant_scoped(): void
    {
        $companyA = $this->company();
        $invoice = $this->document($companyA, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0);
        app(JournalPostingService::class)->postDocument($invoice);
        $this->forgetCompany();

        $companyB = $this->company('MA');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($companyB));

        // B ne voit aucune écriture de A : bilan vide et équilibré.
        $sheet = $this->getJson('/api/v1/accounting/statements/balance-sheet?year=2026');
        $sheet->assertOk();
        $data = $sheet->json('data');
        $this->assertIsArray($data);
        $this->assertEquals(0.0, $data['total_actif']);
        $this->assertEquals(0.0, $data['total_passif']);
        $this->assertEquals(0.0, $data['resultat_net']);
        $this->assertTrue($data['balanced']);

        // Aucun compte de A ne fuit dans les sections de B.
        foreach (['actif', 'passif'] as $side) {
            foreach ($this->sections($data, $side) as $section) {
                $this->assertSame([], $section['accounts']);
            }
        }

        // Compte de résultat de B : vide.
        $statement = $this->getJson('/api/v1/accounting/statements/income-statement?period=2026-08');
        $statement->assertOk();
        $statementData = $statement->json('data');
        $this->assertIsArray($statementData);
        $this->assertEquals(0.0, $statementData['produits']['total']);
        $this->assertEquals(0.0, $statementData['charges']['total']);
        $this->assertEquals(0.0, $statementData['resultat']);
    }

    // ── RBAC : réservé aux managers principal/comptable ─────────────────────

    public function test_statements_require_manager_role(): void
    {
        $company = $this->company();
        $this->forgetCompany();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/statements/balance-sheet?year=2026')->assertForbidden();
        $this->getJson('/api/v1/accounting/statements/income-statement?period=2026-08')->assertForbidden();
    }

    // ── Service direct : garde de période ───────────────────────────────────

    public function test_service_income_statement_guards_invalid_period(): void
    {
        $company = $this->company();

        $this->expectException(InvalidArgumentException::class);
        app(FinancialStatementService::class)->incomeStatement($company->id, '2026-13');
    }
}
