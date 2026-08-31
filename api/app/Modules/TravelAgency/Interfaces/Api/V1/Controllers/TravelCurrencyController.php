<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use App\Modules\TravelAgency\Infrastructure\Services\TravelCurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-805 (#6096) — Multi-devise : taux validés par période + conversion.
 *
 * Écritures réservées `travel.manage` ; la conversion est pure (aucune
 * écriture) — les montants canoniques restent en devise de référence.
 */
class TravelCurrencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $rates = TravelCurrencyRate::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('valid_from')
            ->get();

        return response()->json(['data' => $rates]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'base_currency' => ['required', 'string', 'size:3'],
            'quote_currency' => ['required', 'string', 'size:3', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $rate = TravelCurrencyRate::query()->create([
            'company_id' => $actor->company_id,
            'base_currency' => strtoupper($data['base_currency']),
            'quote_currency' => strtoupper($data['quote_currency']),
            'rate' => $data['rate'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
        ]);

        return response()->json(['data' => $rate])->setStatusCode(201);
    }

    /**
     * Conversion d'affichage (aucune perte d'arrondi sur le montant canonique).
     */
    public function convert(Request $request, TravelCurrencyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:0'],
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        $converted = $service->convert(
            $actor->company_id,
            (int) $data['amount_minor'],
            $data['from'],
            $data['to'],
            isset($data['date']) ? \Illuminate\Support\Carbon::parse($data['date']) : null,
        );

        return response()->json([
            'data' => [
                'from' => strtoupper($data['from']),
                'to' => strtoupper($data['to']),
                'amount_minor' => (int) $data['amount_minor'],
                'converted_amount_minor' => $converted,
                'date' => $data['date'] ?? now()->toDateString(),
            ],
        ]);
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
    }
}
