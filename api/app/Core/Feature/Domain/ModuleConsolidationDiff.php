<?php

declare(strict_types=1);

namespace App\Core\Feature\Domain;

/**
 * BOS-012 (#8199) — calcul du diff de consolidation des données features
 * d'un tenant.
 *
 * Contexte (#8199) : `companies.features` (flags plateforme) et
 * `companies.metadata.modules` (sélection client) sont écrits par des chemins
 * distincts depuis des années (`Company::activateHorizontalTool`,
 * `PATCH /platform/companies/{id}/features`, provisioning trial…), d'où des
 * désynchronisations historiques (#7235, #7432). La cible de #8199 :
 * `metadata.modules` devient une **vue dérivée** de `features`, jamais une
 * source.
 *
 * Ce calculateur est volontairement PUR (aucun accès base, aucune écriture) :
 * il prend les deux cartes d'une company et rend la liste des écarts, avec la
 * valeur cible que l'écriture unifiée posera. C'est ce qui rend le `--dry-run`
 * de `modules:consolidate` démontrable et testable sans base de données.
 *
 * ⚠️ **La règle de résolution n'est pas définie ici.** Elle vit dans
 * {@see ModuleMirror}, partagée avec l'écriture unifiée : si le diagnostic et
 * l'écriture avaient chacun la leur, un rapport pourrait annoncer un écart que
 * l'écriture ne corrige pas — le `--apply` bouclerait sans converger. Une seule
 * règle, deux appelants.
 *
 * Périmètre : uniquement les clés **miroir** (`metadata_mirror` non nul dans
 * {@see ModuleRegistry}), c'est-à-dire les outils horizontaux qui ont à la fois
 * une clé client et un flag plateforme. Les outils sans flag (`employees`,
 * `attendance`, `absences`, `contracts`, `payroll`, `reports`, `marketing`) ne
 * vivent QUE dans `metadata.modules` : ce ne sont pas des dérivations, donc
 * jamais des écarts — les inclure inventerait des divergences inexistantes.
 *
 * @phpstan-type Drift array{
 *     company_id: string,
 *     client_key: string,
 *     flag_key: string,
 *     kind: string,
 *     client_value: ?bool,
 *     flag_value: ?bool,
 *     target_value: bool,
 * }
 */
final class ModuleConsolidationDiff
{
    /** Les deux cartes déclarent des valeurs opposées (la désync historique). */
    public const KIND_DIVERGENCE = 'divergence';

    /** Le client a déclaré une valeur, le flag plateforme est absent. */
    public const KIND_MISSING_MIRROR = 'missing_mirror';

    /** Le flag plateforme est déclaré, le client n'a aucune clé (UI muette). */
    public const KIND_MISSING_CLIENT_KEY = 'missing_client_key';

    /**
     * Écarts entre `metadata.modules` et `features` pour UNE company.
     *
     * Règle de résolution : **le flag plateforme gagne** (`features`), à
     * défaut le défaut versionné du registre. C'est la cible explicite de
     * #8199 — `metadata.modules` est une vue dérivée, pas une source — et
     * cela évite qu'une sélection client historique ressuscite un module que
     * la console plateforme a désactivé (kill switch d'entitlement).
     *
     * `null` des deux côtés = aucune déclaration (tenant historique, sélection
     * jamais faite) : aucun écart n'est inventé.
     *
     * @param  array<mixed>  $modules  carte brute `metadata.modules`
     * @param  array<mixed>  $features  carte brute `companies.features`
     * @param  array<string, string>  $mirrors  clé client => clé de flag
     * @param  callable(string): bool  $defaultFor  défaut versionné du registre
     * @return list<Drift>
     */
    public static function forCompany(
        string $companyId,
        array $modules,
        array $features,
        array $mirrors,
        callable $defaultFor,
    ): array {
        $drifts = [];

        foreach ($mirrors as $clientKey => $flagKey) {
            $clientValue = self::boolOrNull($modules[$clientKey] ?? null);
            $flagValue = self::boolOrNull($features[$flagKey] ?? null);

            // Rien de déclaré : pas de sélection, donc pas d'écart à corriger.
            if ($clientValue === null && $flagValue === null) {
                continue;
            }

            $target = ModuleMirror::targetValue($flagValue, $defaultFor($flagKey));

            if ($clientValue === null) {
                $kind = self::KIND_MISSING_CLIENT_KEY;
            } elseif ($flagValue === null) {
                // Le flag plateforme n'est pas DÉCLARÉ : la valeur EFFECTIVE de
                // la source est le défaut versionné du registre (`$target`), car
                // `Company::hasFeature()` rend `(bool) ($features[$key] ?? $default)`.
                //
                // ⚠️ Si le client s'accorde DÉJÀ avec cette valeur effective, il
                // n'y a rien à consolider — et le dire est indispensable :
                // l'écriture ne pose que la VUE (`metadata.modules`), jamais la
                // source. Signaler l'écart quand même ferait rapporter à
                // `--apply` un écart qu'il ne peut pas résoudre, donc un
                // `--apply` qui ne converge jamais et une commande non
                // idempotente.
                if ($clientValue === $target) {
                    continue;
                }

                $kind = self::KIND_MISSING_MIRROR;
            } elseif ($clientValue !== $flagValue) {
                $kind = self::KIND_DIVERGENCE;
            } else {
                // Les deux chemins s'accordent : rien à consolider.
                continue;
            }

            $drifts[] = [
                'company_id' => $companyId,
                'client_key' => $clientKey,
                'flag_key' => $flagKey,
                'kind' => $kind,
                'client_value' => $clientValue,
                'flag_value' => $flagValue,
                'target_value' => $target,
            ];
        }

        return $drifts;
    }

    /**
     * Délégué à {@see ModuleMirror} : la règle « seul un booléen PHP est une
     * déclaration » doit être IDENTIQUE des deux côtés (diagnostic et
     * écriture), sinon les deux ne s'accordent plus sur ce qu'est un écart.
     */
    private static function boolOrNull(mixed $value): ?bool
    {
        return ModuleMirror::boolOrNull($value);
    }
}
