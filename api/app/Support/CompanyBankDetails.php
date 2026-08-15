<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Débiteurs SEPA : IBAN/BIC de l'entreprise lu depuis `public.companies`
 * (colonne `metadata`), jamais depuis le search_path tenant.
 *
 * Conventions metadata (clés plates, comme `tax_id`/`nis`/`siret`) :
 * - `company_iban` : IBAN du compte débiteur (obligatoire pour SEPA)
 * - `company_bic`  : BIC de la banque débitrice (optionnel)
 *
 * Issue #2198 : le générateur SEPA émettait PLACEHOLDER_COMPANY_IBAN /
 * PLACEHOLDER_BIC — le fichier n'était utilisable par aucune banque.
 */
final class CompanyBankDetails
{
    /**
     * @return array{name: string, iban: ?string, bic: ?string}
     */
    public static function forCompany(string $companyId): array
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';

        $row = DB::table($table)
            ->select(['name', 'metadata'])
            ->where('id', $companyId)
            ->first();

        if ($row === null) {
            return ['name' => 'Leopardo RH', 'iban' => null, 'bic' => null];
        }

        // jsonb revient en STRING via le query builder (pas de cast Eloquent)
        // — le décoder explicitement, sinon company_iban/company_bic sont
        // invisibles et le SEPA échoue en MISSING_COMPANY_IBAN (#2551 cause 5).
        $raw = $row->metadata ?? [];
        $metadata = is_array($raw)
            ? $raw
            : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);

        return [
            'name' => (string) ($row->name ?? 'Leopardo RH'),
            'iban' => self::nullableString($metadata['company_iban'] ?? null),
            'bic' => self::nullableString($metadata['company_bic'] ?? null),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }
}
