<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * COULOIR 16 — M0 · Pont d'identité du client grand public.
 *
 * Les 8 cas du parcours de connexion client, chacun avec une assertion
 * EXÉCUTÉE. Ces tests décrivent l'ÉTAT RÉEL du code au HEAD : ils passent
 * quand l'écart est présent, et devront être inversés le jour où le lot
 * correspondant est livré (c'est le signal de sortie).
 *
 * Groupe `audit` : EXCLU de la CI bloquante (`--exclude-group=audit`). Un
 * constat ne doit pas bloquer la PR qui livre le correctif.
 *
 * ⚠️ Aucun de ces tests ne déclare un cas « réussi » sans avoir interrogé le
 * code : chaque assertion porte sur une route, un guard, une règle de
 * validation ou une table réellement présents.
 */
#[Group('audit')]
class PublicIdentityBridgeTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * CAS 1 — E-mail seul : le parcours nominal existe et est public.
     *
     * Prouve que `POST /user/register` et `POST /user/login` sont montés SANS
     * authentification, donc accessibles à un visiteur qui n'a encore rien.
     */
    public function test_case_1_email_only_registration_and_login_are_public(): void
    {
        $routes = app('router')->getRoutes();

        foreach (['/api/v1/user/register', '/api/v1/user/login'] as $uri) {
            $route = $routes->match(app('request')->create($uri, 'POST'));

            $this->assertNotNull($route, "La route {$uri} doit exister.");
            $this->assertNotContains(
                'auth:user_api',
                $route->gatherMiddleware(),
                "{$uri} doit rester PUBLIQUE : un visiteur n'a pas encore de compte.",
            );
        }
    }

    /**
     * CAS 2 — Téléphone seul : ÉCART PROUVÉ.
     *
     * `LoginCodeController::request()` valide `email` en `required` et ne
     * connaît pas `phone_e164`. Un client sans e-mail ne peut donc pas
     * recevoir de code. L'assertion porte sur la règle de validation réelle,
     * pas sur une intention.
     */
    public function test_case_2_phone_only_login_is_not_supported(): void
    {
        $source = file_get_contents(
            app_path('Core/Auth/Interfaces/Api/V1/Controllers/LoginCodeController.php')
        );

        $this->assertIsString($source);

        // La règle de validation de la demande de code.
        $this->assertMatchesRegularExpression(
            "/'email'\s*=>\s*\['required',\s*'email'/",
            $source,
            'LoginCodeController doit encore exiger un e-mail : le cas « téléphone seul » est un écart.',
        );

        // Et aucune règle `phone_e164` n'est acceptée en entrée.
        $this->assertStringNotContainsString(
            "'phone_e164' => ['required'",
            $source,
            'Si cette assertion casse, le cas 2 vient d\'être livré : inverser ce test.',
        );
    }

    /**
     * CAS 3 — Compte client de l'agence de voyage : ÉCART PROUVÉ.
     *
     * `travel_customer_accounts` est une table d'identité SÉPARÉE, hors du
     * graphe `users` : elle n'a ni `user_id` ni `phone_e164`. Le pont par
     * téléphone vérifié n'existe donc pas encore.
     */
    public function test_case_3_travel_customer_accounts_are_outside_the_users_graph(): void
    {
        // La fixture MVP ne charge pas cette table : on lit la MIGRATION, qui
        // est la source de vérité du schéma réellement déployé.
        $migration = base_path(
            'database/migrations/public/2026_09_19_002000_7739_create_travel_customer_accounts_table.php'
        );

        $this->assertFileExists($migration, 'La migration 7739 doit exister.');

        $source = (string) file_get_contents($migration);

        $this->assertStringContainsString("'travel_customer_accounts'", $source);

        $this->assertStringNotContainsString(
            "'user_id'",
            $source,
            'ÉCART : aucun pont vers users.id. Si cette assertion casse, le pont est livré.',
        );

        $this->assertStringNotContainsString(
            "'phone_e164'",
            $source,
            'ÉCART : le numéro n\'est pas normalisé E.164 sur cette table.',
        );
    }

    /**
     * CAS 4 — Même personne cliente de plusieurs métiers : PARTIEL.
     *
     * L'index de routage `public_conversation_index` porte bien
     * `(user_id, company_id, target_slug)` — un client peut donc avoir un fil
     * par tenant. Mais il n'existe AUCUN index équivalent pour les activités
     * (commandes, réservations) : l'onglet Activités n'a pas de source.
     */
    public function test_case_4_multi_vertical_history_has_a_conversation_index_but_no_activity_index(): void
    {
        // La fixture MVP ne charge pas ces tables : on lit les MIGRATIONS.
        $conversationMigration = base_path(
            'database/migrations/public/2026_10_20_000300_8760_create_public_conversation_index_table.php'
        );

        $this->assertFileExists($conversationMigration, 'L\'index de routage des conversations doit exister (#8760).');

        $source = (string) file_get_contents($conversationMigration);

        foreach (['user_id', 'company_id', 'target_slug'] as $column) {
            $this->assertStringContainsString(
                "'{$column}'",
                $source,
                "public_conversation_index doit porter {$column}.",
            );
        }

        $activityMigrations = glob(base_path('database/migrations/public/*public_activity_index*'));

        $this->assertSame(
            [],
            $activityMigrations ?: [],
            'ÉCART : aucun index d\'activités. Si cette assertion casse, le lot Activités est livré.',
        );
    }

    /**
     * CAS 5 — Client ET employé : DEUX COMPTES SÉPARÉS, prouvé.
     *
     * Les guards sont disjoints : `user_api` (provider `users`) et `sanctum`
     * (provider `employees`). Un jeton client ne peut donc pas ouvrir une
     * route employé — c'est la décision validée par le propriétaire.
     */
    public function test_case_5_client_and_employee_guards_are_disjoint(): void
    {
        $userApi = config('auth.guards.user_api');
        $sanctum = config('auth.guards.sanctum');

        $this->assertSame('users', $userApi['provider'] ?? null, 'user_api doit utiliser le provider users.');
        $this->assertNotSame(
            $userApi['provider'] ?? null,
            $sanctum['provider'] ?? null,
            'Les deux guards ne doivent JAMAIS partager un provider : ce serait une fusion d\'identité.',
        );

        // Une route employé exige sanctum, jamais user_api.
        $route = app('router')->getRoutes()->match(
            app('request')->create('/api/v1/auth/me', 'GET')
        );

        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertNotContains('auth:user_api', $middleware);
    }

    /**
     * CAS 6 — Numéro changé ou réattribué : ÉCART PROUVÉ.
     *
     * Aucune route de récupération de compte par numéro n'existe. Un numéro
     * réattribué par un opérateur ne peut donc pas être repris par un tiers
     * — mais la personne qui a changé de numéro ne peut pas non plus
     * récupérer son compte. Les deux faces du même manque.
     */
    public function test_case_6_no_phone_recovery_route_exists(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())
            ->map(static fn ($route): string => $route->uri())
            // `2fa/recovery-codes` est un faux positif : ce sont des codes de
            // secours 2FA, pas une récupération de compte par téléphone.
            ->filter(static fn (string $uri): bool => (str_contains($uri, 'recover')
                    && ! str_contains($uri, '2fa'))
                || str_contains($uri, 'phone-change')
                || str_contains($uri, 'reassign'));

        $this->assertCount(
            0,
            $uris,
            'ÉCART : aucune route de récupération/changement de numéro. Si ce test casse, le lot est livré.',
        );
    }

    /**
     * CAS 7 — SMS indisponible : ÉCART PROUVÉ (disponibilité, pas sécurité).
     *
     * Le code OTP est généré par `random_int`, haché, et vérifié par
     * `Hash::check` : aucun code arbitraire n'est accepté. Le problème est
     * que le canal SMS peut court-circuiter l'e-mail sans émettre d'appel.
     */
    public function test_case_7_otp_is_cryptographically_sound_but_sms_can_swallow_the_email(): void
    {
        $source = file_get_contents(
            app_path('Core/Auth/Interfaces/Api/V1/Controllers/LoginCodeController.php')
        );

        $this->assertIsString($source);

        // Sécurité : le code est aléatoire et haché, jamais comparé en clair.
        $this->assertStringContainsString('random_int', $source, 'Le code doit être généré aléatoirement.');
        $this->assertStringContainsString('Hash::check', $source, 'La vérification doit passer par Hash::check.');

        // Disponibilité : le canal SMS est consulté AVANT l'envoi de l'e-mail.
        $smsPos = strpos($source, 'sms');
        $mailPos = strpos($source, 'Mail::to');

        $this->assertNotFalse($smsPos, 'Le canal SMS doit être présent dans le contrôleur.');
        $this->assertNotFalse($mailPos, 'L\'envoi e-mail doit être présent dans le contrôleur.');
        $this->assertLessThan(
            $mailPos,
            $smsPos,
            'ÉCART : le canal SMS est évalué avant l\'e-mail — c\'est le point de court-circuit.',
        );
    }

    /**
     * CAS 8 — Appareil perdu : PARTIEL.
     *
     * `POST /user/logout` révoque le jeton COURANT. Aucune route `logout-all`
     * n'existe : un appareil perdu ne peut pas être révoqué à distance sans
     * révoquer aussi l'appareil utilisé pour le faire.
     */
    public function test_case_8_logout_exists_but_logout_all_does_not(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())
            ->map(static fn ($route): string => $route->uri());

        $this->assertTrue(
            $uris->contains('api/v1/user/logout'),
            'POST /user/logout doit exister (révocation du jeton courant).',
        );

        $this->assertFalse(
            $uris->contains('api/v1/user/logout-all'),
            'ÉCART : pas de révocation multi-appareils. Si ce test casse, le lot est livré.',
        );
    }
}
