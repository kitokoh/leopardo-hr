<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\EnqueueKitchenTicketPrintAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPrintJobResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST-OS-3 (Mission Leopardo Restaurant OS) — Surface AUTHENTIFIÉE des
 * travaux d'impression thermique (écran cuisine / gestionnaire).
 *
 *  - index  : liste des travaux d'une succursale (branch_id obligatoire,
 *             tenant-scope → une branche d'un autre tenant renvoie 404) ;
 *  - show   : détail d'un travail (hors tenant → 404) ;
 *  - enqueue: met un ticket cuisine en file (idempotent par clé métier) ;
 *  - requeue: réimpression EXPLICITE → nouvelle révision (jamais automatique).
 *
 * Autorisation ressource-scopée (#7599) : lecture = `view` sur la succursale,
 * geste opérationnel = `operate` (voir RestaurantPrintJobPolicy et
 * ChecksRestaurantBranchAccess).
 */
class RestaurantPrintJobController extends Controller
{
    public function __construct(
        private readonly EnqueueKitchenTicketPrintAction $enqueue,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $branchId = $request->query('branch_id');

        if (! is_numeric($branchId)) {
            abort(422, 'branch_id is required to list print jobs.');
        }

        $branchId = (int) $branchId;

        // Portée : la Policy n'a pas de modèle à charger ici → on vérifie
        // l'accès succursale via Gate sur un job « virtuel » porté par la branche.
        $probe = new RestaurantPrintJob(['branch_id' => $branchId]);
        $probe->company_id = (string) $actor->company_id;

        if ($actor->cannot('view', $probe)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $jobs = RestaurantPrintJob::query()
            ->where('company_id', $actor->company_id)
            ->where('branch_id', $branchId)
            ->when(
                $request->query('status') !== null,
                fn ($query) => $query->where('status', (string) $request->query('status')),
            )
            ->when(
                $request->query('order_id') !== null && is_numeric($request->query('order_id')),
                fn ($query) => $query->where('order_id', (int) $request->query('order_id')),
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return RestaurantPrintJobResource::collection($jobs)->response();
    }

    public function show(Request $request, RestaurantPrintJob $restaurantPrintJob): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPrintJob->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $restaurantPrintJob)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return (new RestaurantPrintJobResource($restaurantPrintJob))->response();
    }

    public function enqueue(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        $probe = new RestaurantPrintJob(['branch_id' => $restaurantOrder->branch_id]);
        $probe->company_id = $actor->company_id;

        if ($actor->cannot('requeue', $probe)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $job = $this->enqueue->execute($restaurantOrder);

        return (new RestaurantPrintJobResource($job))->response()->setStatusCode(201);
    }

    public function requeue(Request $request, RestaurantPrintJob $restaurantPrintJob): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPrintJob->company_id) {
            abort(404);
        }

        if ($actor->cannot('requeue', $restaurantPrintJob)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($restaurantPrintJob->order_id);

        // Réimpression = nouvelle révision (nouvelle ligne, contrainte unique).
        $job = $this->enqueue->execute(
            $order,
            $restaurantPrintJob->ticket_kind,
            (int) $restaurantPrintJob->revision + 1,
            $restaurantPrintJob->printer_ref,
        );

        return (new RestaurantPrintJobResource($job))->response()->setStatusCode(201);
    }
}
