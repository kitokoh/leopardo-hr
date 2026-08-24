<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed du benchmark de charge du module Comptabilité (issue #5275).
 *
 * Crée une entreprise dédiée (`benchmark-accounting-dz`) avec N documents
 * réalistes (statuts/échéances étalés, lignes, paiements) en inserts groupés
 * — sans événements Eloquent ni scope tenant (insertion brute DB pour la
 * vitesse ; le scope ne s'applique pas hors surface tenant).
 */
class AccountingBenchmarkSeeder extends Seeder
{
    /**
     * @param  int  $documents  nombre de documents (défaut 10000)
     * @return string slug de l'entreprise benchmark
     */
    public function run(int $documents = 10000): string
    {
        $slug = 'benchmark-accounting-dz';

        /** @var Company|null $existing */
        $existing = Company::query()->where('slug', $slug)->first();
        if ($existing instanceof Company) {
            return $slug;
        }

        // Factory : remplit toutes les colonnes NOT NULL de companies
        // (sector, timezone, etc.) — le reste du seed reste en inserts bruts.
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Benchmark Accounting DZ',
            'slug' => $slug,
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        $companyId = (string) $company->id;
        $statuses = ['sent', 'sent', 'partially_paid', 'paid', 'overdue', 'draft'];
        $types = ['invoice', 'invoice', 'invoice', 'credit_note', 'receipt'];

        $now = now();
        $contacts = [];
        for ($i = 0; $i < 500; $i++) {
            $contacts[] = [
                'company_id' => $companyId,
                'type' => 'customer',
                'name' => 'Client Benchmark '.$i,
                'email' => 'client-bench-'.$i.'@exemple.dz',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('accounting_contacts')->insert($contacts);
        $contactIds = DB::table('accounting_contacts')
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $documentRows = [];
        $lines = [];
        $payments = [];
        $nowDate = $now->toDateString();

        for ($i = 1; $i <= $documents; $i++) {
            $type = $types[$i % count($types)];
            $status = $statuses[$i % count($statuses)];
            $ht = (float) random_int(10000, 5000000) / 100.0;
            $tax = round($ht * 0.19, 2);
            $ttc = round($ht + $tax, 2);
            $issueDate = $now->copy()->subDays(random_int(0, 365))->toDateString();
            $dueDate = $now->copy()->addDays(random_int(-30, 60))->toDateString();
            $isPaid = $status === 'paid';
            $isDraft = $status === 'draft';

            $documentRows[] = [
                'company_id' => $companyId,
                'type' => $type,
                'number' => 'BENCH-'.$nowDate.'-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'status' => $status,
                'contact_id' => $contactIds[$i % count($contactIds)],
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => 'DZD',
                'subtotal_ht' => $ht,
                'tax_amount' => $tax,
                'total_ttc' => $ttc,
                'tva_rate' => 19.0,
                'paid_amount' => $isPaid ? $ttc : ($status === 'partially_paid' ? round($ttc / 2, 2) : 0.0),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 3 lignes par document (uniforme — mapping de flush simple).
            $lineCount = 3;
            for ($l = 0; $l < $lineCount; $l++) {
                $lines[] = [
                    'company_id' => $companyId,
                    'document_id' => 0, // remplacé après insertion
                    'sort_order' => $l,
                    'description' => 'Prestation benchmark '.$l,
                    'quantity' => 1,
                    'unit_price' => round($ht / $lineCount, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Un paiement pour les documents partiellement payés/payés.
            if ($isPaid || $status === 'partially_paid') {
                $payments[] = [
                    'company_id' => $companyId,
                    'document_id' => 0, // remplacé après insertion
                    'amount' => $isPaid ? $ttc : round($ttc / 2, 2),
                    'method' => $i % 3 === 0 ? 'cash' : 'bank_transfer',
                    'received_at' => $issueDate,
                    'status' => 'matched',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insertions par paquets de 1000 pour limiter la mémoire.
            if ($i % 1000 === 0) {
                $this->flush($companyId, $documentRows, $lines, $payments);
                $documentRows = [];
                $lines = [];
                $payments = [];
            }
        }
        $this->flush($companyId, $documentRows, $lines, $payments);

        return $slug;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $payments
     */
    private function flush(string $companyId, array $documents, array $lines, array $payments): void
    {
        if ($documents === []) {
            return;
        }

        DB::table('accounting_documents')->insert($documents);
        $ids = DB::table('accounting_documents')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit(count($documents))
            ->pluck('id')
            ->all();
        $ids = array_reverse($ids);

        $lineRows = [];
        foreach ($lines as $index => $line) {
            // 3 lignes par document (voir run()) — mapping par blocs.
            $lineRows[] = array_merge($line, ['document_id' => $ids[intdiv($index, 3)]]);
        }
        if ($lineRows !== []) {
            DB::table('accounting_document_lines')->insert($lineRows);
        }

        $paymentRows = [];
        foreach ($payments as $index => $payment) {
            $paymentRows[] = array_merge($payment, ['document_id' => $ids[$index]]);
        }
        if ($paymentRows !== []) {
            DB::table('accounting_payments')->insert($paymentRows);
        }
    }
}
