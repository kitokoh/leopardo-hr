<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Préparation d'un ordre de virement — issue #5239 (Phase C).
 * Le format d'export reprend les formats banque du module Payroll.
 */
class PreparePaymentOrderRequest extends FormRequest
{
    private const ALLOWED_FORMATS = [
        'sepa_xml', 'ccp_dz', 'cpa_dz', 'bna_dz', 'cnep_dz', 'edx_dz', 'virement_ma', 'csv_generic',
    ];

    public function authorize(): bool
    {
        return true; // RBAC porté par le middleware de route (api.manager:comptable)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_FORMATS)],
        ];
    }
}
