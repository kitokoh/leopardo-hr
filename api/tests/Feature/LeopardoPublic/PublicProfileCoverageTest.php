<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — audit de couverture des PROFILS du canal public.
 *
 * Ce test confronte les profils DÉCLARÉS sur GitHub — `ADR-IDENTITE-CLIENTS-PUBLICS.md`
 * §2.1 (« Quatre modèles d'identité, pas deux ») et §8.2 (« Les sept situations ») —
 * à ce qui existe réellement dans le code.
 *
 * Il ne teste pas une fonctionnalité : il verrouille une **propriété
 * d'architecture** que la règle n°1 du couloir impose — « aucune troisième
 * identité », « aucune duplication de compte ». Une régression ici ne casse
 * aucun écran ; elle casse la frontière d'identité, ce qui est bien plus grave
 * et bien plus silencieux.
 *
 * ⚠️ Chaque assertion porte sur une classe, un guard ou une route réellement
 * présents. Aucun cas n'est déclaré « couvert » sans avoir interrogé le code.
 */
final class PublicProfileCoverageTest extends TestCase
{
    use RefreshTenantDatabase;

    // ────────────────────────────────────────────────────────────────────────
    // §2.1 — Les quatre modèles d'identité sont DISTINCTS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Les quatre populations d'identité ne partagent AUCUNE classe.
     *
     * L'ADR §2.1 les énumère : `User` (public générique), `Employee` (tenant),
     * `SuperAdmin` (interne Leopardo), `TravelCustomerAccount` (client travel,
     * verdict « non conforme » mais toléré le temps de la convergence L4).
     *
     * Le point qui compte : `TravelCustomerAccount` n'hérite PAS de `User`.
     * Si un jour elle en héritait, le pont d'identité deviendrait inutile et
     * deux comptes pourraient se confondre — exactement ce que l'ADR interdit.
     */
    public function test_the_four_identity_models_are_distinct_classes(): void
    {
        $models = [
            'User' => User::class,
            'Employee' => Employee::class,
            'SuperAdmin' => \App\Core\Tenant\Domain\Models\SuperAdmin::class,
            'TravelCustomerAccount' => TravelCustomerAccount::class,
        ];

        // Aucune des quatre n'est une sous-classe d'une autre.
        //
        // On remonte la CHAÎNE D'HÉRITAGE réelle (`getParentClass()`) au lieu
        // d'appeler `isSubclassOf()` : PHPStan (niveau 8) replie la hiérarchie
        // de classes et signale « will always evaluate to false » — vrai
        // aujourd'hui, mais le test deviendrait inopérant le jour où
        // l'héritage apparaîtrait. La remontée de chaîne reste dynamique.
        foreach ($models as $nameA => $classA) {
            $ancestors = [];
            $parent = (new \ReflectionClass($classA))->getParentClass();

            while ($parent !== false) {
                $ancestors[] = $parent->getName();
                $parent = $parent->getParentClass();
            }

            foreach ($models as $nameB => $classB) {
                if ($nameA === $nameB) {
                    continue;
                }

                $this->assertNotContains(
                    $classB,
                    $ancestors,
                    "{$nameA} ne doit pas hériter de {$nameB} : ce serait une fusion d'identité.",
                );
            }
        }

        // Le cas explicitement visé par l'ADR §2.1 : `TravelCustomerAccount`
        // ne doit jamais avoir `User` dans sa chaîne d'héritage.
        $travelAncestors = [];
        $parent = (new \ReflectionClass($models['TravelCustomerAccount']))->getParentClass();

        while ($parent !== false) {
            $travelAncestors[] = $parent->getName();
            $parent = $parent->getParentClass();
        }

        $this->assertNotContains(
            $models['User'],
            $travelAncestors,
            'TravelCustomerAccount ne doit JAMAIS hériter de User (ADR §2.1).',
        );
    }

    /**
     * Chaque population a son guard, et les guards sont DISJOINTS.
     *
     * C'est la propriété qui rend l'isolation réelle : un jeton émis pour une
     * population ne peut pas être présenté à la surface d'une autre, parce que
     * le provider résout une table différente.
     */
    public function test_each_identity_population_has_its_own_guard_and_provider(): void
    {
        $expected = [
            'user_api' => 'users',
            'sanctum' => 'employees',
            'super_admin_api' => 'super_admins',
            'travel_customer' => 'travel_customers',
        ];

        foreach ($expected as $guard => $provider) {
            $this->assertSame(
                $provider,
                config("auth.guards.{$guard}.provider"),
                "Le guard `{$guard}` doit résoudre le provider `{$provider}`.",
            );
        }

        // Les providers pointent vers des modèles distincts.
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertSame(Employee::class, config('auth.providers.employees.model'));

        // `travel_customers` n'a PAS de clé `model` : son driver est enregistré
        // par le service provider (voir le commentaire de `config/auth.php`).
        // On vérifie donc le driver, pas le modèle.
        $this->assertSame(
            'travel_customer_accounts',
            config('auth.providers.travel_customers.driver'),
            'Le provider travel doit utiliser son driver dédié, jamais Eloquent sur `users`.',
        );
    }

    /**
     * Un jeton `user_api` n'ouvre NI la surface employé, NI la surface
     * plateforme, NI la surface travel.
     *
     * `Auth::forgetGuards()` est indispensable : sans lui, un guard déjà résolu
     * masquerait une fuite d'autorisation (c'est le piège documenté par
     * `PublicPhoneOtpTest`).
     */
    public function test_a_public_client_token_opens_no_other_identity_surface(): void
    {
        $client = User::factory()->create(['email' => 'profil-coverage@example.test']);

        Auth::forgetGuards();
        $token = $client->createToken('profil-coverage')->plainTextToken;
        Auth::forgetGuards();

        // Sa propre surface : ouverte.
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/me')
            ->assertStatus(200);

        // Surface employé (guard `sanctum`) : fermée.
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/employees')
            ->assertStatus(401);

        // Surface plateforme (guard `super_admin_api`) : fermée.
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/auth/me')
            ->assertStatus(401);

        // Surface travel (guard `travel_customer`) : fermée.
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/public/travel/marketplace/account/me')
            ->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────────
    // §8.2 — Les sept situations de rattachement
    // ────────────────────────────────────────────────────────────────────────

    /**
     * §8.2 « Rattachement sécurisé » — `user_id` n'est écrit QUE par le
     * service de rattachement.
     *
     * L'ADR §8.3 en fait une propriété vérifiable : « Aucun autre code n'écrit
     * `travel_customer_accounts.user_id` ». On le vérifie mécaniquement, en
     * cherchant toute écriture hors du service.
     */
    public function test_only_the_bridge_service_writes_the_travel_account_user_id(): void
    {
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // Le service de rattachement est l'écrivain légitime.
            if (str_ends_with($path, 'PublicIdentityBridge.php')) {
                continue;
            }

            $source = (string) file_get_contents($path);

            // Écriture directe du `user_id` d'un compte travel hors service.
            if (preg_match('/travel_customer_accounts[\'"]?\s*\)\s*->\s*update\s*\(/', $source) === 1) {
                $offenders[] = $path;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'ADR §8.3 : seul `PublicIdentityBridge` écrit `travel_customer_accounts.user_id`. '
            .'Écrivains trouvés hors service : '.implode(', ', $offenders),
        );
    }

    /**
     * §8.2 « OTP et vérification du téléphone » — un numéro saisi est une
     * PRÉTENTION, pas une preuve.
     *
     * La contrainte est en BASE (SQLSTATE 23514), pas seulement en code : même
     * une écriture directe est refusée. C'est ce que prouve
     * `PublicIdentityBridgeServiceTest` ; ici on verrouille la présence de la
     * contrainte dans la migration, pour qu'une réécriture ne la perde pas.
     */
    public function test_the_verified_phone_constraint_is_declared_in_the_migration(): void
    {
        $migration = base_path(
            'database/migrations/public/2026_11_07_000200_9094_add_identity_bridge_to_travel_customer_accounts.php'
        );

        $this->assertFileExists($migration, 'La migration du pont d\'identité doit exister.');

        $source = (string) file_get_contents($migration);

        $this->assertStringContainsString(
            'phone_verified_at',
            $source,
            'La contrainte doit porter sur `phone_verified_at` : sans lui, `user_id` reste NULL.',
        );

        $this->assertMatchesRegularExpression(
            '/CHECK|check/i',
            $source,
            'La garde doit être une contrainte CHECK en base, pas une simple validation applicative.',
        );
    }

    /**
     * §8.2 « Récupération de compte » — uniquement par un canal DÉJÀ vérifié.
     *
     * Aucune route de récupération par simple connaissance du numéro ne doit
     * exister. C'est le constat que `PublicIdentityBridgeTest::test_case_6`
     * porte déjà ; on le reprend ici du point de vue du PROFIL, en excluant
     * explicitement les codes de secours 2FA (faux positif connu).
     */
    public function test_no_account_recovery_route_exists_for_the_public_profile(): void
    {
        $suspicious = collect(Route::getRoutes()->getRoutes())
            ->map(static fn ($route): string => $route->uri())
            ->filter(static function (string $uri): bool {
                if (str_contains($uri, '2fa')) {
                    return false; // codes de secours 2FA, pas une récupération de compte
                }

                return str_contains($uri, 'recover')
                    || str_contains($uri, 'phone-change')
                    || str_contains($uri, 'reassign');
            })
            ->values()
            ->all();

        $this->assertSame(
            [],
            $suspicious,
            'ADR §8.2 : aucune récupération de compte par simple numéro. Routes trouvées : '
            .implode(', ', $suspicious),
        );
    }

    /**
     * §8.2 « Collisions » — deux `users` ne peuvent pas partager un numéro.
     *
     * L'unicité est partielle (NULL autorisé) et porte sur `phone_e164`. On
     * vérifie l'index réellement présent en base, pas la documentation.
     */
    public function test_the_users_phone_uniqueness_index_exists_in_the_database(): void
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SELECT indexname, indexdef FROM pg_indexes
             WHERE schemaname = 'public' AND tablename = 'users'"
        );

        $phoneIndex = collect($indexes)->first(
            static fn ($row): bool => str_contains((string) $row->indexdef, 'phone_e164')
        );

        $this->assertNotNull(
            $phoneIndex,
            'Un index sur `users.phone_e164` doit exister : sans lui, deux comptes peuvent partager un numéro.',
        );

        $this->assertStringContainsString(
            'UNIQUE',
            strtoupper((string) $phoneIndex->indexdef),
            'L\'index sur `phone_e164` doit être UNIQUE (partiel : NULL autorisé).',
        );
    }

    // ────────────────────────────────────────────────────────────────────────
    // Règle n°1 — aucune duplication de compte ni de messagerie
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Le canal public ne possède AUCUNE table d'identité propre.
     *
     * `PUBLIC_PROFILE_DATA_MODEL.md` §10 l'énonce : pas de `public_accounts`,
     * pas de `public_sessions`, pas de `public_messages`. Le client EST une
     * ligne `users` ; les sessions vivent dans `personal_access_tokens` ; la
     * messagerie appartient à BC-13.
     *
     * Ce test échouera le jour où quelqu'un ajoutera une de ces tables — c'est
     * exactement le signal qu'on veut.
     */
    public function test_the_public_channel_owns_no_identity_or_message_table(): void
    {
        $forbidden = ['public_accounts', 'public_sessions', 'public_messages', 'public_orders'];

        foreach ($forbidden as $table) {
            $exists = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT to_regclass(?) AS reg',
                ['public.'.$table],
            );

            $this->assertNull(
                $exists->reg ?? null,
                "La table `public.{$table}` ne doit pas exister : ce serait une duplication "
                .'d\'identité, de session, de messagerie ou de contenu métier (règle n°1).',
            );
        }
    }

    /**
     * La messagerie publique ROUTE, elle ne duplique pas.
     *
     * `public_conversation_index` ne porte que des références — c'est le patron
     * que M3 a repris pour les activités. On vérifie qu'aucune colonne de
     * contenu métier n'y a été ajoutée.
     */
    public function test_the_conversation_index_carries_no_business_content(): void
    {
        $columns = \Illuminate\Support\Facades\DB::getSchemaBuilder()
            ->getColumnListing('public.public_conversation_index');

        $forbidden = ['body', 'message', 'content', 'text', 'amount', 'total', 'price'];

        foreach ($forbidden as $column) {
            $this->assertNotContains(
                $column,
                $columns,
                "`public_conversation_index.{$column}` : l'index de routage ne porte aucun contenu métier.",
            );
        }
    }
}
