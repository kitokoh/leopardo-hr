<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Golden;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\AccountingDashboardService;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC22 (ANALYTICS, #5898) — invariants des read models.
 *
 * Backlog BC-22 : « deux recalculs produisent le même résultat » et « les
 * dashboards n'utilisent pas de jointures profondes transactionnelles ».
 *
 * Le read model `AccountingDashboardService::summary()` est une agrégation
 * de pilotage (lecture seule, scopée company_id) : ce test verrouille
 *  - la DÉTERMINISME : deux recalculs successifs → résultats identiques ;
 *  - les MONTANTS CALCULÉS À LA MAIN (méthodologie golden) ;
 *  - l'ISOLATION tenant : les données d'un autre tenant n'altèrent jamais le
 *    read model du tenant courant.
 */
class GoldenDashboardRecomputeTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'DZD']);

        return $company;
    }

    private function contact(Company $company, string $type, string $email): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => $type,
            'name' => 'Contact '.$type,
            'email' => $email,
        ]);

        return $contact;
    }

    private function document(
        Company $company,
        AccountingContact $contact,
        string $type,
        string $date,
        float $ht,
        float $tax,
        string $number,
    ): AccountingDocument {
        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => $number,
            'status' => 'sent',
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

    private function payment(Company $company, AccountingDocument $document, float $amount, string $date): AccountingPayment
    {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => 'bank_transfer',
            'received_at' => $date,
            'status' => 'recorded',
        ]);

        return $payment;
    }

    public function test_golden_dashboard_recompute_is_deterministic(): void
    {
        $company = $this->company();
        $service = app(AccountingDashboardService::class);

        // Jeu de données déterministe (calculs manuels) :
        //   FAC-1  client  HT 10 000 + TVA 1 900 = TTC 11 900  (émise le 08-05)
        //   FAC-2  client  HT  2 000 + TVA   380 = TTC  2 380  (émise le 08-12)
        //   AV-1   fourn. HT  1 000 + TVA   190 = TTC  1 190  (émise le 08-14)
        //   PAIEMENT FAC-1 : 11 900 (reçu le 08-10)
        //   PAIEMENT FAC-2 :  2 380 (reçu le 08-15)
        // Période 2026-08 : invoices = 2 factures (14 280 TTC), expenses = 1 (1 190),
        // collections = 2 (14 280), outstanding = FAC-2 partiellement? non — FAC-2 payé.
        // → outstanding : aucune facture impayée (FAC-1 et FAC-2 payées en intégralité).
        $customer = $this->contact($company, 'customer', 'client@exemple.dz');
        $supplier = $this->contact($company, 'supplier', 'fournisseur@exemple.dz');
        $fac1 = $this->document($company, $customer, 'invoice', '2026-08-05', 10000.0, 1900.0, 'FAC-2026-0001');
        $fac2 = $this->document($company, $customer, 'invoice', '2026-08-12', 2000.0, 380.0, 'FAC-2026-0002');
        $av1 = $this->document($company, $supplier, 'invoice', '2026-08-14', 1000.0, 190.0, 'AV-2026-0001');
        $this->payment($company, $fac1, 11900.0, '2026-08-10');
        $this->payment($company, $fac2, 2380.0, '2026-08-15');

        $first = $service->summary((string) $company->id, '2026-08-01', '2026-08-31');

        // Montants calculés à la main.
        self::assertSame(2, $first['invoices']['count']);
        self::assertSame(14280.0, $first['invoices']['total_ttc']);
        self::assertSame(2, $first['collections']['count']);
        self::assertSame(14280.0, $first['collections']['total']);
        self::assertSame(1, $first['expenses']['count']);
        self::assertSame(1190.0, $first['expenses']['total_ttc']);

        // DÉTERMINISME : deux recalculs successifs → résultats identiques
        // (exigence backlog : « deux recalculs produisent le même résultat »).
        $second = $service->summary((string) $company->id, '2026-08-01', '2026-08-31');
        self::assertSame($first, $second);
    }

    public function test_dashboard_recompute_ignores_other_tenants(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $service = app(AccountingDashboardService::class);

        $customerA = $this->contact($companyA, 'customer', 'a@exemple.dz');
        $customerB = $this->contact($companyB, 'customer', 'b@exemple.dz');
        $this->document($companyA, $customerA, 'invoice', '2026-08-05', 10000.0, 1900.0, 'FAC-A-0001');
        $this->document($companyB, $customerB, 'invoice', '2026-08-05', 500.0, 95.0, 'FAC-B-0001');

        $summaryA = $service->summary((string) $companyA->id, '2026-08-01', '2026-08-31');

        // Le read model du tenant A ne voit QUE ses données (isolation).
        self::assertSame(1, $summaryA['invoices']['count']);
        self::assertSame(11900.0, $summaryA['invoices']['total_ttc']);

        // Déterminisme après l'ajout de données chez B : inchangé.
        $this->document($companyB, $customerB, 'invoice', '2026-08-06', 900.0, 171.0, 'FAC-B-0002');
        $summaryA2 = $service->summary((string) $companyA->id, '2026-08-01', '2026-08-31');
        self::assertSame($summaryA, $summaryA2);
    }
}
