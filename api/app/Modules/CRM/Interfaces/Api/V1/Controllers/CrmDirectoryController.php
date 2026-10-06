<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmAccountResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmContactResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmLeadResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmOpportunityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Répertoire CRM client (lectures paginées tenant) — issues #5712/#6977/#8195.
 *
 * Endpoints de liste consommés par le dashboard client web (`/crm/leads`,
 * `/crm/accounts`, `/crm/contacts`, `/crm/pipeline`) : l'UI #5715 a été
 * livrée contre ce contrat, les routes manquaient au backend. Lecture =
 * managers du tenant (middleware `api.manager`), isolation tenant
 * fail-closed (scoping `company_id`), données non archivées uniquement.
 * ADR-CRM-002 / ADR-CRM-005 : filtres et tris stricts (422 si inconnu).
 */
class CrmDirectoryController extends Controller
{
    private const ALLOWED_ACCOUNT_SORTS = ['id', 'name', 'status', 'created_at'];
    private const ALLOWED_ACCOUNT_FILTERS = ['status', 'owner_id', 'search'];
    private const ALLOWED_ACCOUNT_STATUSES = ['active', 'inactive', 'archived'];

    public function leads(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->validatePagination($request);

        return CrmLeadResource::collection(
            CrmLead::query()
                ->where('company_id', $actor->company_id)
                ->whereNull('converted_at')
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request, 25))
        );
    }

    public function accounts(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->validateDirectoryParams($request, self::ALLOWED_ACCOUNT_SORTS, self::ALLOWED_ACCOUNT_FILTERS, self::ALLOWED_ACCOUNT_STATUSES);

        $query = CrmAccount::query()
            ->where('company_id', $actor->company_id)
            ->whereNull('archived_at');

        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->has('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'ilike', '%'.$search.'%');
        }

        $sortBy = (string) $request->input('sort_by', 'name');
        $direction = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $direction);

        return CrmAccountResource::collection(
            $query->paginate($this->perPage($request, 25))
        );
    }

    public function contacts(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->validatePagination($request);

        return CrmContactResource::collection(
            CrmContact::query()
                ->with(['account:id,name' => fn ($query) => $query->where('company_id', $actor->company_id)])
                ->where('company_id', $actor->company_id)
                ->whereNull('archived_at')
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request, 25))
        );
    }

    public function opportunities(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->validatePagination($request);

        return CrmOpportunityResource::collection(
            CrmOpportunity::query()
                ->where('company_id', $actor->company_id)
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request, 100))
        );
    }

    /**
     * Valide les paramètres selon ADR-CRM-005 : sort_by, filter[...], per_page, status.
     *
     * @param list<string> $allowedSorts
     * @param list<string> $allowedFilters
     * @param list<string> $allowedStatuses
     */
    private function validateDirectoryParams(
        Request $request,
        array $allowedSorts,
        array $allowedFilters,
        array $allowedStatuses
    ): void {
        $this->validatePagination($request);

        if ($request->has('sort_by')) {
            $sort = (string) $request->input('sort_by');
            if (! in_array($sort, $allowedSorts, true)) {
                abort(422, "Paramètre sort_by « {$sort} » non autorisé.");
            }
        }

        if ($request->has('filter')) {
            $filters = $request->input('filter');
            if (! is_array($filters)) {
                abort(422, 'Le paramètre filter doit être un tableau clé-valeur.');
            }
            foreach (array_keys($filters) as $filterKey) {
                if (! in_array((string) $filterKey, $allowedFilters, true)) {
                    abort(422, "Filtre « {$filterKey} » non autorisé.");
                }
            }
        }

        if ($request->has('status')) {
            $status = (string) $request->input('status');
            if (! in_array($status, $allowedStatuses, true)) {
                abort(422, "Statut « {$status} » non autorisé.");
            }
        }
    }

    private function validatePagination(Request $request): void
    {
        if ($request->has('per_page')) {
            $rawPerPage = $request->input('per_page');
            if (! is_numeric($rawPerPage) || (int) $rawPerPage < 1 || (int) $rawPerPage > 100) {
                abort(422, 'La pagination per_page doit être comprise entre 1 et 100.');
            }
        }
    }

    private function perPage(Request $request, int $default): int
    {
        return max(1, min(100, $request->integer('per_page', $default)));
    }
}
