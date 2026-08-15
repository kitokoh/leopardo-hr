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

        // PDO pgsql retourne les colonnes jsonb en STRING (pas en array) :
        // `DB::table()` n'applique aucun cast Eloquent. Sans décodage explicite,
        // le metadata n'était JAMAIS lu → SEPA échouait systématiquement avec
        // MISSING_COMPANY_IBAN malgré `company_iban` renseigné (régression #2198).
        $rawMetadata = $row->metadata;
        if (is_string($rawMetadata)) {
            $decoded = json_decode($rawMetadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        } else {
            $metadata = is_array($rawMetadata) ? $rawMetadata : [];
        }

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
