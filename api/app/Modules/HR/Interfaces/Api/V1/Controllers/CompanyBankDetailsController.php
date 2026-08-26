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
 * #5613 — Coordonnées bancaires de l'entreprise (IBAN/BIC débiteur SEPA).
 *
 * Stockées dans `public.companies.metadata` sous les clés plates
 * `company_iban` et `company_bic` (convention documentée dans
 * api/app/Support/CompanyBankDetails.php et issue #2198).
 *
 * RBAC : principal uniquement (même garde que la configuration de la paie).
 * Isolation tenant : la société est résolue depuis l'employé authentifié
 * (jamais depuis un id URL) — fail-closed #3727.
 */
class CompanyBankDetailsController extends Controller
{
    public function show(): JsonResponse
    {
        $company = $this->freshCompany();
        $metadata = $this->decodedMetadata($company);

        return new JsonResponse([
            'data' => [
                'company_iban' => $this->nullableString($metadata['company_iban'] ?? null),
                'company_bic'  => $this->nullableString($metadata['company_bic'] ?? null),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal'), 403);

        $validated = $request->validate([
            'company_iban' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                new ValidIban,
            ],
            'company_bic' => [
                'sometimes',
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/i',
            ],
        ]);

        $company  = $this->freshCompany();
        $metadata = $this->decodedMetadata($company);

        // Mettre à jour uniquement les champs fournis dans la requête.
        if (array_key_exists('company_iban', $validated)) {
            $raw = $this->nullableString($validated['company_iban'] ?? null);
            $metadata['company_iban'] = $raw !== null ? strtoupper(str_replace([' ', '-'], '', $raw)) : null;
        }

        if (array_key_exists('company_bic', $validated)) {
            $raw = $this->nullableString($validated['company_bic'] ?? null);
            $metadata['company_bic'] = $raw !== null ? strtoupper($raw) : null;
        }

        DB::table($this->companiesTable())
            ->where('id', $company->id)
            ->update([
                'metadata'   => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        $fresh    = $this->freshCompany();
        $freshMeta = $this->decodedMetadata($fresh);

        return new JsonResponse([
            'data' => [
                'company_iban' => $this->nullableString($freshMeta['company_iban'] ?? null),
                'company_bic'  => $this->nullableString($freshMeta['company_bic'] ?? null),
            ],
            'message' => __('payroll.bank_details_updated'),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function freshCompany(): Company
    {
        $company = currentCompany();

        return Company::query()
            ->from($this->companiesTable())
            ->where('id', $company->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedMetadata(Company $company): array
    {
        $raw = $company->metadata ?? [];

        // jsonb revient en STRING via query builder (cf. CompanyBankDetails.php)
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
