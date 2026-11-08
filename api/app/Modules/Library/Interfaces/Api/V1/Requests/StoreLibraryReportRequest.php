<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Requests;

use App\Modules\Library\Domain\Enums\LibraryReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Signalement public d'un contenu (procédure de retrait, droits d'auteur).
 */
class StoreLibraryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reasons = array_map(
            static fn (LibraryReportReason $reason): string => $reason->value,
            LibraryReportReason::cases()
        );

        return [
            'reason' => ['required', Rule::in($reasons)],
            'details' => ['nullable', 'string', 'max:5000'],
            'reporter_email' => ['nullable', 'email', 'max:200'],
        ];
    }
}
