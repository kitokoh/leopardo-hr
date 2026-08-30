<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\Mobile\RestaurantMobileServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-801 (#6222) — API mobile serveur (prise de commande, service,
 * encaissement cash).
 *
 * Toutes les routes sont authentifiées Sanctum, tenant-scope, et vérifient
 * l'appartenance de la commande au tenant de l'acteur (404 sûr cross-tenant).
 */
class RestaurantMobileServerController extends Controller
{
    public function __construct(private readonly RestaurantMobileServerService $service)
    {
    }

    public function orders(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->service->activeOrders($actor)]);
    }

    public function tables(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->service->openTables($actor)]);
    }

    public function serve(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $order = $this->service->serveOrder($actor, $restaurantOrder);

        return response()->json(['data' => [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status->value,
        ]]);
    }

    public function pay(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'tip_minor' => ['sometimes', 'integer', 'min:0'],
            'idempotency_key' => ['sometimes', 'string', 'max:255'],
        ]);

        $payment = $this->service->payCash(
            $actor,
            $restaurantOrder,
            (int) $data['amount_minor'],
            isset($data['tip_minor']) ? (int) $data['tip_minor'] : null,
            isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null,
        );

        return response()->json(['data' => [
            'payment_id' => $payment->id,
            'provider_code' => $payment->provider_code->value,
            'status' => $payment->status->value,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'order_status' => $restaurantOrder->refresh()->status->value,
        ]], 201);
    }
}
