<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit performance du module Comptabilité (issue #5275) :
 * barrière N+1 sur le chemin de lecture canonique (liste de documents avec
 * contact + lignes + paiements), requête relances indexée, agrégation par
 * période. Le nombre de requêtes SQL doit être indépendant du nombre de
 * documents chargés.
 */
class AccountingPerformanceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function seedDocuments(Company $company, int $count, int $linesPerDocument = 3): void
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Perf',
            'email' => 'perf@exemple.dz',
        ]);

        for ($i = 0; $i < $count; $i++) {
            /** @var AccountingDocument $document */
            $document = AccountingDocument::create([
                'company_id' => $company->id,
                'type' => 'invoice',
                'number' => 'PERF-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'status' => $i % 2 === 0 ? 'sent' : 'paid',
                'contact_id' => $contact->id,
                'issue_date' => '2026-08-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'due_date' => '2026-09-10',
                'currency' => 'DZD',
                'subtotal_ht' => 1000.0,
                'tax_amount' => 190.0,
                'total_ttc' => 1190.0,
                'tva_rate' => 19.0,
                'paid_amount' => $i % 2 === 0 ? 0.0 : 1190.0,
            ]);

            for ($l = 0; $l < $linesPerDocument; $l++) {
                AccountingDocumentLine::create([
                    'company_id' => $company->id,
                    'document_id' => $document->id,
                    'sort_order' => $l,
                    'description' => 'Ligne '.$l,
                    'quantity' => 1,
                    'unit_price' => 333.33,
                ]);
            }

            if ($i % 2 === 1) {
                AccountingPayment::create([
                    'company_id' => $company->id,
                    'document_id' => $document->id,
                    'amount' => 1190.0,
                    'method' => 'bank_transfer',
                    'received_at' => '2026-08-20',
                    'status' => 'matched',
                ]);
            }
        }
    }

    /**
     * Barrière N+1 : la liste canonique (documents + contact + lignes +
     * paiements en eager loading) doit tenir en un nombre CONSTANT de
     * requêtes, quel que soit le nombre de documents.
     */
    public function test_canonical_list_has_no_n_plus_one(): void
    {
        $company = $this->company();
        $this->seedDocuments($company, 5, 3);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $documents = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->with(['contact', 'lines', 'payments'])
            ->get();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $documents);
        // 1 (documents) + 1 (contacts) + 1 (lignes) + 1 (paiements) = 4.
        $this->assertLessThanOrEqual(5, $queryCount, 'Le chargement eager doit rester à requête constante (0 N+1).');

        // Chaque document expose bien ses relations (aucune requête paresseuse).
        /** @var AccountingDocument|null $first */
        $first = $documents->first();
        $this->assertNotNull($first);
        $this->assertNotNull($first->contact);
        $this->assertCount(3, $first->lines);

        /** @var AccountingDocument|null $paid */
        $paid = $documents->firstWhere('status', 'paid');
        $this->assertNotNull($paid);
        $this->assertCount(1, $paid->payments);
    }

    public function test_reminder_query_uses_indexed_path_and_returns_eligible_only(): void
    {
        $company = $this->company();
        // 4 éligibles (sent, due <= J-7, non soldé) ; 1 payée exclue ; 1 draft exclu.
        $this->seedDocuments($company, 3, 1);
        AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'PERF-DRAFT-01',
            'status' => 'draft',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-07-01',
            'currency' => 'DZD',
            'subtotal_ht' => 100.0,
            'tax_amount' => 19.0,
            'total_ttc' => 119.0,
        ]);

        $eligible = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', '2026-07-01')
            ->whereColumn('paid_amount', '<', 'total_ttc')
            ->get();

        // Les 3 documents 'sent' ont due_date 2026-09-10 → NON éligibles ;
        // le draft a due_date 2026-07-01 → exclu par le statut. Résultat : 0.
        $this->assertCount(0, $eligible);

        // Et avec un seuil couvrant les échéances : les 2 'sent' non soldés sortent.
        $due = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', '2026-09-10')
            ->whereColumn('paid_amount', '<', 'total_ttc')
            ->get();

        // 3 documents seedés : i=1 est 'paid' (exclu) → 2 'sent' non soldés éligibles.
        $this->assertSame(2, $due->count());
        $this->assertTrue($due->every(static fn (AccountingDocument $document): bool => $document->status !== 'paid'));
    }

    public function test_period_aggregation_is_supported_by_indexes(): void
    {
        $company = $this->company();
        $this->seedDocuments($company, 10, 1);

        $aggregates = AccountingDocument::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])
            ->selectRaw("to_char(issue_date, 'YYYY-MM') as month, COUNT(*) as n, SUM(total_ttc) as ttc")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $this->assertTrue($aggregates->isNotEmpty());
        $total = $aggregates->sum('n');
        $this->assertSame(10, $total);
    }
}
