<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CreateCurrencyRateAction;
use App\Modules\TravelAgency\Application\Actions\UpdateCurrencyRateAction;
use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use App\Modules\TravelAgency\Infrastructure\Services\TravelCurrencyConverter;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelCurrencyRateRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCurrencyRateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-805 (#6096) — Multi-devise : taux de conversion par tenant.
 */
class TravelCurrencyRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelCurrencyRate::class)) {
            abort(403);
        }

        $rates = TravelCurrencyRate::query()
            ->orderBy('from_currency')
            ->orderBy('valid_from')
            ->paginate(max(1, min(1000, (int) $request->query('per_page', 50))));

        return TravelCurrencyRateResource::collection($rates)->response();
    }

    public function store(StoreTravelCurrencyRateRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelCurrencyRate::class)) {
            abort(403);
        }

        $rate = app(CreateCurrencyRateAction::class)->execute($this->validatedData($request));

        return (new TravelCurrencyRateResource($rate))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelCurrencyRate $travelCurrencyRate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCurrencyRate->company_id) {
            abort(404);
        }

        return (new TravelCurrencyRateResource($travelCurrencyRate))->response();
    }

    public function update(StoreTravelCurrencyRateRequest $request, TravelCurrencyRate $travelCurrencyRate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelCurrencyRate->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelCurrencyRate)) {
            abort(403);
        }

        $rate = app(UpdateCurrencyRateAction::class)->execute($travelCurrencyRate, $this->validatedData($request));

        return (new TravelCurrencyRateResource($rate))->response();
    }

    public function convert(Request $request, TravelCurrencyConverter $converter): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelCurrencyRate::class)) {
            abort(403);
        }

        // #9098 — deux ecarts entre le controle et ce que les clients envoient.
        // (1) Le parametre lu etait `amount` alors que la convention du module
        // est `amount_minor` (tout montant y est en unites mineures) : un client
        // conforme envoyait `amount_minor` et se faisait convertir **0**.
        // (2) La reponse renvoyait telle quelle la sortie du convertisseur, donc
        // `data.amount_minor` — indistinguable du montant d'entree — et jamais
        // `data.converted_amount_minor` attendu par le contrat.
        // `amount` reste accepte en alias legacy, et `amount_minor` est conserve
        // dans la reponse pour ne casser aucun consommateur existant.
        $rawAmount = $request->query('amount_minor') ?? $request->query('amount');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $date = $request->query('date') ? (string) $request->query('date') : null;

        if (! is_numeric($rawAmount) || (int) $rawAmount < 0 || strlen($from) !== 3 || strlen($to) !== 3) {
            abort(422, 'Parametres invalides (amount_minor, from, to requis).');
        }

        $result = $converter->convert((int) $rawAmount, strtoupper($from), strtoupper($to), $date);

        return response()->json(['data' => [
            'converted_amount_minor' => $result['amount_minor'],
            'amount_minor' => $result['amount_minor'],
            'currency' => $result['currency'],
            'rate_minor' => $result['rate_minor'],
            'inverse' => $result['inverse'],
        ]]);
    }

    /**
     * @return array{from_currency: string, to_currency: string, rate_minor: int, valid_from: string, valid_to?: string|null}
     */
    private function validatedData(StoreTravelCurrencyRateRequest $request): array
    {
        return [
            'from_currency' => strtoupper((string) $request->validated('from_currency')),
            'to_currency' => strtoupper((string) $request->validated('to_currency')),
            'rate_minor' => (int) $request->validated('rate_minor'),
            'valid_from' => (string) $request->validated('valid_from'),
            'valid_to' => $request->validated('valid_to') !== null ? (string) $request->validated('valid_to') : null,
        ];
    }
}
