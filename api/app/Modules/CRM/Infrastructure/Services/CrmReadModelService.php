<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Enums\CrmExportEntity;
use Illuminate\Support\Facades\Schema;

/**
 * Read models CRM recalculables (issue #5729).
 *
 * Agrégats analytiques tenant-scoped, calculés à la demande (jamais de table
 * de stockage à resynchroniser — "recalculables"). Chaque agrégat est
 * schéma-gardé : tant que le socle V0 n'est pas mergé, il retourne un état
 * vide explicite (data=[], generated_at présent) plutôt qu'une erreur.
 *
 * Aucune donnée personnelle n'est exposée — uniquement des compteurs et
 * totaux par statut/stage/owner.
 */
final class CrmReadModelService
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'accounts' => $this->accountsByStatus(),
            'contacts' => ['per_account' => $this->contactsPerAccount()],
            'leads' => $this->leadsByStatus(),
            'opportunities' => $this->opportunitiesByStage(),
            'pipeline' => $this->pipelineTotals(),
            'data_quality' => $this->dataQualityScore(),
        ];
    }

    /** @return array<string, int> */
    public function accountsByStatus(): array
    {
        if (! $this->tableExists('crm_accounts')) {
            return [];
        }

        return $this->countGrouped('crm_accounts', 'status');
    }

    /** @return array<string, int> */
    public function leadsByStatus(): array
    {
        if (! $this->tableExists('crm_leads')) {
            return [];
        }

        return $this->countGrouped('crm_leads', 'status');
    }

    /** @return array<string, int|float> */
    public function contactsPerAccount(): array
    {
        if (! $this->tableExists('crm_contacts')) {
            return ['min' => 0, 'max' => 0, 'avg' => 0];
        }

        $rows = \Illuminate\Support\Facades\DB::table('crm_contacts')
            ->where('company_id', currentCompany()->id)
            ->selectRaw('account_id, count(*) as total')
            ->groupBy('account_id')
            ->pluck('total');

        if ($rows->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'avg' => 0];
        }

        return [
            'min' => (int) $rows->min(),
            'max' => (int) $rows->max(),
            'avg' => round((float) $rows->avg(), 2),
        ];
    }

    /** @return array<string, int> */
    public function opportunitiesByStage(): array
    {
        if (! $this->tableExists('crm_opportunities')) {
            return [];
        }

        return $this->countGrouped('crm_opportunities', 'stage');
    }

    /** @return array<string, mixed> */
    public function pipelineTotals(): array
    {
        if (! $this->tableExists('crm_opportunities') || ! $this->tableExists('crm_pipelines')) {
            return ['total' => 0, 'weighted' => 0, 'by_pipeline' => []];
        }

        $rows = \Illuminate\Support\Facades\DB::table('crm_opportunities')
            ->where('company_id', currentCompany()->id)
            ->selectRaw('pipeline_id, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('pipeline_id')
            ->get();

        $total = 0;
        $weighted = 0;
        $byPipeline = [];
        foreach ($rows as $row) {
            $total += (int) $row->total;
            $weighted += (float) $row->amount;
            $byPipeline[$row->pipeline_id] = [
                'count' => (int) $row->total,
                'amount' => round((float) $row->amount, 2),
            ];
        }

        return [
            'total' => $total,
            'weighted' => round($weighted, 2),
            'by_pipeline' => $byPipeline,
        ];
    }

    /** @return array<string, mixed> */
    public function dataQualityScore(): array
    {
        $accounts = $this->completenessScore('crm_accounts', ['name', 'status']);
        $contacts = $this->completenessScore('crm_contacts', ['first_name', 'last_name', 'email']);
        $leads = $this->completenessScore('crm_leads', ['name', 'source']);

        $totalSlots = $accounts['total'] * $accounts['fields']
            + $contacts['total'] * $contacts['fields']
            + $leads['total'] * $leads['fields'];
        $filledSlots = $accounts['filled'] + $contacts['filled'] + $leads['filled'];

        $score = [
            'accounts_total' => $accounts['total'],
            'contacts_total' => $contacts['total'],
            'leads_total' => $leads['total'],
            'accounts_filled' => $accounts['filled'],
            'contacts_filled' => $contacts['filled'],
            'leads_filled' => $leads['filled'],
            'overall' => $totalSlots > 0 ? (int) round($filledSlots * 100 / $totalSlots) : 0,
        ];

        return $score;
    }

    /**
     * @param  array<string>  $requiredColumns
     * @return array{total: int, filled: int, fields: int}
     */
    private function completenessScore(string $table, array $requiredColumns): array
    {
        if (! $this->tableExists($table)) {
            return ['total' => 0, 'filled' => 0, 'fields' => count($requiredColumns)];
        }

        $base = \Illuminate\Support\Facades\DB::table($table)
            ->where('company_id', currentCompany()->id);

        $total = (int) (clone $base)->count();

        $filled = 0;
        foreach ($requiredColumns as $column) {
            $filled += (int) (clone $base)->whereNotNull($column)->where($column, '!=', '')->count();
        }

        return ['total' => $total, 'filled' => $filled, 'fields' => count($requiredColumns)];
    }

    /**
     * @return array<string, int>
     */
    private function countGrouped(string $table, string $column): array
    {
        return \Illuminate\Support\Facades\DB::table($table)
            ->where('company_id', currentCompany()->id)
            ->select($column)
            ->get()
            ->countBy(fn (object $row): string => (string) $row->{$column})
            ->all();
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
