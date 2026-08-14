<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use Illuminate\Support\Facades\Lang;

/**
 * Issue #1872 — localisation de l'avertissement de conformité des règles pays.
 *
 * Le moteur de paie expose une disclosure en anglais via
 * CountryRulesInterface::complianceWarning() (texte source, testé en
 * unitaire pur — sans conteneur Laravel). À la frontière API (presenter,
 * simulation, registre des pays), on expose la version LOCALISÉE du
 * catalogue (api/lang/*/payroll.php → `payroll.confidence.*`) pour que les
 * clients web/mobile reçoivent un message dans la langue de la requête
 * (middleware SetLocale). Si une clé de catalogue manque, on retombe sur la
 * disclosure des règles — jamais de message vide.
 */
final class ComplianceWarningLocalizer
{
    public static function for(CountryRulesInterface $rules): string
    {
        $key = 'payroll.confidence.'.$rules->confidenceLevel().'.message';

        $localized = Lang::get($key, ['country' => $rules->countryCode()]);

        if (is_string($localized) && $localized !== $key) {
            return $localized;
        }

        // Repli : disclosure anglaise des règles (contenu source équivalent).
        return $rules->complianceWarning();
    }

    /**
     * Message neutre pour un pays référencé sans règles de paie dédiées
     * (ex. GB/US dans CountryDefaults) — le calcul n'est pas disponible.
     */
    public static function unknown(string $countryCode): string
    {
        $key = 'payroll.confidence.unknown.message';

        $localized = Lang::get($key, ['country' => $countryCode]);

        return is_string($localized) && $localized !== $key ? $localized : '';
    }
}
