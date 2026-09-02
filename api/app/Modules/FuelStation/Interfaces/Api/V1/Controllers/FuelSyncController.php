<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Infrastructure\Services\FuelSyncService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SyncFuelReadingsRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SyncFuelSalesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline & synchronisation (FUEL-014, issue #5808).
 *
 * - `outbox` : resynchronisation du terminal pompiste (relevés/ventes
 *   postérieurs à since_id, bornée) — aucun replay massif.
 * - `readings` / `sales` : lots idempotents (idempotency_key / external_id)
 *   — un rejeu réseau ne crée jamais de doublon.
 */
class FuelSyncController extends Controller
{
    public function __construct(private readonly FuelSyncService $sync) {}

    public function outbox(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $sinceId = max(0, $request->integer('since_id', 0));
        $limit = min(500, max(1, $request->integer('limit', 100)));

        return response()->json([
            'data' => $this->sync->outboxForDevice((string) $actor->company_id, $sinceId, $limit),
        ]);
    }

    public function readings(SyncFuelReadingsRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $result = $this->sync->bulkReadings($actor, $this->payloadList($request, 'readings'));

        return response()->json(['data' => $result]);
    }

    public function sales(SyncFuelSalesRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $result = $this->sync->bulkSales($actor, $this->payloadList($request, 'sales'));

        return response()->json(['data' => $result]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payloadList(Request $request, string $key): array
    {
        $items = (array) $request->input($key, []);

        /** @var list<array<string, mixed>> $payloads */
        $payloads = array_values(array_map(
            static fn (mixed $item): array => is_array($item) ? $item : [],
            $items
        ));

        return $payloads;
    }
}
