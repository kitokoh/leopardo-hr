<?php

declare(strict_types=1);

namespace App\Modules\Branding\Console\Commands;

use App\Modules\Branding\Infrastructure\Services\BrandBackfillService;
use Illuminate\Console\Command;

/**
 * Commande de backfill du branding (BC-41, #9582).
 *
 * ## Rejouable par conception
 *
 * La commande est idempotente : deux exécutions produisent le même état. Elle
 * est donc sûre à relancer après un incident, et peut être planifiée tant que
 * la double lecture `companies.metadata.branding` reste en place.
 *
 * ## Pourquoi une commande et pas une migration
 *
 * Voir `BrandBackfillService` : le JSON source n'a pas de forme garantie, une
 * migration de données ne gère pas confortablement ces cas et n'est pas faite
 * pour être rejouée. Une commande, si.
 *
 * ## Pas d'option `--dry-run`
 *
 * Elle a été retirée volontairement : le service n'a pas de mode « compter sans
 * écrire », et exposer une option qui ne ferait rien serait exactement le genre
 * de surface qui fait croire à une garantie inexistante. Pour mesurer avant
 * d'agir, utiliser `--limit=1` sur un tenant de test.
 */
final class BrandingBackfillCommand extends Command
{
    protected $signature = 'branding:backfill
        {--limit= : Nombre maximal de tenants traités}';

    protected $description = 'Reconstitue une Brand par défaut depuis companies.metadata.branding (BC-41, idempotent)';

    public function handle(BrandBackfillService $backfill): int
    {
        $limitOption = $this->option('limit');
        $limit = is_string($limitOption) && $limitOption !== '' ? (int) $limitOption : null;

        $report = $backfill->run($limit);

        $this->table(
            ['Indicateur', 'Valeur'],
            [
                ['Tenants traités', $report['processed']],
                ['Brands créées', $report['created']],
                ['Brands mises à jour', $report['updated']],
                ['Ignorés (pas de branding exploitable)', $report['skipped']],
                ['Erreurs', count($report['errors'])],
            ],
        );

        foreach ($report['errors'] as $error) {
            $this->error($error);
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
