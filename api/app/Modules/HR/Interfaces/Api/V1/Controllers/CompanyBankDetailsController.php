<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Rules\ValidIban;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Coordonnées bancaires de l'entreprise (IBAN / BIC) nécessaires à
 * l'export SEPA pain.001.001.03.
 *
 * Issue #5613 — ces champs existaient côté backend (CompanyBankDetails,
 * ValidIban, BankExportGenerator) mais aucune UI ne permettait de les saisir ;
 * l'export SEPA retournait systématiquement 422 MISSING_COMPANY_IBAN.
 *
 * Stockage : `companies.metadata.company_iban` / `.company_bic` (clés plates
 * héritées de l'issue #2198, cohérentes avec CompanyBankDetails::forCompany()).
 */
class CompanyBankDetailsController extends Controller
{
    /**
     * GET /api/v1/company/bank-details
     * Retourne l'IBAN et le BIC actuellement configurés.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $company = $this->freshCompany($actor->company_id);
        $metadata = $this->parseMetadata($company);

        return new JsonResponse([
            'data' => $this->payload($metadata),
        ]);
    }

    /**
     * PATCH /api/v1/company/bank-details
     * Met à jour l'IBAN et/ou le BIC de l'entreprise.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $validated = $request->validate([
            // ValidIban accepte DZ (RIB 20 chiffres), MA, FR, TR et les IBAN
            // officiels — voir api/app/Rules/ValidIban.php (issue #2198).
            'company_iban' => ['required', 'string', 'max:64', new ValidIban],
            'company_bic'  => ['nullable', 'string', 'max:11', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
        ]);

        $company  = $this->freshCompany($actor->company_id);
        $metadata = $this->parseMetadata($company);

        // Normalisation : IBAN sans espaces, BIC en majuscules.
        $metadata['company_iban'] = strtoupper(str_replace([' ', '-'], '', (string) $validated['company_iban']));

        if (array_key_exists('company_bic', $validated)) {
            $metadata['company_bic'] = $validated['company_bic'] !== null
                ? strtoupper(trim((string) $validated['company_bic']))
                : null;
        }

        $this->persistMetadata($company->id, $metadata);

        return new JsonResponse([
            'data'    => $this->payload($metadata),
            'message' => __('errors.COMPANY_BANK_DETAILS_UPDATED', [], 'fr')
                ?: 'Coordonnées bancaires mises à jour.',
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function freshCompany(string $companyId): Company
    {
        /** @var Company $company */
        $company = Company::query()
            ->from($this->companiesTable())
            ->where('id', $companyId)
            ->firstOrFail();

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMetadata(Company $company): array
    {
        $raw = $company->metadata ?? [];

        if (is_array($raw)) {
            return $raw;
        }

        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{company_iban: string|null, company_bic: string|null}
     */
    private function payload(array $metadata): array
    {
        return [
            'company_iban' => isset($metadata['company_iban']) && $metadata['company_iban'] !== ''
                ? (string) $metadata['company_iban']
                : null,
            'company_bic'  => isset($metadata['company_bic']) && $metadata['company_bic'] !== ''
                ? (string) $metadata['company_bic']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function persistMetadata(string $companyId, array $metadata): void
    {
        DB::table($this->companiesTable())
            ->where('id', $companyId)
            ->update([
                'metadata'   => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
