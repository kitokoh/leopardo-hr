<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Rules\ValidIban;
use App\Support\CompanyBankDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5613 — coordonnées bancaires de l'entreprise (débiteur SEPA).
 *
 * L'export SEPA (pain.001.001.03, #2198) exige `company.metadata.company_iban`
 * mais aucun frontend ne permettait de le saisir → 422 MISSING_COMPANY_IBAN
 * systématique. Ce contrôleur expose GET/PATCH /company/bank-details :
 * lecture/écriture de `company_iban` + `company_bic` (clés plates du metadata,
 * lues par CompanyBankDetails::forCompany()).
 *
 * Note DZ : l'Algérie ne fait pas partie du registre IBAN officiel — pour une
 * entreprise en DZ, le RIB (20 chiffres) est accepté en plus de l'IBAN.
 */
class CompanyBankDetailsController extends Controller
{
    public function show(): JsonResponse
    {
        $company = currentCompany();

        return new JsonResponse(['data' => CompanyBankDetails::forCompany((string) $company->id)]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403, 'FORBIDDEN');

        $company = $this->freshCompany();

        $validated = $request->validate([
            'company_iban' => [
                'sometimes',
                'nullable',
                'string',
                'max:34',
                new ValidIban($company->country === 'DZ'),
            ],
            'company_bic' => [
                'sometimes',
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/',
            ],
        ]);

        $metadata = is_array($company->metadata) ? $company->metadata : [];
        foreach (['company_iban', 'company_bic'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = $validated[$field];
                if ($value === null || trim((string) $value) === '') {
                    unset($metadata[$field]);
                } else {
                    $metadata[$field] = ValidIban::normalize((string) $value);
                }
            }
        }

        $company->metadata = $metadata;
        $company->save();

        return new JsonResponse([
            'data' => CompanyBankDetails::forCompany((string) $company->id),
            'message' => __('errors.COMPANY_BANK_DETAILS_UPDATED'),
        ]);
    }

    private function freshCompany(): Company
    {
        $company = currentCompany();
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';

        return Company::query()
            ->from($table)
            ->where('id', $company->id)
            ->firstOrFail();
    }
}
