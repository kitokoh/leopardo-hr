<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Golden;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-007 (#5865) — Golden tests « écritures comptables ».
 *
 * Même méthodologie que les golden tests paie (GoldenDzPayrollTest) : chaque
 * montant attendu est CALCULÉ À LA MAIN (plan de comptes PCF/SYSCOHADA
 * simplifié, issue #5234 — docs/architecture/COMPTABILITE_CONCEPTION.md) :
 *
 *   - 411  Clients (créances)            - 70  Ventes de produits
 *   - 709  Rabais, remises et ristournes - 4457 TVA collectée
 *   - 512  Banques / 53 Caisse (trésorerie)
 *
 * Règles vérifiées (invariants) :
 *   1. Toute écriture est équilibrée (Σ débit = Σ crédit) — sinon
 *      `UnbalancedJournalEntryException`.
 *   2. Facture : 411 D (TTC) / 70 C (HT) / 4457 C (TVA) — montants
 *      reproductibles au centime.
 *   3. Paiement : 512 ou 53 D (montant) / 411 C (montant).
 *   4. Avoir : 709 D (HT) / 4457 D (TVA) / 411 C (TTC).
 *   5. Re-posting idempotent : jamais de doublon, totaux identiques
 *      (snapshot reproductible).
 *   6. Période clôturée = figée : tout posting dans une période close lève
 *      `PeriodClosedException`, les totaux restent inchangés.
 *
 * Ces tests ne dépendent d'aucune valeur reprise du code : une divergence
 * entre le calcul manuel et l'implémentation = régression de conformité.
 */
class GoldenJournalPostingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function contact(Company $company, string $email = 'golden@exemple.dz'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Golden',
            'email' => $email,
        ]);

        return $contact;
    }

    private function document(
        Company $company,
        string $type = 'invoice',
        string $status = 'sent',
        string $date = '2026-08-05',
        float $ht = 10000.0,
        float $tax = 1900.0,
        string $number = 'FAC-2026-9001',
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

    private function payment(Company $company, AccountingDocument $document, float $amount, string $method = 'bank_transfer', string $date = '2026-08-10'): AccountingPayment
    {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => null,
            'received_at' => $date,
            'status' => PaymentStatus::Recorded->value,
        ]);

        return $payment;
    }

    /** @return array<int, array{account_code: string, debit: float, credit: float}> */
    private function linesOf(int $sourceId, string $sourceType = 'document'): array
    {
        return AccountingJournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('account_code')
            ->get()
            ->map(static fn (AccountingJournalEntry $entry): array => [
                'account_code' => $entry->account_code,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
            ])
            ->values()
            ->all();
    }

    // ── Invariant 1+2 : facture équilibrée, montants calculés à la main ──────

    public function test_golden_invoice_posting_is_balanced_and_reproducible(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Calcul manuel (PCF simplifié, TVA 19 %) :
        //   HT 10 000,00 → TVA 19 % = 1 900,00 → TTC 11 900,00
        //   411 Clients    débit  11 900,00
        //   70  Ventes     crédit 10 000,00
        //   4457 TVA coll. crédit  1 900,00
        //   Σ débit = Σ crédit = 11 900,00
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 10000.0, 1900.0);
        $service = app(JournalPostingService::class);

        self::assertSame(3, $service->postDocument($invoice));

        $lines = $this->linesOf($invoice->id);
        self::assertSame('411', $lines[0]['account_code']);
        self::assertSame(11900.0, $lines[0]['debit']);
        self::assertSame(0.0, $lines[0]['credit']);

        self::assertSame('4457', $lines[1]['account_code']);
        self::assertSame(0.0, $lines[1]['debit']);
        self::assertSame(1900.0, $lines[1]['credit']);

        self::assertSame('70', $lines[2]['account_code']);
        self::assertSame(0.0, $lines[2]['debit']);
        self::assertSame(10000.0, $lines[2]['credit']);

        // Reproducibilité : re-posting idempotent, aucune écriture dupliquée.
        self::assertSame(3, $service->postDocument($invoice));
        self::assertCount(3, $this->linesOf($invoice->id));

        // Totaux de période stables et équilibrés.
        $totals = $service->totalsForPeriod('2026-08');
        self::assertSame(11900.0, $totals['debit']);
        self::assertSame(11900.0, $totals['credit']);
        self::assertTrue($service->isPeriodBalanced('2026-08'));
    }

    // ── Invariant 3 : paiement = mouvement de trésorerie ─────────────────────

    public function test_golden_payment_posts_cash_movement(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Calcul manuel : encaissement banque de la facture 11 900,00
        //   512 Banques   débit  11 900,00
        //   411 Clients   crédit 11 900,00
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 10000.0, 1900.0);
        $service = app(JournalPostingService::class);
        $service->postDocument($invoice);
        $payment = $this->payment($company, $invoice, 11900.0, 'bank_transfer', '2026-08-10');

        self::assertSame(2, $service->postPayment($payment));

        $lines = $this->linesOf($payment->id, 'payment');
        self::assertSame('411', $lines[0]['account_code']);
        self::assertSame(0.0, $lines[0]['debit']);
        self::assertSame(11900.0, $lines[0]['credit']);

        self::assertSame('512', $lines[1]['account_code']);
        self::assertSame(11900.0, $lines[1]['debit']);
        self::assertSame(0.0, $lines[1]['credit']);

        // Le paiement cash passe par 53 Caisse.
        $cashPayment = $this->payment($company, $invoice, 500.0, 'cash', '2026-08-11');
        $service->postPayment($cashPayment);
        $cashLines = $this->linesOf($cashPayment->id, 'payment');
        self::assertSame('53', $cashLines[1]['account_code']);
        self::assertSame(500.0, $cashLines[1]['debit']);
    }

    // ── Invariant 4 : avoir = contrepassation équilibrée ─────────────────────

    public function test_golden_credit_note_reverses_balanced(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Calcul manuel (avoir HT 2 000,00, TVA 19 % = 380,00, TTC 2 380,00) :
        //   709 Rabais    débit  2 000,00
        //   4457 TVA coll. débit   380,00
        //   411 Clients   crédit 2 380,00
        $creditNote = $this->document($company, 'credit_note', 'sent', '2026-08-06', 2000.0, 380.0, 'AV-2026-9001');
        $service = app(JournalPostingService::class);

        self::assertSame(3, $service->postDocument($creditNote));

        $lines = $this->linesOf($creditNote->id);
        self::assertSame('411', $lines[0]['account_code']);
        self::assertSame(0.0, $lines[0]['debit']);
        self::assertSame(2380.0, $lines[0]['credit']);

        self::assertSame('4457', $lines[1]['account_code']);
        self::assertSame(380.0, $lines[1]['debit']);
        self::assertSame(0.0, $lines[1]['credit']);

        self::assertSame('709', $lines[2]['account_code']);
        self::assertSame(2000.0, $lines[2]['debit']);
        self::assertSame(0.0, $lines[2]['credit']);

        self::assertTrue($service->isPeriodBalanced('2026-08'));
    }

    // ── Invariant 5 : arrondis aux centimes reproductibles ───────────────────

    public function test_golden_rounding_is_reproducible_to_the_centime(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Calcul manuel avec arrondi : HT 333,33 ; TVA 19 % = 63,3327
        // → arrondie 63,33 ; TTC = 333,33 + 63,33 = 396,66
        //   411 débit 396,66 / 70 crédit 333,33 / 4457 crédit 63,33
        //   Σ débit = Σ crédit = 396,66 (tolérance 0,005)
        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-07', 333.33, 63.33, 'FAC-2026-9002');
        $service = app(JournalPostingService::class);

        self::assertSame(3, $service->postDocument($invoice));

        $lines = $this->linesOf($invoice->id);
        self::assertSame(396.66, $lines[0]['debit']);
        self::assertSame(63.33, $lines[1]['credit']);
        self::assertSame(333.33, $lines[2]['credit']);

        // Snapshot reproductible : re-posting → totaux identiques, 3 lignes.
        $service->postDocument($invoice);
        self::assertCount(3, $this->linesOf($invoice->id));
        $totals = $service->totalsForPeriod('2026-08');
        self::assertSame(396.66, $totals['debit']);
        self::assertSame(396.66, $totals['credit']);
    }

    // ── Invariant 6 : clôture de période = immutabilité (snapshot figé) ──────

    public function test_golden_closed_period_is_immutable(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 10000.0, 1900.0);
        $service = app(JournalPostingService::class);
        $service->postDocument($invoice);

        $snapshotBefore = $service->totalsForPeriod('2026-08');

        // Clôture de la période 2026-08.
        $closed = $service->closePeriod('2026-08', 'golden-test');
        self::assertInstanceOf(AccountingClosedPeriod::class, $closed);
        self::assertTrue($service->isPeriodClosed('2026-08'));

        // Tout posting daté dans la période close est refusé.
        $lateInvoice = $this->document($company, 'invoice', 'sent', '2026-08-28', 5000.0, 950.0, 'FAC-2026-9003');
        try {
            $service->postDocument($lateInvoice);
            self::fail('le posting dans une période clôturée doit lever PeriodClosedException');
        } catch (PeriodClosedException $e) {
            self::assertSame('PERIOD_CLOSED', $e->errorCode());
        }

        // Le snapshot (totaux) reste figé — aucune écriture ajoutée.
        $snapshotAfter = $service->totalsForPeriod('2026-08');
        self::assertSame($snapshotBefore, $snapshotAfter);
        self::assertCount(0, $this->linesOf($lateInvoice->id));
    }

    // ── Snapshot multi-écritures reproductible (période complète) ────────────

    public function test_golden_period_snapshot_is_reproducible(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Calcul manuel cumulé (2026-08) :
        //   FAC-9001 : 411 D 11 900 / 70 C 10 000 / 4457 C 1 900
        //   AV-9001  : 709 D 2 000 / 4457 D 380 / 411 C 2 380
        //   PAY-1    : 512 D 11 900 / 411 C 11 900
        //   Totaux   : débit  = 11 900 + 2 000 + 380 + 11 900 = 26 180
        //              crédit = 10 000 + 1 900 + 2 380 + 11 900 = 26 180
        $service = app(JournalPostingService::class);

        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 10000.0, 1900.0, 'FAC-2026-9001');
        $creditNote = $this->document($company, 'credit_note', 'sent', '2026-08-06', 2000.0, 380.0, 'AV-2026-9001');
        $service->postDocument($invoice);
        $service->postDocument($creditNote);
        $payment = $this->payment($company, $invoice, 11900.0, 'bank_transfer', '2026-08-10');
        $service->postPayment($payment);

        $snapshot = $service->totalsForPeriod('2026-08');
        self::assertSame(26180.0, $snapshot['debit']);
        self::assertSame(26180.0, $snapshot['credit']);

        // Reproductibilité : re-poster tout (MÊMES objets) → snapshot
        // identique, zéro doublon.
        $service->postDocument($invoice);
        $service->postDocument($creditNote);
        $service->postPayment($payment);

        self::assertSame($snapshot, $service->totalsForPeriod('2026-08'));
        self::assertSame(3, AccountingJournalEntry::query()->where('source_type', 'document')->where('source_id', $invoice->id)->count());
        self::assertTrue($service->isPeriodBalanced('2026-08'));
    }
}
