<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\UnvalidatedConfidenceLevelException;

/**
 * #9054 (BOS-006V2-8) — garde de validation experte par marché.
 *
 * La spec `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md` § 4.4
 * impose `confidenceLevel: pilot` par défaut et réserve `production` aux
 * référentiels couverts par une fiche `docs/payroll/VALIDATION_EXPERTE.md`
 * signée par un expert local. Cette classe est le SEUL endroit qui décide si
 * une revendication `production` est recevable.
 *
 * Décisions de conception (documentées dans la PR) :
 *  1. **La source de vérité est le fichier de fiches**, pas une constante PHP :
 *     une signature est un acte humain daté, elle doit être auditable dans
 *     l'historique git du dépôt, pas noyée dans du code.
 *  2. **Fail-closed** : fiche absente, illisible, non signée, expirée ou
 *     portant un autre pays ⇒ la revendication `production` est REFUSÉE.
 *     Aucun fallback silencieux (règle § 4.3 de la spec).
 *  3. **`pilot` et `placeholder` passent toujours** : ce sont précisément les
 *     niveaux qui n'affirment rien de plus que « couverture en cours de
 *     validation ».
 *  4. Le registre est **sans état persistant** : il relit les fiches à chaque
 *     appel (mémoïsé par requête) pour qu'un merge de fiche prenne effet sans
 *     redéploiement de configuration.
 */
final class ExpertValidationRegistry
{
    /** Niveau qui exige une fiche signée. */
    public const PRODUCTION = 'production';

    /** Chemin relatif à la racine du dépôt. */
    public const REGISTRY_PATH = 'docs/payroll/VALIDATION_EXPERTE.md';

    /** @var array<string, array{country: string, expert: string, signed_on: string, scope: string}>|null */
    private ?array $entries = null;

    public function __construct(private readonly ?string $registryPath = null) {}

    /**
     * Vérifie qu'un référentiel peut légitimement revendiquer son niveau.
     *
     * @throws UnvalidatedConfidenceLevelException si `production` n'est pas prouvé
     */
    public function assertClaimIsBacked(CountryRulesInterface $rules): void
    {
        $level = strtolower(trim($rules->confidenceLevel()));

        if ($level !== self::PRODUCTION) {
            return;
        }

        $country = strtoupper(trim($rules->countryCode()));
        $entry = $this->entries()[$country] ?? null;

        if ($entry === null) {
            throw new UnvalidatedConfidenceLevelException(
                $country,
                self::PRODUCTION,
                'aucune fiche signée pour ce pays dans '.self::REGISTRY_PATH
            );
        }

        if ($entry['signed_on'] === '' || $entry['expert'] === '') {
            throw new UnvalidatedConfidenceLevelException(
                $country,
                self::PRODUCTION,
                'la fiche existe mais n\'est pas signée (expert ou date manquants)'
            );
        }

        if ($this->isExpired($entry['signed_on'])) {
            throw new UnvalidatedConfidenceLevelException(
                $country,
                self::PRODUCTION,
                sprintf('la fiche signée le %s a dépassé sa durée de validité', $entry['signed_on'])
            );
        }
    }

    /**
     * Vrai si le pays dispose d'une fiche signée et encore valide.
     */
    public function isValidated(string $countryCode): bool
    {
        $entry = $this->entries()[strtoupper(trim($countryCode))] ?? null;

        return $entry !== null
            && $entry['signed_on'] !== ''
            && $entry['expert'] !== ''
            && ! $this->isExpired($entry['signed_on']);
    }

    /**
     * Pays couverts par une fiche signée valide — exposé pour l'API et les tests.
     *
     * @return list<string>
     */
    public function validatedCountries(): array
    {
        $countries = [];
        foreach (array_keys($this->entries()) as $country) {
            if ($this->isValidated($country)) {
                $countries[] = $country;
            }
        }

        sort($countries);

        return $countries;
    }

    /**
     * Une signature est valable 12 mois : les taux légaux bougent chaque année
     * (lois de finances, revalorisations), une fiche périmée ne prouve plus rien.
     */
    private function isExpired(string $signedOn): bool
    {
        $timestamp = strtotime($signedOn);
        if ($timestamp === false) {
            return true;
        }

        return $timestamp < strtotime('-12 months');
    }

    /**
     * Parse le registre markdown. Format d'une ligne de fiche :
     *
     *   | CM | 2026-10-29 | Jean Dupont, expert-comptable ONECCA | Paie + compta |
     *
     * Les lignes d'en-tête et de séparation sont ignorées ; une ligne dont la
     * première cellule n'est pas un code pays à 2 lettres est ignorée.
     *
     * @return array<string, array{country: string, expert: string, signed_on: string, scope: string}>
     */
    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        // __DIR__ = api/app/Modules/Payroll/Infrastructure/Services
        // 6 niveaux remontent à la racine du dépôt (api/ inclus).
        $path = $this->registryPath ?? dirname(__DIR__, 6).'/'.self::REGISTRY_PATH;
        $this->entries = [];

        if (! is_file($path) || ! is_readable($path)) {
            return $this->entries;
        }

        $content = (string) file_get_contents($path);
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (count($cells) < 3) {
                continue;
            }

            $country = strtoupper($cells[0]);
            if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
                continue;
            }

            $this->entries[$country] = [
                'country' => $country,
                'signed_on' => $cells[1] ?? '',
                'expert' => $cells[2] ?? '',
                'scope' => $cells[3] ?? '',
            ];
        }

        return $this->entries;
    }
}
