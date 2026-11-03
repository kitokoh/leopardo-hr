<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Shared\Services\PublicCommerce\PlatformSettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * COULOIR 16 — M0 · Générateur de données synthétiques de la surface publique.
 *
 * ## Ce que la commande produit
 *
 * Trois tenants fictifs (un par ville de `config/public_synthetic.php`), chacun
 * avec ses entreprises et ses établissements publiés ou non. Le jeu sert à
 * développer et à tester la découverte publique, la fiche commerce et les
 * états d'écran — sans jamais approcher une donnée de production.
 *
 * ## Ce que la commande ne fait PAS
 *
 *  - elle ne crée **aucun** compte utilisateur : le parcours « Contacter »
 *    exige un OTP téléphone qui dépend du lot L1 (porte (b) de M2) ;
 *  - elle ne crée **aucune** commande ni réservation : l'index d'activités
 *    (`public_activity_index`) est alimenté par événements de verticales, et
 *    fabriquer des lignes à la main produirait un index qui mentirait sur son
 *    origine ;
 *  - elle ne touche à **aucune** table de tenant autre que
 *    `restaurant_branches` — la seule surface publique réellement exposée par
 *    un provider aujourd'hui.
 *
 * ## Idempotence
 *
 * Chaque tenant est identifié par un slug déterministe (`pub-synth-<ville>`).
 * Rejouer la commande met à jour l'existant au lieu d'en créer un doublon :
 * `updateOrCreate` sur le slug, `updateOrInsert` sur `(company_id, code)` des
 * branches. Un second passage laisse la base dans un état identique.
 *
 * ## Nettoyage
 *
 * `--clean` supprime les trois tenants et leurs branches. Il ne supprime QUE
 * les slugs préfixés par `pub-synth-` : un slug hors préfixe est refusé, même
 * avec `--force`. C'est la même doctrine que `PilotSeedGuard` (MAT-012 #5870),
 * avec une allowlist distincte — élargir celle des seeds pilotes pour y loger
 * des données de démonstration publique mélangerait deux usages.
 *
 * ## Pourquoi la commande refuse la production
 *
 * Un générateur de données fictives qui tourne en production crée des
 * commerces qui n'existent pas et qui apparaissent dans la découverte réelle.
 * Le refus est donc par défaut, et `--force` est explicite.
 */
final class PublicSyntheticDataCommand extends Command
{
    protected $signature = 'leopardo:public:synthetic-data
        {--clean : supprime les tenants synthétiques au lieu de les créer}
        {--city= : limite l\'exécution à une ville (clé de configuration)}
        {--force : autorise l\'exécution hors environnement local/développement/test}';

    protected $description = 'Crée ou nettoie les données synthétiques de la surface publique (3 villes fictives).';

    public function __construct(
        private readonly TenantManager $tenants,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        /** @var array<string, array<string, mixed>> $cities */
        $cities = (array) config('public_synthetic.cities', []);

        if ($cities === []) {
            $this->error('Aucune ville dans config/public_synthetic.php — rien à générer.');

            return self::FAILURE;
        }

        $only = $this->option('city');

        if (is_string($only) && $only !== '') {
            if (! array_key_exists($only, $cities)) {
                $this->error(sprintf(
                    'Ville inconnue « %s ». Villes disponibles : %s.',
                    $only,
                    implode(', ', array_keys($cities)),
                ));

                return self::FAILURE;
            }

            $cities = [$only => $cities[$only]];
        }

        if ((bool) $this->option('clean')) {
            return $this->clean($cities);
        }

        return $this->seed($cities);
    }

    /**
     * Refuse la production sans `--force` explicite.
     */
    private function guardEnvironment(): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (app()->environment('production')) {
            $this->error(
                'Les données synthétiques sont refusées en production sans --force '
                .'(elles créeraient des commerces inexistants dans la découverte réelle).'
            );

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $cities
     */
    private function seed(array $cities): int
    {
        $created = 0;
        $establishments = 0;

        foreach ($cities as $key => $city) {
            $company = $this->upsertCityTenant($key, $city);

            $this->tenants->withinTenant($company, function () use ($company, $city, $key, &$establishments): void {
                $establishments += $this->seedEstablishments($company, $city, $key);
            });

            $created++;
        }

        $this->writeCategoryThreshold();

        $this->info(sprintf(
            'Données synthétiques prêtes : %d ville(s), %d établissement(s).',
            $created,
            $establishments,
        ));

        $this->line('Aucun compte utilisateur, aucune commande, aucune réservation créés (voir le docblock de la commande).');

        return self::SUCCESS;
    }

    /**
     * Crée ou met à jour le tenant d'une ville.
     *
     * Le slug est déterministe : c'est lui qui porte l'idempotence. Un slug
     * aléatoire créerait un nouveau tenant à chaque exécution.
     *
     * @param  array<string, mixed>  $city
     */
    private function upsertCityTenant(string $key, array $city): Company
    {
        $prefix = (string) config('public_synthetic.slug_prefix', 'pub-synth-');
        $slug = $prefix.Str::slug($key, '-');

        /** @var Company $company */
        $company = Company::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => (string) $city['name'],
                'sector' => 'Restauration',
                'country' => (string) $city['country'],
                'city' => (string) $city['name'],
                'address' => null,
                'email' => $slug.'@example.invalid',
                'phone' => null,
                'plan_id' => $this->defaultPlanId(),
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => 'active',
                'subscription_start' => now()->startOfMonth(),
                'subscription_end' => now()->addYear(),
                'language' => (string) $city['locale'],
                'timezone' => (string) $city['timezone'],
                'currency' => (string) $city['currency'],
                // La verticale doit être active, sinon le provider de découverte
                // filtre le tenant (`coalesce((c.features->>'restaurantmanager')
                // ::boolean, false) is true`) et les données générées seraient
                // invisibles — un générateur qui produit de l'invisible ne sert
                // à rien.
                'features' => ['restaurantmanager' => true],
            ],
        );

        return $company;
    }

    /**
     * Crée les établissements d'une ville dans le contexte tenant.
     *
     * @param  array<string, mixed>  $city
     */
    private function seedEstablishments(Company $company, array $city, string $cityKey): int
    {
        if (! Schema::hasTable('restaurant_branches')) {
            $this->warn('Table restaurant_branches absente — établissements ignorés.');

            return 0;
        }

        $count = 0;
        $index = 0;

        /** @var list<array<string, mixed>> $companies */
        $companies = (array) ($city['companies'] ?? []);

        foreach ($companies as $companySpec) {
            if (! is_array($companySpec)) {
                continue;
            }

            /** @var list<array<string, mixed>> $establishments */
            $establishments = (array) ($companySpec['establishments'] ?? []);

            foreach ($establishments as $spec) {
                if (! is_array($spec)) {
                    continue;
                }

                $index++;
                $code = sprintf('SYN-%02d', $index);
                $published = (bool) ($spec['published'] ?? false);

                // Le slug public est GLOBALEMENT unique (contrainte de base) :
                // il doit donc porter le tenant, pas seulement le commerce.
                // On part de la CLÉ de ville, pas de `$company->slug` — ce
                // dernier porte déjà le préfixe, et le préfixer à nouveau
                // produirait `pub-synth-pub-synth-valmora-syn-01`.
                $publicSlug = $published
                    ? (string) config('public_synthetic.slug_prefix', 'pub-synth-')
                        .Str::slug($cityKey.'-'.$code, '-')
                    : null;

                $exists = DB::table('restaurant_branches')
                    ->where('company_id', $company->id)
                    ->where('code', $code)
                    ->exists();

                $attributes = [
                    'name' => (string) $spec['name'],
                    'address' => null,
                    'city' => (string) $city['name'],
                    'phone' => null,
                    'timezone' => (string) $city['timezone'],
                    'currency' => (string) $city['currency'],
                    'status' => 'active',
                    'is_public' => $published,
                    'public_slug' => $publicSlug,
                    'establishment_type' => (string) $spec['type'],
                    'cuisine_types' => null,
                    'public_description' => $published
                        ? sprintf('%s — donnée synthétique, aucun établissement réel.', (string) $spec['name'])
                        : null,
                    'cover_image_url' => null,
                    // Coordonnées décalées par établissement pour que la
                    // recherche par proximité ait des distances distinctes
                    // et non trois points confondus.
                    'latitude' => self::num($city['latitude'] ?? null) + ($index * 0.0015),
                    'longitude' => self::num($city['longitude'] ?? null) + ($index * 0.0015),
                    'updated_at' => now(),
                ];

                // `created_at` n'est posé qu'à la CRÉATION : `updateOrInsert`
                // l'écraserait à chaque rejeu, ce qui ferait mentir la date de
                // création d'un établissement inchangé.
                if (! $exists) {
                    $attributes['created_at'] = now();
                }

                DB::table('restaurant_branches')->updateOrInsert(
                    ['company_id' => $company->id, 'code' => $code],
                    $attributes,
                );

                $count++;
            }
        }

        return $count;
    }

    /**
     * Écrit le seuil de catégories dans `public.platform_settings`.
     *
     * Le seuil est une DONNÉE de plateforme, pas une constante : la commande
     * l'écrit pour que `PlatformSettingsRepository` le lise exactement comme en
     * production. Si la table n'existe pas (base non migrée), on le signale
     * sans échouer — le dépôt retombe alors sur son défaut documenté.
     */
    private function writeCategoryThreshold(): void
    {
        if (! Schema::hasTable('public.platform_settings')) {
            $this->warn('Table public.platform_settings absente — seuil de catégories non écrit (défaut du dépôt appliqué).');

            return;
        }

        $value = (array) config('public_synthetic.category_threshold', []);

        // `json_encode` est typé `string|false` même avec JSON_THROW_ON_ERROR :
        // PHPStan level max refuse l'union. On encode puis on vérifie, plutôt
        // que de caster en aveugle — un `false` deviendrait `""` en base.
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (! is_string($encoded)) {
            throw new RuntimeException('Le seuil de catégories n\'a pas pu être encodé en JSON.');
        }

        DB::table('public.platform_settings')->updateOrInsert(
            ['key' => PlatformSettingsRepository::DISCOVERY_CATEGORY_MIN_PAGES],
            [
                'value' => $encoded,
                'description' => 'Seuil de catégories de la découverte publique (données synthétiques).',
                'updated_by' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $cities
     */
    private function clean(array $cities): int
    {
        $prefix = (string) config('public_synthetic.slug_prefix', 'pub-synth-');
        $removed = 0;

        foreach (array_keys($cities) as $key) {
            $slug = $prefix.Str::slug($key, '-');

            // Garde : on ne supprime QUE ce que le préfixe désigne. Un slug
            // hors préfixe est refusé même avec --force — c'est la différence
            // entre un nettoyage et une suppression de données client.
            if (! str_starts_with($slug, $prefix)) {
                throw new RuntimeException(sprintf(
                    'Refus de supprimer « %s » : hors du préfixe synthétique « %s ».',
                    $slug,
                    $prefix,
                ));
            }

            /** @var Company|null $company */
            $company = Company::query()->where('slug', $slug)->first();

            if ($company === null) {
                continue;
            }

            $this->tenants->withinTenant($company, function () use ($company): void {
                if (Schema::hasTable('restaurant_branches')) {
                    DB::table('restaurant_branches')->where('company_id', $company->id)->delete();
                }
            });

            $company->delete();
            $removed++;
        }

        $this->info(sprintf('%d tenant(s) synthétique(s) supprimé(s).', $removed));

        return self::SUCCESS;
    }

    /**
     * Plan par défaut : `companies.plan_id` est NOT NULL et référence `plans`.
     * On prend le premier plan disponible plutôt qu'un identifiant en dur, qui
     * casserait sur une base dont les plans ont d'autres identifiants.
     */
    private function defaultPlanId(): int
    {
        // ⚠️ `Schema::hasTable('plans')` renvoie FAUX sous le `search_path`
        // `shared_tenants, public` des tests, alors que `public.plans` existe
        // (vérifié en exécutant : hasTable('plans')=false,
        // hasTable('public.plans')=true). La qualification explicite est donc
        // obligatoire — sans elle, la commande échoue en test alors qu'elle
        // fonctionne en production, où le search_path diffère.
        $id = Schema::hasTable('public.plans')
            ? DB::table('public.plans')->orderBy('id')->value('id')
            : null;

        if (! is_numeric($id)) {
            throw new RuntimeException(
                'Aucun plan en base : exécuter les seeders de base avant les données synthétiques.'
            );
        }

        return (int) $id;
    }

    /**
     * Valeur numérique d'une entrée de configuration, ou 0.
     *
     * La configuration est un `array<string, mixed>` : caster directement en
     * `float` serait refusé par PHPStan level max, et un `(float) 'abc'`
     * vaudrait silencieusement 0. On vérifie donc le type avant de convertir.
     */
    private static function num(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
