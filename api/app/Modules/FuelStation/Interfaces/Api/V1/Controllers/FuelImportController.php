<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Infrastructure\Services\FuelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * FUEL-018 (#5812) — Import CSV contrôlé (preview + import transactionnel).
 *
 * `POST /fuel-station/imports/preview` : validation ligne par ligne sans
 * effet de bord ; `POST /fuel-station/imports` : import transactionnel
 * (rollback logique si une ligne est invalide) + trace `fuel_imports`.
 * Réservé aux managers (middleware api.manager).
 */
class FuelImportController extends Controller
{
    public function __construct(private readonly FuelImportService $imports)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $data = $this->validated($request);

        return response()->json(['data' => $this->imports->preview($actor, $data['type'], $data['rows'])]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $data = $this->validated($request);

        try {
            $result = $this->imports->import($actor, $data['type'], $data['rows']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * @return array{type: string, rows: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $request->validate([
            'type' => ['required', 'string', 'in:products,shifts,readings'],
            'rows' => ['required', 'array', 'max:5000'],
        ]);

        return [
            'type' => (string) $request->input('type'),
            'rows' => $request->input('rows'),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
