<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Log;

/**
 * BOS-011 (#8198, ADR-0026) — point d'accès unique au registre unifié
 * modules / features / solutions pendant la transition dual-read.
 *
 * Rôle :
 *   - résoudre le mode courant (`legacy` | `dual` | `registry`, config
 *     `module-registry.mode` — inconnu ⇒ repli fail-safe `legacy`) ;
 *   - servir la donnée demandée depuis la source du mode (listes legacy
 *     vs dérivations du {@see ModuleRegistry}) ;
 *   - en mode `dual`, comparer les deux chemins, SERVIR LEGACY et
 *     journaliser toute divergence (JSON sans PII : contexte + clé +
 *     valeurs legacy/registry + company_id éventuel).
 *
 * La parité legacy ⇄ registre est par ailleurs gardée en CI
 * (tests/Unit/Core/Feature) : en l'absence de dérive, le mode dual ne
 * journalise rien.
 */
final class ModuleRegistryGateway
{
    public const MODE_LEGACY = 'legacy';

    public const MODE_DUAL = 'dual';

    public const MODE_REGISTRY = 'registry';

    public function __construct(
        private readonly ModuleRegistry $registry,
    ) {}

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    public function mode(): string
    {
        $mode = (string) config('module-registry.mode', self::MODE_LEGACY);

        return in_array($mode, [self::MODE_LEGACY, self::MODE_DUAL, self::MODE_REGISTRY], true)
            ? $mode
            : self::MODE_LEGACY;
    }

    /**
     * Défaut versionné d'un flag selon le mode (inconnu ⇒ false — fail-closed).
     */
    public function defaultFor(string $key, ?Company $company = null): bool
    {
        $legacy = (bool) config("feature-flags.flags.{$key}.default", false);

        if ($this->mode() === self::MODE_REGISTRY) {
            return $this->registry->defaultFor($key);
        }

        if ($this->mode() === self::MODE_DUAL) {
            $registryValue = $this->registry->defaultFor($key);

            if ($registryValue !== $legacy) {
                $this->logDivergence('default', $key, $legacy, $registryValue, $company);
            }
        }

        return $legacy;
    }

    /**
     * Allowlist console plateforme selon le mode (remplace KNOWN_MODULES à
     * terme — l'ordre contractuel est préservé par la dérivation).
     *
     * @return list<string>
     */
    public function knownModules(): array
    {
        $legacy = Company::KNOWN_MODULES;

        if ($this->mode() === self::MODE_REGISTRY) {
            return $this->registry->knownModules();
        }

        if ($this->mode() === self::MODE_DUAL) {
            $registryValue = $this->registry->knownModules();

            if ($registryValue !== $legacy) {
                $this->logListDivergence('knownModules', $legacy, $registryValue);
            }
        }

        return $legacy;
    }

    /**
     * Outils horizontaux du catalogue client selon le mode (remplace
     * HORIZONTAL_TOOLS à terme).
     *
     * @return list<string>
     */
    public function horizontalTools(): array
    {
        $legacy = Company::HORIZONTAL_TOOLS;

        if ($this->mode() === self::MODE_REGISTRY) {
            return $this->registry->horizontalTools();
        }

        if ($this->mode() === self::MODE_DUAL) {
            $registryValue = $this->registry->horizontalTools();

            if ($registryValue !== $legacy) {
                $this->logListDivergence('horizontalTools', $legacy, $registryValue);
            }
        }

        return $legacy;
    }

    /**
     * Correspondance outil horizontal ⇒ flag plateforme selon le mode
     * (remplace HORIZONTAL_TOOL_FEATURES à terme).
     *
     * @return array<string, string>
     */
    public function horizontalMirrors(): array
    {
        $legacy = Company::HORIZONTAL_TOOL_FEATURES;

        if ($this->mode() === self::MODE_REGISTRY) {
            return $this->registry->horizontalMirrors();
        }

        if ($this->mode() === self::MODE_DUAL) {
            $registryValue = $this->registry->horizontalMirrors();

            if ($registryValue != $legacy) {
                $this->logDivergence('horizontalMirrors', '(map)', $legacy, $registryValue);
            }
        }

        return $legacy;
    }

    /**
     * FR-7 — la clé est-elle un flag tenant connu ? (refus de kill fail-closed)
     */
    public function isKnown(string $key): bool
    {
        return $this->registry->isKnown($key);
    }

    /**
     * FR-7 — la clé est-elle killable ? (`killable: false` ⇒ refus + audit)
     */
    public function isKillable(string $key): bool
    {
        return $this->registry->isKillable($key);
    }

    /**
     * Journalise une divergence dual-read (JSON, sans PII — company_id +
     * clé + valeurs des deux chemins, spec §2 US2).
     */
    public function logDivergence(string $context, string $key, mixed $legacyValue, mixed $registryValue, ?Company $company = null): void
    {
        Log::channel($this->divergenceChannel())->warning('module_registry.divergence', [
            'context' => $context,
            'key' => $key,
            'legacy' => $legacyValue,
            'registry' => $registryValue,
            'company_id' => $company?->id,
            'correlation_id' => correlation_id(),
        ]);
    }

    /**
     * @param  list<string>  $legacy
     * @param  list<string>  $registryValue
     */
    private function logListDivergence(string $context, array $legacy, array $registryValue): void
    {
        Log::channel($this->divergenceChannel())->warning('module_registry.divergence', [
            'context' => $context,
            'key' => '(list)',
            'legacy' => implode(',', $legacy),
            'registry' => implode(',', $registryValue),
            'company_id' => null,
            'correlation_id' => correlation_id(),
        ]);
    }

    private function divergenceChannel(): string
    {
        $channel = (string) config('module-registry.divergence_channel', 'audit');

        return $channel !== '' ? $channel : 'audit';
    }
}
