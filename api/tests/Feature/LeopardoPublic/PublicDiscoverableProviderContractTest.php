<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Leopardo Public — garde de contrat des providers de découverte (#8794).
 *
 * Deux propriétés étaient VRAIES dans le code et FAUSSES dans les faits, et
 * aucune suite ne les couvrait :
 *
 *  1. `url` du DTO public. `RestaurantDiscoverableProvider` émettait
 *     `/public/restaurants/{slug}` — le préfixe de l'API, pas une route du
 *     navigateur. Chaque résultat Restaurant de `/decouvrir` pointait donc vers
 *     une page inexistante. Les quatre autres providers étaient corrects, ce qui
 *     rendait le défaut invisible à la lecture : il fallait comparer la valeur
 *     au répertoire de routes réel de `front/web`.
 *
 *  2. `supportedActions()`. Restaurant déclarait `booking` alors qu'AUCUNE route
 *     publique de réservation n'existe (les contrôleurs de réservation ne sont
 *     montés que dans le groupe `auth:sanctum`). La passerelle exposait donc
 *     `meta.actions.restaurant = [booking, …]` et le frontend affichait un
 *     bouton inopérant.
 *
 * ⚠️ Ce test est VOLONTAIREMENT indépendant des données. Une première version
 * assertait sur `search([])` : sur une base fraîche `search()` retourne `[]`,
 * la boucle ne s'exécute pas, et le test reste VERT avec le bug réintroduit
 * (vérifié en le remettant en place). Un garde qui ne peut pas échouer ne garde
 * rien. La convention `type ⇒ segment de route` est donc déclarée ici, et c'est
 * l'EXISTENCE du répertoire `front/web/src/app/{segment}/[slug]/page.tsx` qui
 * est vérifiée — un fait statique du dépôt, toujours observable.
 *
 * La contrepartie : ajouter une verticale découvrable exige d'ajouter une ligne
 * dans `TYPE_SEGMENTS`. C'est le prix d'un garde réellement sensible, et le
 * message d'erreur le dit explicitement.
 */
class PublicDiscoverableProviderContractTest extends TestCase
{
    /**
     * Type canonique du provider ⇒ segment de route du navigateur.
     *
     * Source de vérité du contrat public : chaque provider met ce segment dans
     * `url`, et `front/web/src/app/{segment}/[slug]/page.tsx` doit exister.
     *
     * @var array<string, string>
     */
    private const TYPE_SEGMENTS = [
        'restaurant' => 'restaurants',
        'hospitality_property' => 'stay',
        'retail_shop' => 'boutiques',
        'showcase' => 'vitrine',
        'vtc_operator' => 'operateurs',
        // P2-01 (#8802) — première verticale dotée d'un opt-in public a
        // posteriori. La page `front/web/src/app/entreprises/[slug]/page.tsx`
        // doit exister ET ne pas être sous un préfixe dashboard.
        'trade_company' => 'entreprises',
    ];

    /**
     * Actions qu'une route publique sait réellement servir.
     *
     * `contact` n'a qu'une route (la passerelle commune, `target_type` dans le
     * corps) ; `order` et `booking` ont chacun leur famille. S'il devient faux,
     * ce test casse — c'est le but.
     *
     * @var array<string, list<string>>
     */
    private const ACTION_URI_FRAGMENTS = [
        'contact' => ['api/v1/public/conversations'],
        'order' => ['/orders'],
        'booking' => ['/bookings', '/reservations'],
    ];

    /**
     * Providers résolus par le MÊME mécanisme que l'agrégateur
     * (`AppServiceProvider` → `tagged('public.discoverable')`).
     *
     * @return array<string, PublicDiscoverableProvider>
     */
    private function providers(): array
    {
        $providers = [];

        foreach (app()->tagged('public.discoverable') as $provider) {
            $this->assertInstanceOf(PublicDiscoverableProvider::class, $provider);
            $providers[$provider->type()] = $provider;
        }

        return $providers;
    }

    private function appDir(): string
    {
        return dirname(base_path()).'/front/web/src/app';
    }

    /**
     * Préfixes DASHBOARD déclarés dans `front/web/src/proxy.ts`.
     *
     * La source de vérité est le fichier lui-même : recopier la liste ici
     * ferait diverger la garde du produit au premier ajout de préfixe. On lit
     * donc le tableau `DASHBOARD_PREFIXES` jusqu'à sa fermeture `];` et on en
     * extrait les littéraux `'/segment'`.
     *
     * Lecture volontairement tolérante : si le format du fichier change, la
     * garde renvoie un tableau vide plutôt que de casser sur un parse fragile —
     * elle reste alors aussi permissive qu'avant #8802, jamais plus stricte
     * par accident.
     *
     * @return list<string>
     */
    private function dashboardPrefixes(): array
    {
        $path = dirname(base_path()).'/front/web/src/proxy.ts';

        if (! is_file($path)) {
            return [];
        }

        $source = (string) file_get_contents($path);

        $start = strpos($source, 'DASHBOARD_PREFIXES');

        if ($start === false) {
            return [];
        }

        $end = strpos($source, '];', $start);

        if ($end === false) {
            return [];
        }

        $block = substr($source, $start, $end - $start);

        // Seuls les littéraux de tête de segment nous intéressent, et on ignore
        // les commentaires (qui citent des chemins comme `/trade` ou `/commerce`
        // sans les déclarer).
        $lines = preg_split('/\R/', $block) ?: [];
        $prefixes = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }

            if (preg_match("/'\\/([a-z0-9-]+)'/", $line, $m) === 1) {
                $prefixes[] = $m[1];
            }
        }

        return array_values(array_unique($prefixes));
    }

    private function publicRouteServes(string $action): bool
    {
        $fragments = self::ACTION_URI_FRAGMENTS[$action] ?? null;

        if ($fragments === null) {
            return false;
        }

        /** @var iterable<\Illuminate\Routing\Route> $routes */
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/public/')) {
                continue;
            }

            foreach ($fragments as $fragment) {
                // `contact` est une correspondance EXACTE (une seule route) ;
                // `order`/`booking` sont des familles, donc des fragments.
                $matched = $fragment === 'api/v1/public/conversations'
                    ? $uri === $fragment
                    : str_contains($uri, $fragment);

                if ($matched) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Garde n°1 — chaque type découvrable a un segment DÉCLARÉ, et ce segment
     * existe réellement dans `front/web`.
     */
    public function test_every_discoverable_type_has_a_declared_browser_segment(): void
    {
        $appDir = $this->appDir();

        $this->assertDirectoryExists(
            $appDir,
            "Répertoire front/web introuvable depuis l'API ({$appDir}). Ce garde ne peut pas prouver que les URL publiques existent : "
            .'il doit être déplacé côté frontend plutôt que supprimé.'
        );

        $providers = $this->providers();

        $this->assertNotEmpty($providers, 'Aucun provider public découvert : la garde ne prouverait rien.');

        foreach (array_keys($providers) as $type) {
            $this->assertArrayHasKey(
                $type,
                self::TYPE_SEGMENTS,
                sprintf(
                    "Le provider `%s` est découvrable mais aucun segment de route navigateur n'est déclaré dans TYPE_SEGMENTS. "
                    ."Ajouter la ligne `'%s' => '<segment>'` après avoir créé `front/web/src/app/<segment>/[slug]/page.tsx` (#8794).",
                    $type,
                    $type
                )
            );

            $segment = self::TYPE_SEGMENTS[$type];

            $this->assertFileExists(
                "{$appDir}/{$segment}/[slug]/page.tsx",
                sprintf(
                    'Le type `%s` pointe vers `/%s/{slug}`, mais `front/web/src/app/%s/[slug]/page.tsx` n\'existe pas : '
                    .'tout lien de résultat pour cette verticale est MORT pour un visiteur public (#8794).',
                    $type,
                    $segment,
                    $segment
                )
            );

            // ⚠️ Exister ne suffit PAS. Une page sous un préfixe DASHBOARD est
            // interceptée par `src/proxy.ts`, qui redirige (307) tout visiteur
            // sans cookie de session vers `/auth/login` : la fiche existe, se
            // compile, passe ce test… et reste un lien mort.
            //
            // Ce cas s'est produit pour de vrai (#8802) : la première fiche
            // Trade a été créée sous `/commerce`, préfixe déjà pris par l'espace
            // vendeur BC-17. Seule une requête HTTP réelle l'a révélé. La garde
            // est donc étendue ici plutôt que laissée à la vigilance du
            // relecteur.
            $this->assertNotContains(
                $segment,
                $this->dashboardPrefixes(),
                sprintf(
                    'Le segment `%s` (type `%s`) est déclaré comme préfixe DASHBOARD dans `front/web/src/proxy.ts` : '
                    .'la fiche sera redirigée vers `/auth/login` (307) pour tout visiteur public, donc inatteignable. '
                    .'Choisir un segment public distinct (ex. `/entreprises` plutôt que `/commerce`).',
                    $segment,
                    $type
                )
            );
        }

        // Le mapping ne doit pas déclarer de fantômes : un segment sans provider
        // signale une verticale retirée sans nettoyage du contrat.
        foreach (array_keys(self::TYPE_SEGMENTS) as $type) {
            $this->assertArrayHasKey(
                $type,
                $providers,
                "TYPE_SEGMENTS déclare `{$type}` mais aucun provider ne l'expose : mapping obsolète."
            );
        }
    }

    /**
     * Garde n°2 — aucune URL d'API `/public/` n'est codée en dur dans un
     * provider. C'est la cause exacte du défaut Restaurant, indépendamment des
     * données présentes en base.
     */
    public function test_no_provider_hardcodes_an_api_public_prefix_in_its_url(): void
    {
        foreach ($this->providers() as $type => $provider) {
            $file = (new \ReflectionClass($provider))->getFileName();

            $this->assertIsString($file, "Provider `{$type}` : fichier source introuvable.");

            $source = (string) file_get_contents((string) $file);

            $this->assertStringNotContainsString(
                "'/public/",
                $source,
                sprintf(
                    "Provider `%s` : une URL `/public/...` est codée en dur. `/public/...` est le préfixe de l'API, "
                    .'pas une route du navigateur — utiliser le segment de route déclaré dans TYPE_SEGMENTS (#8794).',
                    $type
                )
            );
        }
    }

    /**
     * Garde n°3 — l'URL réellement produite (quand des données existent) suit
     * le segment déclaré et ne contient jamais `/public/`.
     */
    public function test_produced_urls_follow_the_declared_browser_segment(): void
    {
        foreach ($this->providers() as $type => $provider) {
            $segment = self::TYPE_SEGMENTS[$type];

            foreach ($provider->search([]) as $item) {
                /** @var array<string, mixed> $item */
                $url = $item['url'] ?? null;

                $this->assertStringStartsWith('/', (string) $url, "Provider `{$type}` : `url` doit être relative à l'origine.");
                $this->assertStringNotContainsString(
                    '/public/',
                    (string) $url,
                    sprintf('Provider `%s` : `url` = `%s` contient `/public/` (#8794).', $type, $url)
                );
                $this->assertStringStartsWith(
                    "/{$segment}/",
                    (string) $url,
                    sprintf(
                        "Provider `%s` : `url` = `%s` n'utilise pas le segment navigateur `/%s/{slug}` (#8794).",
                        $type,
                        $url,
                        $segment
                    )
                );
            }
        }
    }

    /**
     * Garde n°4 — une action déclarée doit être adossée à une route publique.
     *
     * Version exécutable du « pas de bouton mort » : `meta.actions` est la seule
     * source de vérité du frontend, donc une déclaration sans route est un
     * bouton qui ne peut rien faire.
     */
    public function test_declared_actions_are_backed_by_a_public_route(): void
    {
        foreach ($this->providers() as $type => $provider) {
            $declared = $provider->supportedActions();

            foreach ($declared as $action) {
                $this->assertTrue(
                    $this->publicRouteServes($action),
                    sprintf(
                        'Provider `%s` déclare `%s` mais AUCUNE route `api/v1/public/**` ne la sert : '
                        .'`meta.actions.%s` exposerait un bouton inopérant (#8794).',
                        $type,
                        $action,
                        $type
                    )
                );
            }
        }
    }

    /**
     * Garde n°5 — non-régression NOMMÉE du défaut Restaurant.
     *
     * Sans cette assertion, la garde n°4 resterait verte si `booking` revenait
     * sans route : le message générique ne dit pas quel contrat public il
     * faudrait livrer (P3-01).
     */
    public function test_restaurant_declares_only_the_actions_it_actually_serves(): void
    {
        $provider = $this->providers()['restaurant'] ?? null;

        $this->assertInstanceOf(
            PublicDiscoverableProvider::class,
            $provider,
            'Le provider `restaurant` doit être tagué `public.discoverable`.'
        );

        $this->assertSame(
            ['contact', 'order'],
            $provider->supportedActions(),
            'Restaurant ne sert que le contact (passerelle commune) et la commande publique (RESTO-902). '
            .'`booking` ne doit revenir qu\'avec un contrat public de réservation (P3-01) ET un test qui le prouve.'
        );
    }
}
