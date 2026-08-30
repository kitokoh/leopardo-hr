<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Feature\Domain\Models\PlatformFeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * MAT-010 (#5868) — Feature flags et kill switch (BC-01 PLATFORM).
 *
 * Service léger pour interroger les feature flags stockés dans
 * `companies.features` (JSONB, par tenant) et les kill switches plateforme
 * (`platform_feature_flags`, fail-closed, audités).
 *
 * Résolution d'un flag (`resolve` / `isKilled`) :
 *   1. les entrées plateforme sont filtrées par le contexte passé
 *      (tenant_id, solution, provider, version, module) ;
 *   2. la dimension la PLUS SPÉCIFIQUE qui matche gagne (version > provider
 *      > solution > tenant > module > global) ;
 *   3. une entrée `enabled = true` tue le flag, une entrée `enabled = false`
 *      le ré-autorise explicitement (override d'un kill moins spécifique) ;
 *   4. aucune entrée → non tué (le défaut de la feature elle-même reste
 *      fail-closed pour les clés inconnues, voir `enabled()`).
 *
 * Utilisation :
 *   FeatureFlag::enabled('finance', $company)              => bool (tenant)
 *   FeatureFlag::isKilled('finance', ['tenant_id' => $id]) => bool (plateforme)
 *   FeatureFlag::resolve('leo_ai', $company, ['solution' => 'fuel_station'])
 */
class FeatureFlag
{
    /** Ordre de spécificité croissante des dimensions de kill switch. */
    private const DIMENSION_ORDER = ['global', 'module', 'tenant', 'solution', 'provider', 'version'];

    /**
     * Retourne true si la feature est active pour la company donnee.
     * Les features inconnues retournent false (fail-closed).
     */
    public static function enabled(string $key, ?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        return $company->hasFeature($key);
    }

    /**
     * Résolution complète : flag tenant actif ET non tué par un kill switch
     * plateforme correspondant au contexte (MAT-010).
     *
     * @param  array<string, mixed>  $context  tenant_id|module|solution|provider|version
     */
    public static function resolve(string $key, ?Company $company, array $context = []): bool
    {
        if (! self::enabled($key, $company)) {
            return false;
        }

        return ! self::isKilled($key, $context);
    }

    /**
     * Retourne true si un kill switch plateforme tue la clé pour le contexte
     * donné (fail-closed : tout kill correspondant coupe la feature).
     *
     * @param  array<string, mixed>  $context  tenant_id|module|solution|provider|version
     */
    public static function isKilled(string $key, array $context = []): bool
    {
        $flags = self::matchingFlags($key, $context);

        if ($flags->isEmpty()) {
            return false;
        }

        // La dimension la plus spécifique qui matche décide.
        /** @var PlatformFeatureFlag $winner */
        $winner = $flags->sortBy(fn (PlatformFeatureFlag $flag): int => (int) array_search($flag->dimension, self::DIMENSION_ORDER, true))->last();

        return (bool) $winner->enabled;
    }

    /**
     * Entrées plateforme correspondant à la clé ET au contexte.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, PlatformFeatureFlag>
     */
    public static function matchingFlags(string $key, array $context = []): Collection
    {
        $flagKeys = array_unique(array_filter([
            $key,
            isset($context['module']) && is_string($context['module']) ? $context['module'] : null,
        ]));

        if ($flagKeys === []) {
            return new Collection();
        }

        $rows = PlatformFeatureFlag::query()->whereIn('flag_key', $flagKeys)->get();

        return $rows->filter(fn (PlatformFeatureFlag $flag): bool => self::rowMatches($flag, $key, $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function rowMatches(PlatformFeatureFlag $flag, string $key, array $context): bool
    {
        return match ($flag->dimension) {
            'global' => true,
            'module' => $flag->flag_key === $key || $flag->flag_key === ($context['module'] ?? null),
            'tenant' => $flag->dimension_value !== null && (string) $flag->dimension_value === (string) ($context['tenant_id'] ?? ''),
            'solution' => $flag->dimension_value !== null && $flag->dimension_value === ($context['solution'] ?? null),
            'provider' => $flag->dimension_value !== null && $flag->dimension_value === ($context['provider'] ?? null),
            'version' => $flag->dimension_value !== null && $flag->dimension_value === ($context['version'] ?? null),
            default => false,
        };
    }

    /**
     * Crée ou met à jour un kill switch / feature flag plateforme, avec
     * audit append-only (qui, quand, avant → après, raison).
     *
     * @return PlatformFeatureFlag
     */
    public static function setFlag(
        string $flagKey,
        string $dimension = 'global',
        ?string $dimensionValue = null,
        bool $enabled = true,
        ?string $reason = null,
        ?string $changedBy = null,
    ): PlatformFeatureFlag {
        $flag = PlatformFeatureFlag::query()
            ->where('flag_key', $flagKey)
            ->where('dimension', $dimension)
            ->where('dimension_value', $dimensionValue)
            ->first();

        if ($flag === null) {
            $flag = new PlatformFeatureFlag([
                'flag_key' => $flagKey,
                'dimension' => $dimension,
                'dimension_value' => $dimensionValue,
            ]);
            $flag->appendHistory([
                'from' => null,
                'to' => $enabled,
                'by' => $changedBy,
                'reason' => $reason,
            ]);
        } elseif ((bool) $flag->enabled !== $enabled) {
            $flag->appendHistory([
                'from' => (bool) $flag->enabled,
                'to' => $enabled,
                'by' => $changedBy,
                'reason' => $reason,
            ]);
        }

        $flag->enabled = $enabled;
        $flag->reason = $reason;
        $flag->changed_by = $changedBy;

        try {
            $flag->save();
        } catch (Throwable $exception) {
            // Course possible entre deux admins : on relit puis on réessaie
            // une fois avant de propager (opération admin rare).
            $flag = PlatformFeatureFlag::query()
                ->where('flag_key', $flagKey)
                ->where('dimension', $dimension)
                ->where('dimension_value', $dimensionValue)
                ->firstOrFail();
            $flag->enabled = $enabled;
            $flag->reason = $reason;
            $flag->changed_by = $changedBy;
            $flag->save();
        }

        return $flag;
    }

    /**
     * Retourne la carte complete des flags connus pour la company (resolus).
     * Pratique pour serialiser dans /auth/me.
     *
     * @return array<string, bool>
     */
    public static function for(?Company $company): array
    {
        $flags = [];

        foreach (Company::KNOWN_MODULES as $module) {
            $flags[$module] = self::enabled($module, $company);
        }

        return $flags;
    }

    /**
     * Toutes les entrées plateforme (kill switches), pour l'admin.
     *
     * @return Collection<int, PlatformFeatureFlag>
     */
    public static function allFlags(): Collection
    {
        return PlatformFeatureFlag::query()
            ->orderBy('flag_key')
            ->orderBy('dimension')
            ->get();
    }
}
