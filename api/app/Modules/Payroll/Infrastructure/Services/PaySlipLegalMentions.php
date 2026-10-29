<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Issue #9045 (lot BC-07 P1) — référentiels de mentions obligatoires du
 * bulletin de paie par juridiction, exposés au générateur PDF.
 *
 * Les référentiels documentaires vivent dans `docs/payroll/BULLETIN_*_MENTIONS.md`
 * (gabarit `BULLETIN_DZ_MENTIONS.md`). Cette classe est leur **projection
 * exécutable** : elle ne réécrit pas la loi, elle porte les mentions que le
 * générateur doit effectivement imprimer, et les identifiants employeur /
 * salarié qu'il doit chercher.
 *
 * Règles opposables (spec `CONFORMITE_LEGALE_MULTI_MARCHES_2026.md` § 4) :
 *   1. **Aucune mention française codée en dur dans un bulletin non-FR** —
 *      chaque pays porte son propre texte, dans sa langue juridique
 *      (TR en turc, CA bilingue EN/FR, FR/CM/CI/SN en français parce que
 *      c'est la langue du texte applicable, pas par contamination du gabarit).
 *   2. **Aucun fallback pays silencieux** — un pays absent de la table ne
 *      reçoit AUCUNE mention inventée : `notice()` retourne `null` et le
 *      générateur n'imprime rien. C'est volontaire : une mention devinée sur
 *      un bulletin est pire qu'une mention absente, parce qu'elle est fausse
 *      et opposable.
 *   3. `confidenceLevel` reste `pilot` : ces textes sont prêts pour la
 *      validation experte (#7941), pas pour une promesse de conformité.
 *
 * Les identifiants sont lus dans `company.metadata` (pattern F-09 #1539 déjà
 * utilisé pour l'Algérie) et sur le modèle `Employee` (colonnes existantes :
 * `matricule`, `cnps_matricule`, `cnss_ci_matricule`, `ipres_matricule`,
 * `national_id`). Aucune migration n'est nécessaire : un identifiant absent
 * n'est simplement pas imprimé.
 */
final class PaySlipLegalMentions
{
    /**
     * Mentions légales par pays.
     *
     * `notice` : phrase de pied de bulletin, dans la langue du texte
     *            applicable. `null` = pays non couvert (aucune mention).
     * `employer` : identifiants employeur — clé `company.metadata` => libellé.
     * `employee` : identifiants salarié — attribut `Employee` => libellé.
     *
     * @var array<string, array{
     *     notice: string,
     *     employer: array<string, string>,
     *     employee: array<string, string>
     * }>
     */
    private const COUNTRIES = [
        // ── France ────────────────────────────────────────────────────────
        // C. trav. art. L3243-1 (mentions du bulletin) et R3243-1.
        'FR' => [
            'notice' => 'Bulletin établi conformément aux articles L3243-1 et R3243-1 du Code du travail. '
                .'Document à conserver sans limitation de durée.',
            'employer' => [
                'legal_siret' => 'SIRET',
                'legal_ape' => 'Code APE/NAF',
                'legal_urssaf' => 'N° URSSAF',
                'legal_convention_collective' => 'Convention collective',
            ],
            'employee' => [
                'matricule' => 'Matricule',
                'national_id' => 'N° sécurité sociale',
            ],
        ],

        // ── Turquie ───────────────────────────────────────────────────────
        // İş Kanunu m.37 (ücret hesap pusulası) — texte en turc : une mention
        // française sur un bulletin turc serait inopposable et trompeuse.
        'TR' => [
            'notice' => 'Bu ücret hesap pusulası 4857 sayılı İş Kanunu m.37 uyarınca düzenlenmiştir. '
                .'SGK primleri ve vergi kesintileri dahildir.',
            'employer' => [
                'legal_sgk_workplace' => 'İşyeri SGK sicil no',
                'legal_vergi_no' => 'Vergi kimlik no',
                'legal_ticaret_sicil' => 'Ticaret sicil no',
            ],
            'employee' => [
                'matricule' => 'Personel no',
                'national_id' => 'T.C. kimlik no',
            ],
        ],

        // ── Canada ────────────────────────────────────────────────────────
        // Code canadien du travail, art. 254 (bulletin de paie) — pays
        // bilingue : la mention porte les deux langues officielles.
        'CA' => [
            'notice' => 'Pay statement issued under section 254 of the Canada Labour Code / '
                .'Bulletin de paie établi conformément à l\'article 254 du Code canadien du travail.',
            'employer' => [
                'legal_bn' => 'Business Number (BN) / N° d\'entreprise',
                'legal_gst_hst' => 'GST/HST No / N° TPS/TVH',
                'legal_tvq' => 'QST No / N° TVQ',
            ],
            'employee' => [
                'matricule' => 'Employee ID / Matricule',
                'national_id' => 'SIN / NAS',
            ],
        ],

        // ── Cameroun ──────────────────────────────────────────────────────
        // Code du travail camerounais (loi n° 92/007), art. 68 (2) : bulletin
        // individuel dont la contexture est fixée par arrêté ministériel.
        'CM' => [
            'notice' => 'Bulletin de paie individuel établi conformément à l\'article 68 (2) du Code du travail camerounais '
                .'(loi n° 92/007 du 14 août 1992). La mention « pour solde de tout compte » est sans effet (art. 68 (3)).',
            'employer' => [
                'legal_niu' => 'NIU',
                'legal_rccm' => 'RCCM',
                'legal_cnps_employer' => 'N° CNPS employeur',
            ],
            'employee' => [
                'matricule' => 'Matricule',
                'cnps_matricule' => 'N° CNPS salarié',
            ],
        ],

        // ── Côte d'Ivoire ─────────────────────────────────────────────────
        // Code du travail ivoirien (loi n° 2015-532), art. 32.5 et 32.6.
        'CI' => [
            'notice' => 'Bulletin individuel de paie établi conformément à l\'article 32.5 du Code du travail ivoirien '
                .'(loi n° 2015-532 du 20 juillet 2015). La mention « pour solde de tout compte » est sans effet (art. 32.6).',
            'employer' => [
                'legal_ncc' => 'N° compte contribuable (NCC/DGI)',
                'legal_rccm' => 'RCCM',
                'legal_cnps_employer' => 'N° CNPS employeur',
            ],
            'employee' => [
                'matricule' => 'Matricule',
                'cnss_ci_matricule' => 'N° CNPS salarié',
            ],
        ],

        // ── Sénégal ───────────────────────────────────────────────────────
        // Code du travail sénégalais (loi n° 97-17), art. L.116.
        'SN' => [
            'notice' => 'Bulletin de paie dressé et certifié par l\'employeur conformément à l\'article L.116 du Code du travail sénégalais '
                .'(loi n° 97-17 du 1er décembre 1997).',
            'employer' => [
                'legal_ninea' => 'NINEA',
                'legal_rccm' => 'RCCM',
                'legal_ipres_employer' => 'N° IPRES/CSS employeur',
            ],
            'employee' => [
                'matricule' => 'Matricule',
                'ipres_matricule' => 'N° IPRES',
            ],
        ],

        // ── Algérie (gabarit historique, conservé à l'identique) ──────────
        // F-09 (#1539) : les libellés DZ existaient déjà dans le générateur ;
        // ils sont rapatriés ici pour qu'il n'y ait qu'une seule source.
        'DZ' => [
            'notice' => 'Conformément au Code du travail algérien (Loi 90-11). CNAS employeur incluse.',
            'employer' => [
                'legal_nif' => 'NIF',
                'legal_rc' => 'RC',
                'legal_cnas_employer' => 'N° CNAS employeur',
                'legal_idnat' => 'ID.Nat',
            ],
            'employee' => [
                'matricule' => 'Matricule',
                'national_id' => 'N° sécurité sociale',
            ],
        ],
    ];

    /**
     * Pays couverts par un référentiel de mentions (#9045) — exposé pour les
     * tests de couverture et pour la documentation.
     *
     * @return list<string>
     */
    public static function coveredCountries(): array
    {
        return array_keys(self::COUNTRIES);
    }

    /**
     * Mention légale de pied de bulletin pour un pays.
     *
     * Retourne `null` pour un pays non couvert : **aucune mention devinée**
     * (règle 2 de l'en-tête). Le générateur n'imprime alors rien.
     */
    public static function notice(?string $country): ?string
    {
        $code = self::normalize($country);

        return self::COUNTRIES[$code]['notice'] ?? null;
    }

    /**
     * Identifiants légaux employeur présents sur la société, libellés dans la
     * langue du pays. Seules les clés réellement renseignées sont retournées.
     *
     * @return array<string, string>
     */
    public static function employerIdentifiers(?Company $company, ?string $country): array
    {
        if ($company === null) {
            return [];
        }

        $code = self::normalize($country);
        $labels = self::COUNTRIES[$code]['employer'] ?? [];
        $metadata = is_array($company->metadata) ? $company->metadata : [];

        $out = [];
        foreach ($labels as $key => $label) {
            $value = $metadata[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$label] = trim($value);
            }
        }

        return $out;
    }

    /**
     * Identifiants légaux du salarié présents sur le modèle, libellés dans la
     * langue du pays. Seules les valeurs non vides sont retournées.
     *
     * @return array<string, string>
     */
    public static function employeeIdentifiers(?Employee $employee, ?string $country): array
    {
        if ($employee === null) {
            return [];
        }

        $code = self::normalize($country);
        $labels = self::COUNTRIES[$code]['employee'] ?? [];

        $out = [];
        foreach ($labels as $attribute => $label) {
            $value = $employee->getAttribute($attribute);
            if (is_string($value) && trim($value) !== '') {
                $out[$label] = trim($value);
            }
        }

        return $out;
    }

    private static function normalize(?string $country): string
    {
        return strtoupper(trim((string) $country));
    }
}
