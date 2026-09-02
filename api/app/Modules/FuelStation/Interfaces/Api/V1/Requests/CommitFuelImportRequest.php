<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Commit d'un import FuelStation (FUEL-018, issue #5812).
 *
 * Corps vide — le commit est un acte explicite et idempotent sur la session
 * d'import (claim atomique de statut).
 */
class CommitFuelImportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
