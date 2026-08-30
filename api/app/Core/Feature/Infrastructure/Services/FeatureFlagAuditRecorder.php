<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use Illuminate\Support\Facades\DB;

/**
 * MAT-010 (#5868) — Piste d'audit des changements de feature flags.
 *
 * Chaque bascule (activation/désactivation par tenant) est enregistrée dans
 * `feature_flag_audits` (schéma public, table plateforme) : qui, quand, de
 * quelle valeur vers quelle valeur, et depuis quelle source. Un kill switch
 * est par construction une action d'exploitation (config/env) — c'est le
 * changement de config qui est traçable en déploiement ; les bascules
 * tenant→tenant passent ici.
 */
final class FeatureFlagAuditRecorder
{
    public function record(
        string $companyId,
        string $flagKey,
        bool $previousValue,
        bool $newValue,
        string $source,
        ?int $actorUserId = null,
    ): void {
        if ($previousValue === $newValue) {
            return;
        }

        $table = DB::getDriverName() === 'pgsql' ? 'public.feature_flag_audits' : 'feature_flag_audits';

        DB::table($table)->insert([
            'company_id' => $companyId,
            'flag_key' => $flagKey,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'source' => $source,
            'actor_user_id' => $actorUserId,
            'created_at' => now(),
        ]);
    }
}
