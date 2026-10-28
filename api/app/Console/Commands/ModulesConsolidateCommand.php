<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Feature\Domain\ModuleConsolidationDiff;
use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
 * ⚠️ **Le diagnostic est le comportement par défaut, et l'écriture est
 * opt-in.** L'absence de `--apply` ne produit AUCUNE écriture : `--dry-run` est
 * donc conservé comme option explicite (utile dans un script qui documente son
 * intention) mais ne change rien, parce qu'il n'y a rien à refuser.
 *
 * `--apply` (palier 2 de #8199) aligne la vue dérivée `metadata.modules` sur
 * `features`, via {@see Company::syncHorizontalToolMirror()} — l'écrivain
 * partagé avec la console plateforme. Deux garanties de conception :
 *
 *   1. **`features` n'est jamais écrit.** La cible de #8199 est que
 *      `metadata.modules` devienne une vue dérivée ; réécrire `features` ferait
 *      de la vue une source. C'est aussi ce qui rend le critère d'acceptation 2
 *      (« `hasFeature` avant/après identique ») structurellement vrai plutôt que
 *      simplement constaté : `hasFeature()` ne lit que `features`.
 *   2. **Une société par transaction.** Un `--apply` interrompu laisse les
 *      sociétés déjà traitées convergées et les suivantes intactes — jamais une
 *      carte à moitié réécrite. La commande est idempotente : une seconde
 *      exécution ne trouve plus d'écart et ne réécrit rien.
 *
 * Code de sortie : `0` = aucun écart (ou tout appliqué), `1` = écarts
 * détectés sans `--apply`, ou société non convergée après application
 * (utilisable tel quel dans une step CI).
 */
class ModulesConsolidateCommand extends Command
{
    protected $signature = 'modules:consolidate
                            {--dry-run : Rapport de diff sans aucune écriture (comportement par défaut)}
                            {--apply : Aligner la vue dérivée metadata.modules sur features (écrit metadata uniquement)}
                            {--company= : Restreindre le rapport à un identifiant de société}
                            {--json : Sortie JSON (archivage du rapport dans la PR)}';

    protected $description = 'BOS-012 (#8199) — rapport de diff modules/features par société (lecture seule, ou --apply)';

    /** Nombre de sociétés parcourues (pour le rapport). */
    private int $scanned = 0;

    public function handle(ModuleRegistry $registry): int
    {
        $mirrors = $registry->horizontalMirrors();
        $defaultFor = static fn (string $key): bool => $registry->defaultFor($key);

        $apply = $this->option('apply') === true;

        $drifts = [];
        $applied = 0;
        $unconverged = [];

        foreach ($this->companies() as $company) {
            $metadata = self::arrayOrEmpty($company->metadata);
            $modules = self::arrayOrEmpty($metadata['modules'] ?? null);
            $features = self::arrayOrEmpty($company->features);

            $companyDrifts = ModuleConsolidationDiff::forCompany(
                (string) $company->id,
                $modules,
                $features,
                $mirrors,
                $defaultFor,
            );

            if ($companyDrifts === []) {
                continue;
            }

            foreach ($companyDrifts as $drift) {
                $drifts[] = $drift;
            }

            if (! $apply) {
                continue;
            }

            if ($this->applyCompany($company)) {
                $applied++;
            } else {
                // L'écrivain partagé n'a pas convergé : on le dit, on ne le
                // cache pas. Un `--apply` qui rapporte « appliqué » sans que le
                // diff suivant soit vide serait un mensonge silencieux.
                $unconverged[] = (string) $company->id;
            }
        }

        if ($this->option('json') === true) {
            $this->line((string) json_encode([
                'registry_version' => $registry->version(),
                'dry_run' => ! $apply,
                'companies_scanned' => $this->scanned,
                'companies_with_drift' => count(array_unique(array_column($drifts, 'company_id'))),
                'drift_count' => count($drifts),
                'companies_applied' => $applied,
                'companies_unconverged' => $unconverged,
                'drifts' => $drifts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            if ($unconverged !== []) {
                return self::FAILURE;
            }

            return $drifts === [] || $apply ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf(
            'BOS-012 (#8199) — rapport de diff modules/features (registre %s, %s)',
            $registry->version(),
            $apply ? 'MODE APPLY : metadata.modules uniquement' : 'AUCUNE écriture'
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

        if ($apply) {
            if ($unconverged !== []) {
                $this->error(sprintf(
                    '%d société(s) NON convergée(s) après application : %s',
                    count($unconverged),
                    implode(', ', array_slice($unconverged, 0, 10))
                ));

                return self::FAILURE;
            }

            $this->info(sprintf('%d société(s) alignée(s) : metadata.modules est désormais dérivé de features.', $applied));

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * Applique la consolidation d'UNE société, dans SA transaction.
     *
     * Une transaction par société, et non une pour tout le lot : un `--apply`
     * interrompu (timeout, Ctrl-C, erreur base) laisse les sociétés déjà
     * traitées convergées et les suivantes intactes. Une transaction unique
     * ferait tout échouer — et sur des milliers de tenants, la garder ouverte
     * pendant tout le parcours est un verrou qui n'a aucune raison d'exister.
     *
     * La relecture par requête qualifiée n'est pas une précaution de style :
     * `Company` cible `public.companies` explicitement, mais la commande peut
     * tourner avec un `search_path` tenant, et c'est l'écriture QUALIFIÉE qui a
     * déjà été corrigée ailleurs pour ce piège (#7322).
     *
     * @return bool `true` si la société est convergée après l'écriture
     */
    private function applyCompany(Company $company): bool
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';

        return (bool) DB::transaction(function () use ($company, $table): bool {
            $fresh = Company::query()
                ->from($table)
                ->where('id', $company->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh instanceof Company) {
                return true;
            }

            $fresh->syncHorizontalToolMirror();

            // ⚠️ `metadata` seul. `features` n'est JAMAIS réécrit par cette
            // commande : c'est la garantie de parité `hasFeature` de #8199.
            DB::table($table)
                ->where('id', $fresh->id)
                ->update([
                    'metadata' => json_encode($fresh->metadata ?? [], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

            // Relecture : on ne déclare « appliqué » que si un second passage de
            // l'écrivain ne trouve PLUS rien à faire. C'est la définition
            // opérationnelle de la convergence, et elle se vérifie au lieu de se
            // supposer.
            $verify = Company::query()
                ->from($table)
                ->where('id', $company->id)
                ->first();

            if (! $verify instanceof Company) {
                return true;
            }

            return ! $verify->syncHorizontalToolMirror();
        });
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
