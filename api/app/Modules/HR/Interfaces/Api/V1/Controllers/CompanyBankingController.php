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
 * Issue #5613 — Coordonnées bancaires de l'entreprise (IBAN/BIC SEPA).
 *
 * Expose deux endpoints réservés aux managers (principal ou rh) :
 *
 *   GET  /api/v1/company/banking   → lit company_iban / company_bic depuis metadata
 *   PATCH /api/v1/company/banking  → met à jour ces clés dans metadata
 *
 * Sans ces champs configurés, l'export SEPA (pain.001.001.03) retourne
 * 422 MISSING_COMPANY_IBAN (#2198).
 *
 * Note DZ : l'Algérie n'utilise pas l'IBAN officiel. Un RIB à 20 chiffres
 * est accepté pour les entreprises dont le pays est DZ.
 */
class CompanyBankingController extends Controller
{
    /**
     * GET /api/v1/company/banking
     *
     * Retourne l'IBAN et le BIC actuellement enregistrés pour l'entreprise,
     * avec un flag `sepa_ready` indiquant si l'export SEPA est utilisable.
     */
    public function show(): JsonResponse
    {
        $company = $this->freshCompany();
        $banking = $this->bankingFrom($company);

        return new JsonResponse([
            'data' => [
                'company_iban' => $banking['company_iban'],
                'company_bic' => $banking['company_bic'],
                // Indicateur : l'IBAN est requis pour l'export SEPA (#2198).
                'sepa_ready' => $banking['company_iban'] !== null,
                'country' => $company->country ?? null,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/company/banking
     *
     * Met à jour les coordonnées bancaires dans metadata.
     * Validation :
     *   - company_iban : IBAN ISO 13616 (ValidIban) ou RIB 20 chiffres pour DZ
     *   - company_bic  : BIC standard (4 + 2 + 2 + éventuellement 3)
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $company = $this->freshCompany();

        $validated = $request->validate([
            'company_iban' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_bic' => [
                'sometimes',
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/i',
            ],
        ]);

        // Validation IBAN / RIB selon le pays de l'entreprise.
        if (array_key_exists('company_iban', $validated) && $validated['company_iban'] !== null) {
            $ibanRaw = (string) $validated['company_iban'];
            $ibanNorm = ValidIban::normalize($ibanRaw);

            $countryCode = strtoupper(trim((string) ($company->country ?? '')));

            if (! $this->isAcceptableIban($ibanNorm, $countryCode)) {
                return new JsonResponse([
                    'message' => 'Les données fournies sont invalides.',
                    'errors' => [
                        'company_iban' => ['Le champ company_iban n\'est pas un IBAN valide'
                            .($countryCode === 'DZ' ? ' ou un RIB algérien valide (20 chiffres).' : '.')],
                    ],
                ], 422);
            }

            // Stocker l'IBAN normalisé (majuscules, sans espaces).
            $validated['company_iban'] = $ibanNorm;
        }

        // Mettre à jour uniquement les clés fournies dans metadata.
        $metadata = $company->metadata ?? [];

        foreach (['company_iban', 'company_bic'] as $field) {
            if (array_key_exists($field, $validated)) {
                if ($validated[$field] === null || trim((string) $validated[$field]) === '') {
                    unset($metadata[$field]);
                } else {
                    $metadata[$field] = strtoupper(trim((string) $validated[$field]));
                }
            }
        }

        $this->persistMetadata($company, $metadata);

        $updatedCompany = $this->freshCompany();
        $banking = $this->bankingFrom($updatedCompany);

        return new JsonResponse([
            'data' => [
                'company_iban' => $banking['company_iban'],
                'company_bic' => $banking['company_bic'],
                'sepa_ready' => $banking['company_iban'] !== null,
                'country' => $updatedCompany->country ?? null,
            ],
            'message' => __('errors.COMPANY_BANKING_UPDATED', [], 'fr') ?: 'Coordonnées bancaires mises à jour.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Accepte un IBAN standard (ValidIban) OU un RIB algérien (20 chiffres)
     * pour les entreprises dont le pays est DZ (#5613 — note DZ).
     */
    private function isAcceptableIban(string $ibanNorm, string $countryCode): bool
    {
        // IBAN standard ISO 13616 — valide pour tous les pays supportés.
        if (ValidIban::isValid($ibanNorm)) {
            return true;
        }

        // RIB algérien : 20 chiffres, pas de préfixe pays — uniquement si DZ.
        if ($countryCode === 'DZ' && preg_match('/^\d{20}$/', $ibanNorm)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{company_iban: ?string, company_bic: ?string}
     */
    private function bankingFrom(Company $company): array
    {
        // Le cast 'array' du modèle garantit le type (checks défensifs supprimés —
        // PHPStan strict : is_array() toujours vrai / is_string() jamais).
        $metadata = $company->metadata ?? [];

        return [
            'company_iban' => $this->nullableString($metadata['company_iban'] ?? null),
            'company_bic' => $this->nullableString($metadata['company_bic'] ?? null),
        ];
    }

    private function freshCompany(): Company
    {
        // Même convention que CompanyBrandingController : helper global
        // currentCompany() résolu par TenantManager depuis le Sanctum token.
        $company = currentCompany();

        return Company::query()
            ->from($this->companiesTable())
            ->where('id', $company->id)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $metadata */
    private function persistMetadata(Company $company, array $metadata): void
    {
        DB::table($this->companiesTable())
            ->where('id', $company->id)
            ->update([
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }
}
