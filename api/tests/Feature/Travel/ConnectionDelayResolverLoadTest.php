<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Services\ConnectionDelayResolver;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * M5 (évaluation leopardo-hr) — CHARGE du `ConnectionDelayResolver`.
 *
 * `TravelConnectionDelayTest` prouve déjà que le cache fonctionne sur 5 villes
 * × 10 appels. Ce fichier répond à une autre question : **le cache tient-il
 * sous la densité réelle d'une recherche de correspondances ?**
 *
 * `ConnectionSearchAction` appelle `resolve()` dans une boucle imbriquée
 * (chaque premier tronçon × chaque second tronçon). Sur un réseau dense — une
 * gare de départ, une gare d'arrivée, et des dizaines de villes
 * intermédiaires — le nombre d'appels atteint facilement plusieurs milliers
 * pour une seule requête HTTP. Sans mémoïsation, chacun relit
 * `travel_stations` : c'est un N+1 dont le coût croît avec le nombre de
 * combinaisons d'horaires, pas avec la donnée.
 *
 * Ce que ces tests verrouillent :
 *
 * 1. **Bornage** — le nombre de requêtes SQL est celui du nombre de villes
 *    DISTINCTES, pas celui des appels. C'est la propriété qui rend le coût
 *    indépendant de la densité.
 * 2. **Correction sous charge** — la valeur servie reste la bonne pour chaque
 *    ville (la gare la plus contraignante), y compris quand les villes sont
 *    entrelacées : un cache qui confondrait deux clés passerait un test
 *    séquentiel et échouerait ici.
 * 3. **Invalidation ciblée** — `forget($cityId)` ne jette que la ville visée ;
 *    les autres restent en cache. Un `forget()` global à chaque écriture
 *    ramènerait le N+1 sans qu'aucun test fonctionnel ne s'en aperçoive.
 * 4. **Coût mesuré** — le temps de résolution reste négligeable devant une
 *    requête HTTP, ce qui est la raison d'être du cache.
 */
class ConnectionDelayResolverLoadTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Villes distinctes d'un réseau dense réaliste. */
    private const CITIES = 40;

    /** Appels par ville — simule les paires (premier tronçon × second tronçon). */
    private const CALLS_PER_CITY = 100;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();

        $this->company = $company;
    }

    /**
     * Réseau dense : `self::CITIES` villes, chacune avec 3 gares dont une
     * seule porte une surcharge — la configuration réelle d'un réseau
     * multi-gares.
     *
     * @return array<int, array{city: int, expected: int}>
     */
    private function denseNetwork(): array
    {
        return app(TenantManager::class)->withinTenant($this->company, function (): array {
            $rows = [];

            for ($i = 0; $i < self::CITIES; $i++) {
                /** @var TravelCity $city */
                $city = TravelCity::factory()->create();

                // Deux gares sans surcharge : elles ne doivent pas tirer le
                // délai vers le bas (ni provoquer de requête supplémentaire).
                TravelStation::factory()->count(2)->create([
                    'city_id' => $city->id,
                    'min_connection_minutes' => null,
                ]);

                // Une gare contraignante, valeur distincte par ville : si le
                // cache confondait deux villes, la valeur servie serait fausse.
                $expected = 20 + ($i % 200);

                TravelStation::factory()->create([
                    'city_id' => $city->id,
                    'min_connection_minutes' => $expected,
                ]);

                $rows[] = ['city' => (int) $city->id, 'expected' => $expected];
            }

            return $rows;
        });
    }

    /**
     * Le nombre de requêtes SQL doit être celui des villes distinctes, pas
     * celui des appels — c'est la propriété qui rend le coût indépendant de
     * la densité de la recherche.
     */
    public function test_query_count_is_bounded_by_distinct_cities_not_by_call_count(): void
    {
        $network = $this->denseNetwork();

        $resolver = app(ConnectionDelayResolver::class);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $calls = 0;

        // Boucle imbriquée : chaque ville est visitée CALLS_PER_CITY fois,
        // dans un ordre entrelacé (comme la boucle réelle premier × second).
        for ($round = 0; $round < self::CALLS_PER_CITY; $round++) {
            foreach ($network as $row) {
                $resolver->resolve($row['city']);
                $calls++;
            }
        }

        $this->assertSame(
            self::CITIES * self::CALLS_PER_CITY,
            $calls,
            'Le test doit bien exercer la charge annoncée.',
        );

        $this->assertSame(
            self::CITIES,
            $queries,
            sprintf(
                'Attendu %d requêtes (une par ville) pour %d appels, obtenu %d : '
                .'le cache ne borne pas le coût, la recherche dense est un N+1.',
                self::CITIES,
                $calls,
                $queries,
            ),
        );
    }

    /**
     * Sous charge entrelacée, chaque ville doit recevoir SA valeur.
     *
     * Un cache indexé par erreur (clé partagée, clé tronquée) passerait un
     * test séquentiel — il échoue ici.
     */
    public function test_every_city_keeps_its_own_delay_under_interleaved_load(): void
    {
        $network = $this->denseNetwork();

        $resolver = app(ConnectionDelayResolver::class);

        for ($round = 0; $round < 5; $round++) {
            foreach ($network as $row) {
                $this->assertSame(
                    $row['expected'],
                    $resolver->resolve($row['city']),
                    sprintf('Ville %d : délai erroné au tour %d.', $row['city'], $round),
                );
            }
        }
    }

    /**
     * `forget($cityId)` ne doit jeter QUE la ville visée.
     *
     * Une invalidation globale à chaque écriture de gare ramènerait le N+1
     * complet sans qu'aucun test fonctionnel ne le remarque : ce test le
     * verrouille en comptant les requêtes après l'invalidation.
     */
    public function test_forgetting_one_city_keeps_the_others_cached(): void
    {
        $network = $this->denseNetwork();

        $resolver = app(ConnectionDelayResolver::class);

        // 1. Tout charger.
        foreach ($network as $row) {
            $resolver->resolve($row['city']);
        }

        // 2. Invalider une seule ville.
        $resolver->forget($network[0]['city']);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        // 3. Relire tout le réseau.
        foreach ($network as $row) {
            $resolver->resolve($row['city']);
        }

        $this->assertSame(
            1,
            $queries,
            sprintf(
                'Après invalidation d\'une ville, une seule relecture est attendue ; %d obtenues : '
                .'l\'invalidation est globale, le cache est perdu à chaque écriture.',
                $queries,
            ),
        );
    }

    /**
     * Coût mesuré : 4 000 résolutions doivent rester très en deçà du coût
     * d'une requête HTTP.
     *
     * Le seuil est volontairement large (2 s) : il ne mesure pas la
     * performance d'une machine mais détecte une régression grossière — un
     * cache retiré ferait 4 000 requêtes SQL et dépasserait largement ce
     * budget.
     */
    public function test_resolution_cost_stays_negligible_under_load(): void
    {
        $network = $this->denseNetwork();

        $resolver = app(ConnectionDelayResolver::class);

        // Préchauffage : on ne mesure que le régime établi.
        foreach ($network as $row) {
            $resolver->resolve($row['city']);
        }

        $startedAt = microtime(true);

        for ($round = 0; $round < self::CALLS_PER_CITY; $round++) {
            foreach ($network as $row) {
                $resolver->resolve($row['city']);
            }
        }

        $elapsed = microtime(true) - $startedAt;

        $this->assertLessThan(
            2.0,
            $elapsed,
            sprintf(
                '%d résolutions ont pris %.3f s : le cache ne joue plus.',
                self::CITIES * self::CALLS_PER_CITY,
                $elapsed,
            ),
        );
    }
}
