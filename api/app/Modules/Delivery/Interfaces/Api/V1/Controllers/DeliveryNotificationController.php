<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Application\Services\DeliveryNotificationService;
use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use App\Modules\Delivery\Interfaces\Api\V1\Resources\DeliveryNotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notifications destinataire (DELIVERY-206, issue #6290) — opt-out effectif
 * + liste (outbox). RBAC manager|admin ; le numéro opt-out est masqué dans
 * les listes (RGPD) sauf pour admin.
 */
final class DeliveryNotificationController
{
    public function __construct(private readonly DeliveryNotificationService $notifications) {}

    public function optOut(Request $request): JsonResponse
    {
        $phone = (string) $request->string('phone');

        if ($phone === '' || strlen($phone) < 6 || strlen($phone) > 40) {
            abort(422, 'INVALID_PHONE');
        }

        $this->notifications->optOut($this->companyId($request), $phone);

        return response()->json(['data' => ['phone' => substr($phone, 0, 4).'…'.substr($phone, -2)]])
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DeliveryNotification::query()
            ->where('company_id', $this->companyId($request))
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        // RGPD : le numéro n'est visible en clair que pour les admins
        // (manager principal — la matrice fine est BC-26-D05/#6312).
        $employee = $request->user();
        $maskPhone = ! ($employee->isManager() && $employee->hasManagerRole('principal'));

        $notifications = $query->paginate(min((int) $request->integer('per_page', 15), 100));

        return DeliveryNotificationResource::collection($notifications)
            ->additional(['meta' => ['phone_masked' => $maskPhone]])
            ->each(function (DeliveryNotificationResource $resource) use ($maskPhone): void {
                $resource->withMask($maskPhone);
            });
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
