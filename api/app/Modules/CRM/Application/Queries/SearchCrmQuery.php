<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
 *    les recherches sont volontairement plafonnées (voir note EXPLAIN dans
 *    le module) ;
 *  - N+1 évité : `owner` eager-loaded sur les deux types.
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
        $query = CrmAccount::query()
            ->with('owner:id,first_name,last_name')
            ->where(function ($builder) use ($pattern): void {
                $builder->where('name', 'ilike', $pattern)
                    ->orWhere('legal_name', 'ilike', $pattern);
            });

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        return $query->orderBy('name')->limit(self::PER_TYPE_LIMIT)->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CrmContact> */
    private function searchContacts(string $pattern, ?string $status, ?int $ownerId): \Illuminate\Database\Eloquent\Collection
    {
        $query = CrmContact::query()
            ->with(['owner:id,first_name,last_name', 'account:id,name'])
            ->where(function ($builder) use ($pattern): void {
                $builder->where('first_name', 'ilike', $pattern)
                    ->orWhere('last_name', 'ilike', $pattern)
                    ->orWhere('email', 'ilike', $pattern)
                    ->orWhere('phone', 'ilike', $pattern);
            });

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        return $query->orderBy('last_name')->limit(self::PER_TYPE_LIMIT)->get();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
