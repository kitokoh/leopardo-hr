<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantReviewStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MAT-009 / #9724 — Métriques d'observabilité RestaurantManager.
 *
 * Snapshot tenant-scoped, SANS PII ni payloads : profondeur de la file
 * outbox, événements en dead-letter, jobs en échec, avis en attente de
 * modération, commandes bloquées (payées non servies, en cuisine depuis
 * trop longtemps), fraîcheur des dernières écritures.
 *
 * Alimente le gate `observability` du pilote (dev-hub/tools/pilot-gates.json)
 * et les alertes d'exploitation. Lecture pure, aucun effet de bord.
 *
 * Autorisation : `restaurant.reports` (direction, ou niveau `manage` sur au
 * moins une succursale assignée) — même règle que les rapports agrégés : un
 * opérateur de salle n'a pas à lire la santé technique du tenant.
 */
class RestaurantMetricsController extends Controller
{
    /** Seuil (minutes) au-delà duquel une commande en cuisine est « bloquée ». */
    private const KITCHEN_STUCK_MINUTES = 45;

    /** Seuil (minutes) au-delà duquel une commande payée non servie est « bloquée ». */
    private const PAID_UNSERVED_MINUTES = 30;

    public function metrics(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('restaurant.reports')) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $companyId = $actor->company_id;

        $outboxPending = (int) RestaurantOutboxEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                RestaurantOutboxEvent::STATUS_PENDING,
                RestaurantOutboxEvent::STATUS_PROCESSING,
            ])
            ->count();

        $outboxFailed = (int) RestaurantOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('status', RestaurantOutboxEvent::STATUS_FAILED)
            ->count();

        // Dead-letter : épuisement des tentatives (MAX_ATTEMPTS) — ces
        // événements ne seront plus rejoués automatiquement.
        $outboxDeadLetter = (int) RestaurantOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('status', RestaurantOutboxEvent::STATUS_FAILED)
            ->where('attempts', '>=', RestaurantOutboxEvent::MAX_ATTEMPTS)
            ->count();

        $reviewsPending = (int) RestaurantReview::query()
            ->where('company_id', $companyId)
            ->where('status', RestaurantReviewStatus::PENDING->value)
            ->count();

        $ordersInKitchen = (int) RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                OrderStatus::OPEN->value,
                OrderStatus::IN_PREPARATION->value,
                OrderStatus::READY->value,
            ])
            ->count();

        $ordersStuckKitchen = (int) RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                OrderStatus::OPEN->value,
                OrderStatus::IN_PREPARATION->value,
            ])
            ->where('created_at', '<', now()->subMinutes(self::KITCHEN_STUCK_MINUTES))
            ->count();

        $ordersPaidUnserved = (int) RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->where('status', OrderStatus::PAID->value)
            ->where('updated_at', '<', now()->subMinutes(self::PAID_UNSERVED_MINUTES))
            ->count();

        return response()->json([
            'data' => [
                'outbox_pending' => $outboxPending,
                'outbox_failed' => $outboxFailed,
                'outbox_dead_letter' => $outboxDeadLetter,
                'jobs_failed' => $this->failedJobsCount(),
                'reviews_pending' => $reviewsPending,
                'orders_in_kitchen' => $ordersInKitchen,
                'orders_stuck_kitchen' => $ordersStuckKitchen,
                'orders_paid_unserved' => $ordersPaidUnserved,
                'thresholds' => [
                    'kitchen_stuck_minutes' => self::KITCHEN_STUCK_MINUTES,
                    'paid_unserved_minutes' => self::PAID_UNSERVED_MINUTES,
                    'outbox_max_attempts' => RestaurantOutboxEvent::MAX_ATTEMPTS,
                ],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Jobs en échec de la file d'attente.
     *
     * La table `failed_jobs` vit dans le schéma public (partagée par tous les
     * tenants) et ne porte pas de `company_id` : on ne peut donc pas la
     * cloisonner par tenant. On expose le compteur global, explicitement
     * documenté comme tel — c'est une métrique d'exploitation, pas une donnée
     * métier. Absente (déploiement sans queue database) → 0.
     */
    private function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }
}
