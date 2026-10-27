<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Feature\Domain\ModuleConsolidationDiff;
use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;

/**
 * BOS-012 (#8199) — `modules:consolidate`, premier palier : DIAGNOSTIC.
 *
 * Contexte (#8199) : `companies.features` et `companies.metadata.modules`
 * sont écrits par des chemins distincts depuis des années, d'où des
 * désynchronisations historiques (#7235, #7432). La cible est que
 * `metadata.modules` devienne une vue **dérivée** de `features`.
 *
 * Cette commande livre le **rapport de diff** exigé comme préalable
 * (« `--dry-run` obligatoire d'abord : rapport de diff par company »).
 *
 * ⚠️ Elle est en LECTURE SEULE PAR CONSTRUCTION : il n'existe aucun chemin
 * d'écriture dans cette classe, donc `--dry-run` est le comportement par
 * défaut et non un mode à activer. L'option est conservée pour que la commande
 * documente l'intention (et pour que `--dry-run` explicite fonctionne dans un
 * script), mais elle ne change rien — il n'y a pas de mode « apply » à
 * refuser, seulement du code qui n'écrit jamais. L'écriture unifiée est le
 * palier suivant de #8199, et ne peut être livrée qu'après avoir mesuré sur
 * staging combien de tenants dérivent : c'est ce que ce rapport produit.
 *
 * Code de sortie : `0` = aucun écart, `1` = écarts détectés (utilisable tel
 * quel dans une step CI).
 */
class ModulesConsolidateCommand extends Command
{
    protected $signature = 'modules:consolidate
                            {--dry-run : Rapport de diff sans aucune écriture (comportement par défaut)}
                            {--company= : Restreindre le rapport à un identifiant de société}
                            {--json : Sortie JSON (archivage du rapport dans la PR)}';

    protected $description = 'BOS-012 (#8199) — rapport de diff modules/features par société (lecture seule)';

    /** Nombre de sociétés parcourues (pour le rapport). */
    private int $scanned = 0;

    public function handle(ModuleRegistry $registry): int
    {
        $mirrors = $registry->horizontalMirrors();
        $defaultFor = static fn (string $key): bool => $registry->defaultFor($key);

        $drifts = [];

        foreach ($this->companies() as $company) {
            $metadata = self::arrayOrEmpty($company->metadata);
            $modules = self::arrayOrEmpty($metadata['modules'] ?? null);
            $features = self::arrayOrEmpty($company->features);

            foreach (ModuleConsolidationDiff::forCompany(
                (string) $company->id,
                $modules,
                $features,
                $mirrors,
                $defaultFor,
            ) as $drift) {
                $drifts[] = $drift;
            }
        }

        if ($this->option('json') === true) {
            $this->line((string) json_encode([
                'registry_version' => $registry->version(),
                'dry_run' => true,
                'companies_scanned' => $this->scanned,
                'companies_with_drift' => count(array_unique(array_column($drifts, 'company_id'))),
                'drift_count' => count($drifts),
                'drifts' => $drifts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $drifts === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf(
            'BOS-012 (#8199) — rapport de diff modules/features (registre %s, AUCUNE écriture)',
            $registry->version()
        ));

        if ($drifts === []) {
            $this->info(sprintf('%d société(s) analysée(s) : aucun écart.', $this->scanned));

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            '%d écart(s) sur %d société(s) (sur %d analysée(s)) :',
            count($drifts),
            count(array_unique(array_column($drifts, 'company_id'))),
            $this->scanned
        ));

        $this->table(
            ['Société', 'Type', 'Clé client', 'Clé flag', 'metadata.modules', 'features', 'Cible'],
            array_map(static fn (array $drift): array => [
                $drift['company_id'],
                $drift['kind'],
                $drift['client_key'],
                $drift['flag_key'],
                self::label($drift['client_value']),
                self::label($drift['flag_value']),
                $drift['target_value'] ? 'true' : 'false',
            ], $drifts)
        );

        return self::FAILURE;
    }

    /**
     * Sociétés à analyser, en flux (`cursor`) : la table peut porter des
     * milliers de tenants et le rapport n'a aucune raison de tout charger en
     * mémoire.
     *
     * @return LazyCollection<int, Company>
     */
    private function companies(): LazyCollection
    {
        $query = Company::query()->orderBy('id');

        $id = $this->option('company');

        if (is_string($id) && $id !== '') {
            $query->where('id', $id);
        }

        return $query->cursor()->map(function (Company $company): Company {
            $this->scanned++;

            return $company;
        });
    }

    private static function label(?bool $value): string
    {
        return match ($value) {
            true => 'true',
            false => 'false',
            null => '(absent)',
        };
    }

    /**
     * Une colonne JSON (`metadata`, `features`) vaut `NULL` en base pour un
     * tenant qui n'a jamais été provisionné, même si le docblock du modèle la
     * décrit comme un tableau : le cast `array` rend `null` sur `NULL`. Le
     * paramètre est donc typé `mixed` — prétendre `array` ici reproduirait
     * exactement le mensonge de type qui a masqué des bugs ailleurs.
     *
     * @return array<mixed>
     */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
