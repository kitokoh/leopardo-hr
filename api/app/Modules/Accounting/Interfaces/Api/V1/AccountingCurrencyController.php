<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Actions\DocumentCurrencyConverter;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\ConvertCurrencyRequest;
use Illuminate\Http\JsonResponse;

/**
 * Conversion multi-devises — issue #5270.
 *
 * Utilitaire de conversion HT/TVA/TTC entre la devise d'un document et la
 * devise de référence de l'entreprise (affichage facturation, wizard
 * d'activation). RBAC : `comptable` et `principal` (même middleware que les
 * contacts/settings). Aucune donnée persistée — calcul pur, arrondis
 * documentés dans DocumentCurrencyConverter.
 */
class AccountingCurrencyController extends Controller
{
    public function convert(ConvertCurrencyRequest $request, DocumentCurrencyConverter $converter): JsonResponse
    {
        $validated = $request->validated();

        $amount = (float) $validated['amount'];
        $from = strtoupper((string) $validated['from_currency']);
        $to = strtoupper((string) $validated['to_currency']);
        $rate = isset($validated['rate']) ? (float) $validated['rate'] : null;

        $result = $converter->convertAmount($amount, $from, $to, $rate);

        return response()->json([
            'data' => [
                'amount' => $result->amount,
                'from_currency' => $result->fromCurrency,
                'to_currency' => $result->toCurrency,
                'rate' => $result->rate,
                'source' => $result->source,
                'converted_amount' => $result->convertedAmount,
                'rounding' => $result->rounding,
                'decimals' => $result->decimals,
            ],
        ]);
    }
}
