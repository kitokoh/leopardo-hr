<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Feature\Domain\Models\PlatformFeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MAT-010 (#5868) — Gestion des kill switches / feature flags plateforme.
 *
 * Usage :
 *   php artisan platform:feature-flag leo_ai --on --reason "pilote fuel" --actor ops@kitokoh.com
 *   php artisan platform:feature-flag leo_ai --dimension tenant --value 42 --off --reason "incident"
 *   php artisan platform:feature-flag --list
 *
 * Toute modification est auditée (historique append-only + log) ; l'état
 * par défaut reste fail-closed.
 */
class PlatformFeatureFlagCommand extends Command
{
    protected $signature = 'platform:feature-flag
        {flag? : Clé du flag/module (ex. leo_ai, finance)}
        {--list : Liste toutes les entrées plateforme}
        {--dimension=global : global|module|tenant|solution|provider|version}
        {--value= : Valeur de dimension (id tenant, nom de solution/provider/version)}
        {--on : Active le flag (défaut si ni --on ni --off)}
        {--off : Désactive le flag}
        {--reason= : Raison de la modification (obligatoire en --off)}
        {--actor= : Acteur de la modification (email/système)}';

    protected $description = 'Gère les kill switches / feature flags plateforme (MAT-010, #5868).';

    public function handle(): int
    {
        if ($this->option('list') || $this->argument('flag') === null) {
            return $this->listFlags();
        }

        $flagKey = (string) $this->argument('flag');
        $dimension = (string) $this->option('dimension');

        if (! in_array($dimension, PlatformFeatureFlag::DIMENSIONS, true)) {
            $this->error("Dimension invalide : {$dimension} (attendue : ".implode('|', PlatformFeatureFlag::DIMENSIONS).').');

            return self::FAILURE;
        }

        $enabled = $this->option('off') ? false : true;
        $reason = $this->option('reason');
        $actor = $this->option('actor') ?? 'cli';

        if (! $enabled && $reason === null) {
            $this->warn('Conseil : documenter la raison d\'un kill switch (--reason=...) pour l\'audit.');

            $reason = 'kill switch CLI sans raison';
        }

        $value = $this->option('value');
        $value = is_string($value) && $value !== '' ? $value : null;

        if ($dimension !== 'global' && $dimension !== 'module' && $value === null) {
            $this->error("La dimension '{$dimension}' exige --value=.");

            return self::FAILURE;
        }

        $flag = FeatureFlag::setFlag(
            $flagKey,
            $dimension,
            $value,
            $enabled,
            $reason,
            $actor,
        );

        Log::info('platform.feature_flag.set', [
            'flag_key' => $flagKey,
            'dimension' => $dimension,
            'dimension_value' => $value,
            'enabled' => $enabled,
            'reason' => $reason,
            'actor' => $actor,
        ]);

        $this->info(sprintf(
            '%s %s [%s%s] %s',
            $enabled ? 'Activé' : 'Désactivé (kill)',
            $flagKey,
            $dimension,
            $value !== null ? "={$value}" : '',
            $reason !== null ? '— '.$reason : '',
        ));

        return self::SUCCESS;
    }

    private function listFlags(): int
    {
        $flags = FeatureFlag::allFlags();

        if ($flags->isEmpty()) {
            $this->info('Aucun kill switch / feature flag plateforme enregistré (état nominal fail-closed).');

            return self::SUCCESS;
        }

        $rows = $flags->map(fn (PlatformFeatureFlag $flag): array => [
            'flag_key' => $flag->flag_key,
            'dimension' => $flag->dimension.($flag->dimension_value !== null ? '='.$flag->dimension_value : ''),
            'enabled' => $flag->enabled ? 'ON' : 'OFF',
            'reason' => $flag->reason ?? '',
            'changed_by' => $flag->changed_by ?? '',
            'updated_at' => $flag->updated_at?->toDateTimeString() ?? '',
        ]);

        $this->table(['flag', 'dimension', 'état', 'raison', 'par', 'maj'], $rows->all());

        return self::SUCCESS;
    }
}
