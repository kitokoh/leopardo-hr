<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;

/**
 * #7384 — applique les réglages enregistrés depuis le cockpit sur la config de
 * l'assistant (`config('ai.*')`).
 *
 * Priorité : **base > environnement**. Une base vide ⇒ aucune écriture de
 * config ⇒ le comportement d'origine est strictement préservé.
 *
 * Pourquoi surcharger la config plutôt que lire la base à chaque usage : tout
 * le code IA existant (`AIFeatureCheck`, `Orchestrator`, `AiCloudPolicy`, les
 * clients fournisseurs…) lit `config('ai.*')`. Surcharger au boot rend les
 * réglages éditables **sans toucher une seule ligne de ce code**.
 */
final class PlatformAiSettingsApplier
{
    /** Clé de cache des valeurs résolues (invalidée à chaque écriture). */
    public const CACHE_KEY = 'platform_ai_settings:resolved';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly PlatformAiSettingsRepository $repository,
    ) {}

    public function apply(): void
    {
        $values = $this->values();
        if ($values === []) {
            return;
        }

        foreach ($values as $key => $value) {
            $definition = PlatformAiSettingsCatalog::find($key);
            if ($definition === null) {
                continue;
            }

            config([$definition['config'] => $this->cast($definition['type'], $value)]);
        }
    }

    /**
     * Invalide le cache — appelé après chaque écriture pour que la modification
     * soit visible immédiatement (sinon un admin qui vient de poser sa clé
     * croirait que ça ne marche pas).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private function values(): array
    {
        try {
            /** @var array<string, string> $values */
            $values = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                fn (): array => $this->repository->resolvableValues(),
            );

            return $values;
        } catch (\Throwable) {
            // Cache indisponible (Redis absent en local, par ex.) : on lit
            // directement la base plutôt que de perdre la surcharge.
            return $this->repository->resolvableValues();
        }
    }

    private function cast(string $type, string $value): bool|string
    {
        if ($type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOL) === true;
        }

        return $value;
    }
}
