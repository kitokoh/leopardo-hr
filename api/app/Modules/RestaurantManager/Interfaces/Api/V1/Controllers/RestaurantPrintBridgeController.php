<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\AckPrintJobAction;
use App\Modules\RestaurantManager\Application\Actions\BuildKitchenTicketAction;
use App\Modules\RestaurantManager\Application\Actions\ClaimPrintJobAction;
use App\Modules\RestaurantManager\Application\Actions\RenderPrintJobPayloadAction;
use App\Modules\RestaurantManager\Domain\Contracts\OrderTicketRendererInterface;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPrintJobRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPrintBridgeJobResource;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPrintJobResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST-OS-3 (Mission Leopardo Restaurant OS) — Surface du BRIDGE d'impression
 * LOCAL (poste cuisine).
 *
 * Le bridge est authentifié par jeton partagé (middleware
 * `restaurant.print.bridge`, hash en env) et scopé à UNE société
 * (`X-Company-Id`) : la confiance ne repose jamais sur Sanctum, et le contexte
 * tenant rend le scope BelongsToCompany actif.
 *
 * Les travaux sont résolus EXPLICITEMENT via le repository tenant-scopé
 * (jamais par binding implicite) : hors société → 404, sans dépendre de
 * l'ordre des middlewares.
 *
 * Cycle de vie (aucune impression « supposée ») :
 *   1. `jobs`   — liste les travaux réclamables d'une succursale ;
 *   2. `claim`  — réclame ATOMIQUEMENT un travail et reçoit le flux ESC/POS
 *                 (base64) à remettre à l'imprimante ;
 *   3. `ack`    — accuse l'impression RÉELLE (`printed`) ;
 *      `fail`   — signale un échec (papier, réseau, driver) : le travail
 *                 repart dans la file avec son motif.
 *
 * Le bridge ne peut pas marquer `printed` sans avoir réclamé (le passage à
 * `printed` exige un travail non terminal), et l'API ne déduit jamais une
 * impression réussie de l'émission d'une requête.
 */
class RestaurantPrintBridgeController extends Controller
{
    public function __construct(
        private readonly RestaurantPrintJobRepositoryInterface $jobs,
        private readonly ClaimPrintJobAction $claim,
        private readonly AckPrintJobAction $ack,
        private readonly RenderPrintJobPayloadAction $render,
        private readonly BuildKitchenTicketAction $buildTicket,
        private readonly OrderTicketRendererInterface $renderer,
    ) {}

    public function jobs(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');

        if (! is_numeric($branchId)) {
            abort(422, 'branch_id is required.');
        }

        $jobs = $this->claimableQuery((int) $branchId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return RestaurantPrintBridgeJobResource::collection($jobs)->response();
    }

    /**
     * Réclame un travail et renvoie le flux ESC/POS encodé base64.
     */
    public function claim(Request $request, string $restaurantPrintJob): JsonResponse
    {
        $job = $this->resolveJob($restaurantPrintJob);

        $printerRef = $request->input('printer_ref');
        $printerRef = is_string($printerRef) ? $printerRef : null;

        $claimed = $this->claim->execute($job, $printerRef);

        if ($claimed === null) {
            // Déjà réclamé par une autre instance ou terminal.
            return new JsonResponse(['error' => 'job_not_claimable'], 409);
        }

        $paperWidth = $request->input('paper_width');
        $paperWidth = is_string($paperWidth) || is_int($paperWidth) ? (string) $paperWidth : null;

        $rendered = $this->render->execute($claimed, $paperWidth);

        return new JsonResponse([
            'job' => (new RestaurantPrintBridgeJobResource($claimed))->resolve(),
            'payload_base64' => base64_encode($rendered['payload']),
            'payload_sha256' => $rendered['hash'],
            'encoding' => 'escpos',
        ]);
    }

    public function ack(Request $request, string $restaurantPrintJob): JsonResponse
    {
        $job = $this->resolveJob($restaurantPrintJob);

        $printerRef = $request->input('printer_ref');
        $printerRef = is_string($printerRef) ? $printerRef : null;

        $acked = $this->ack->printed($job, $printerRef);

        return (new RestaurantPrintJobResource($acked))->response();
    }

    public function fail(Request $request, string $restaurantPrintJob): JsonResponse
    {
        $job = $this->resolveJob($restaurantPrintJob);

        $error = $request->input('error');
        $error = is_string($error) && $error !== '' ? $error : 'unknown_bridge_error';

        $failed = $this->ack->failed($job, $error);

        return (new RestaurantPrintJobResource($failed))->response();
    }

    /**
     * Re-rend le flux d'un travail déjà réclamé SANS le réclamer à nouveau
     * (utile après un redémarrage du bridge : le travail est toujours `claimed`).
     */
    public function payload(Request $request, string $restaurantPrintJob): JsonResponse
    {
        $job = $this->resolveJob($restaurantPrintJob);

        if ($job->status === PrintJobStatus::CANCELLED) {
            abort(409, 'job_cancelled');
        }

        $rendered = $this->render->execute($job);

        return new JsonResponse([
            'job' => (new RestaurantPrintBridgeJobResource($job))->resolve(),
            'payload_base64' => base64_encode($rendered['payload']),
            'payload_sha256' => $rendered['hash'],
            'encoding' => 'escpos',
        ]);
    }

    /**
     * Sonde de disponibilité du bridge (aucune donnée métier).
     */
    public function health(Request $request): JsonResponse
    {
        $lease = config('restaurantmanager.printing.claim_lease_minutes', 15);
        $paper = config('restaurantmanager.printing.paper_width', 80);

        return new JsonResponse([
            'status' => 'ok',
            'claim_lease_minutes' => is_numeric($lease) ? (int) $lease : 15,
            'paper_width' => is_numeric($paper) ? (int) $paper : 80,
        ]);
    }

    /**
     * Prévisualisation TEXTE d'un ticket (diagnostic sans consommer de papier).
     */
    public function preview(Request $request, string $restaurantPrintJob): JsonResponse
    {
        $job = $this->resolveJob($restaurantPrintJob);
        $job->loadMissing('order');
        $order = $job->order;

        if ($order === null) {
            abort(404);
        }

        $ticket = $this->buildTicket->execute($order, (int) $job->revision);
        $bytes = $this->renderer->render($ticket, 80);

        // Retrait des séquences de contrôle pour l'affichage humain.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $bytes) ?? '';

        return new JsonResponse([
            'job_id' => $job->id,
            'ticket_kind' => $job->ticket_kind->value,
            'preview' => $text,
        ]);
    }

    /**
     * Résolution tenant-scopée explicite : un travail d'une autre société
     * renvoie 404 (jamais de fuite, jamais de binding implicite).
     */
    private function resolveJob(string $id): RestaurantPrintJob
    {
        $companyId = (string) currentCompany()->id;

        $job = $this->jobs->findForCompany((int) $id, $companyId);

        if (! $job instanceof RestaurantPrintJob) {
            abort(404);
        }

        return $job;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<RestaurantPrintJob>
     */
    private function claimableQuery(int $branchId)
    {
        $configuredLease = config('restaurantmanager.printing.claim_lease_minutes', 15);
        $leaseMinutes = is_numeric($configuredLease) ? (int) $configuredLease : 15;

        return RestaurantPrintJob::query()
            ->where('branch_id', $branchId)
            ->where('ticket_kind', PrintTicketKind::KITCHEN->value)
            ->where(function ($query) use ($leaseMinutes): void {
                $query->whereIn('status', [
                    PrintJobStatus::PENDING->value,
                    PrintJobStatus::FAILED->value,
                ])->orWhere(function ($query) use ($leaseMinutes): void {
                    $query->where('status', PrintJobStatus::CLAIMED->value)
                        ->where('claimed_at', '<', now()->subMinutes($leaseMinutes));
                });
            });
    }
}
