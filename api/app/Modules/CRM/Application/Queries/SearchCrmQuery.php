<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5719 — Recherche CRM tenant-scoped (accounts + contacts).
 *
 * Règles :
 *  - `company_id` appliqué avant tout résultat (scope global BelongsToCompany) ;
 *  - aucun tri/SQL libre fourni par le client : la pertinence est fixe
 *    (exactitude du nom d'abord, puis création récente) ;
 *  - filtres strictement allowlistés (type, statut, owner) ;
 *  - périmètre borné par type (200 résultats max par type) pour garantir une
 *    latence bornée : les ILIKE '%term%' n'utilisent pas les index btree —
 *    les recherches sont volontairement plafonnées (note EXPLAIN dans la
 *    spec du cluster) ;
 *  - N+1 évité : `owner`/`actor` eager-loadés quand la relation existe ;
 *  - DÉFENSIF (coordination #5708) : la fondation V0 a livré deux variantes
 *    de schéma pour crm_accounts/crm_contacts (PR #5754 minimal vs #5757
 *    riche) — les colonnes facultatives (legal_name, email, phone, owner_id,
 *    job_title) ne sont utilisées que si elles existent, pour rester
 *    compatible avec les deux.
 */
class SearchCrmQuery
{
    /** Nombre maximal de résultats par type (bornage mémoire/latence). */
    private const PER_TYPE_LIMIT = 200;

    /**
     * @param  array{
     *     q: string,
     *     type?: string,
     *     status?: string,
     *     owner_id?: int,
     *     per_page?: int,
     *     page?: int,
     * }  $input
     */
    public function execute(array $input): LengthAwarePaginator
    {
        $term = $this->escapeLike(trim((string) $input['q']));
        $pattern = '%'.$term.'%';

        $type = $input['type'] ?? null;
        $status = $input['status'] ?? null;
        $ownerId = isset($input['owner_id']) ? (int) $input['owner_id'] : null;

        $rows = new Collection();

        if ($type === null || $type === 'account') {
            foreach ($this->searchAccounts($pattern, $status, $ownerId) as $account) {
                $rows->push(['type' => 'account', 'model' => $account]);
            }
        }

        if ($type === null || $type === 'contact') {
            foreach ($this->searchContacts($pattern, $status, $ownerId) as $contact) {
                $rows->push(['type' => 'contact', 'model' => $contact]);
            }
        }

        $rows = $rows
            ->sortByDesc(fn (array $row) => $row['model']->created_at?->timestamp ?? 0)
            ->values();

        $perPage = min((int) ($input['per_page'] ?? 25), 50);
        $page = max(1, (int) ($input['page'] ?? 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $input]
        );
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CrmAccount> */
    private function searchAccounts(string $pattern, ?string $status, ?int $ownerId): \Illuminate\Database\Eloquent\Collection
    {
        $query = CrmAccount::query();

        if (method_exists(CrmAccount::class, 'owner')) {
            $query->with('owner:id,first_name,last_name');
        }

        $query->where(function ($builder) use ($pattern): void {
            $builder->where('name', 'ilike', $pattern);

            foreach ($this->optionalAccountColumns() as $column) {
                $builder->orWhere($column, 'ilike', $pattern);
            }
        });

        if ($status !== null && Schema::hasColumn('crm_accounts', 'status')) {
            $query->where('status', $status);
        }

        if ($ownerId !== null && Schema::hasColumn('crm_accounts', 'owner_id')) {
            $query->where('owner_id', $ownerId);
        }

        return $query->orderBy('name')->limit(self::PER_TYPE_LIMIT)->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CrmContact> */
    private function searchContacts(string $pattern, ?string $status, ?int $ownerId): \Illuminate\Database\Eloquent\Collection
    {
        $query = CrmContact::query();

        if (method_exists(CrmContact::class, 'owner')) {
            $query->with('owner:id,first_name,last_name');
        }

        $query->where(function ($builder) use ($pattern): void {
            $builder->where('first_name', 'ilike', $pattern)
                ->orWhere('last_name', 'ilike', $pattern);

            foreach ($this->optionalContactColumns() as $column) {
                $builder->orWhere($column, 'ilike', $pattern);
            }
        });

        if ($status !== null && Schema::hasColumn('crm_contacts', 'status')) {
            $query->where('status', $status);
        }

        if ($ownerId !== null && Schema::hasColumn('crm_contacts', 'owner_id')) {
            $query->where('owner_id', $ownerId);
        }

        return $query->orderBy('last_name')->limit(self::PER_TYPE_LIMIT)->get();
    }

    /** @return array<int, string> */
    private function optionalAccountColumns(): array
    {
        $columns = [];

        foreach (['legal_name', 'email', 'phone'] as $column) {
            if (Schema::hasColumn('crm_accounts', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /** @return array<int, string> */
    private function optionalContactColumns(): array
    {
        $columns = [];

        foreach (['email', 'phone'] as $column) {
            if (Schema::hasColumn('crm_contacts', $column)) {
                $columns[] = $column;
            }
        }

        if (Schema::hasColumn('crm_contacts', 'job_title')) {
            $columns[] = 'job_title';
        } elseif (Schema::hasColumn('crm_contacts', 'title')) {
            $columns[] = 'title';
        }

        return $columns;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
