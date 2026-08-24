<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated ADR-0016 Phase 4 (#5355) — fusionnée dans `attendance:auto-close`
 *             (une seule fermeture automatique). Cette commande est conservée
 *             comme alias délégué pendant la transition ; elle sera supprimée
 *             en Phase 5 (#5356).
 *
 * Ferme automatiquement les sessions GPS restées ouvertes trop longtemps
 * (exemple : app tuée, perte réseau, crash). Délègue à
 * `attendance:auto-close --sessions-only` (même cycle, même logique).
 */
class AutoCloseGeoSessionsCommand extends Command
{
    protected $signature = 'smart-attendance:auto-close
                           {--hours=14 : Fermer les sessions ouvertes depuis plus de N heures}
                           {--company= : ID de la société (tenant) cible — sinon tous les tenants actifs}
                           {--dry-run  : Afficher sans fermer}';

    protected $description = '@deprecated — alias de attendance:auto-close --sessions-only (ADR-0016 Phase 4, #5355). Ferme les sessions GPS restées ouvertes.';

    public function handle(): int
    {
        $args = ['--sessions-only' => true];

        if ($this->option('hours') !== null && (int) $this->option('hours') !== 14) {
            $args['--hours'] = (string) $this->option('hours');
        }

        if ($this->option('company') !== null) {
            $args['--company'] = (string) $this->option('company');
        }

        if ((bool) $this->option('dry-run')) {
            $args['--dry-run'] = true;
        }

        return $this->call('attendance:auto-close', $args);
    }
}
