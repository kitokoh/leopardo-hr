<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Exceptions\InvalidLetteringException;
use App\Modules\Accounting\Domain\Exceptions\LetteringAlreadyUsedException;
use App\Modules\Accounting\Domain\Exceptions\UnbalancedLetteringException;
use App\Modules\Accounting\Infrastructure\Services\LetteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lettrage comptable — issue #5422.
 *
 * - POST   /accounting/journal/lettering             → lettre des écritures ;
 * - DELETE /accounting/journal/lettering/{letter}    → délettrage.
 *
 * Erreurs métier → 422 {message, code} :
 * LETTERING_UNBALANCED, LETTERING_INVALID, LETTERING_ALREADY_USED.
 */
final class AccountingLetteringController extends Controller
{
    /**
     * POST /api/v1/accounting/journal/lettering
     */
    public function store(Request $request, LetteringService $service): JsonResponse
    {
        $validated = $request->validate([
            'letter' => ['required', 'string', 'max:32'],
            'entry_ids' => ['required', 'array', 'min:2'],
            'entry_ids.*' => ['required', 'integer'],
        ], [
            'letter.required' => __('accounting.validation.letter_required'),
            'letter.max' => __('accounting.validation.letter_max'),
            'entry_ids.required' => __('accounting.validation.entry_ids_required'),
            'entry_ids.min' => __('accounting.validation.entry_ids_min'),
            'entry_ids.*.integer' => __('accounting.validation.entry_ids_integer'),
        ]);

        /** @var array<int, int> $entryIds */
        $entryIds = $validated['entry_ids'];

        try {
            $result = $service->letter(
                $this->companyId($request),
                (string) $validated['letter'],
                $entryIds,
            );
        } catch (UnbalancedLetteringException) {
            return $this->error('LETTERING_UNBALANCED', __('accounting.lettering_unbalanced'));
        } catch (LetteringAlreadyUsedException) {
            return $this->error('LETTERING_ALREADY_USED', __('accounting.lettering_already_used'));
        } catch (InvalidLetteringException) {
            return $this->error('LETTERING_INVALID', __('accounting.lettering_invalid'));
        }

        return response()->json(['data' => $result], 201);
    }

    /**
     * DELETE /api/v1/accounting/journal/lettering/{letter}
     */
    public function destroy(Request $request, LetteringService $service, string $letter): JsonResponse
    {
        $service->unletter($this->companyId($request), $letter);

        return response()->json(null, 204);
    }

    private function companyId(Request $request): string
    {
        return (string) $request->user()?->company_id;
    }

    private function error(string $code, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], 422);
    }
}
