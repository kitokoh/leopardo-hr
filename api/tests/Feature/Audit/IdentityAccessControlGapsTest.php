<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * AUDIT — preuves des ÉCARTS (et non des acquis).
 *
 * Complément de {@see IdentityAccessControlAuditTest}. Chaque test de cette
 * classe documente un manque constaté dans le rapport. Ils sont écrits pour
 * PASSER quand l'écart EST PRÉSENT : ils décrivent donc l'état audité, et
 * devront être inversés le jour où l'écart sera corrigé (c'est justement le
 * signal de sortie).
 *
 * Branche d'audit uniquement — jamais fusionnée.
 */
class IdentityAccessControlGapsTest extends TestCase
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
     * ÉCART 1 — Aucun endpoint de SÉLECTION DE CONTEXTE.
     *
     * Une personne membre de deux entreprises ne peut pas choisir dans
     * laquelle elle travaille : `AuthService::findEmployeeInTenantSchemas()`
     * retourne le PREMIER schéma qui contient son e-mail, dans l'ordre de
     * `public.companies`. Le contexte est donc arbitraire du point de vue de
     * l'utilisateur.
     */
    public function test_there_is_no_endpoint_to_select_a_tenant_context(): void
    {
        $candidates = [
            'api/v1/auth/contexts',
            'api/v1/auth/select-tenant',
            'api/v1/auth/switch-tenant',
            'api/v1/auth/companies',
        ];

        $found = [];

        foreach ($candidates as $uri) {
            foreach (Route::getRoutes() as $route) {
                if (in_array($uri, $route->uri() === $uri ? [$uri] : [], true)) {
                    $found[] = $uri;
                }
            }
        }

        // Recherche plus large : toute route dont l'URI contient « context » ou
        // « switch » sous /auth.
        $authContextRoutes = array_values(array_filter(
            array_map(static fn ($route): string => $route->uri(), iterator_to_array(Route::getRoutes())),
            static fn (string $uri): bool => str_starts_with($uri, 'api/')
                && (str_contains($uri, 'context') || str_contains($uri, 'switch-tenant') || str_contains($uri, 'select-tenant'))
                && str_contains($uri, 'auth'),
        ));

        $this->assertSame(
            [],
            array_merge($found, $authContextRoutes),
            'CONSTAT : aucun endpoint de sélection de contexte tenant n\'existe. '
            .'Pour une personne présente dans 2 entreprises, le contexte de login est '
            .'choisi arbitrairement par AuthService::findEmployeeInTenantSchemas().',
        );
    }

    /**
     * ÉCART 2 — Le grand public n'a PAS une identité `users`.
     *
     * `travel_customer_accounts` est une table et un modèle SÉPARÉS
     * (module TravelAgency, schéma public), avec son propre guard Sanctum
     * `travel_customer`. C'est donc une TROISIÈME identité à côté de
     * `users` (public) et `employees` (tenant) — contraire à la décision
     * « aucune troisième identité ».
     */
    public function test_public_consumer_identity_is_a_separate_model_not_user(): void
    {
        $this->assertTrue(
            class_exists(\App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount::class),
            'Le modèle TravelCustomerAccount existe — c\'est la 3e identité.',
        );

        $this->assertFalse(
            is_subclass_of(
                \App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount::class,
                \App\Core\Auth\Domain\Models\User::class,
            ),
            'CONSTAT : TravelCustomerAccount n\'hérite PAS de User — les deux identités '
            .'sont disjointes et non reliées par phone_e164.',
        );

        $guards = config('auth.guards');

        $this->assertArrayHasKey(
            'travel_customer',
            $guards,
            'Un guard Sanctum dédié existe pour ce 3e type d\'identité.',
        );
    }

    /**
     * ÉCART 3 — Le rattachement `users` ↔ `employees` existe en base mais
     * n'est pas utilisé au login.
     *
     * `user_employee_links` porte bien `unique(user_id, company_id)`, mais la
     * connexion employé passe par `employees.email` et
     * `AuthService::findEmployeeInTenantSchemas()`, sans jamais consulter ce
     * lien. Le lien est donc décoratif pour l'authentification : c'est la
     * « trajectoire de convergence » que l'ADR doit arbitrer.
     */
    public function test_user_employee_link_exists_but_login_does_not_use_it(): void
    {
        // Constat vérifié à la SOURCE (migration réelle, pas le schéma MVP de
        // test) : la table de liaison est créée par une migration publique et
        // porte unique(user_id, company_id).
        $migration = base_path('database/migrations/public/2026_05_02_100001_create_users_and_company_requests_tables.php');
        $source = (string) file_get_contents($migration);

        $this->assertStringContainsString('user_employee_links', $source);
        $this->assertStringContainsString("unique(['user_id', 'company_id'])", $source,
            'La clé inclut company_id : N liens par personne, une par entreprise.');

        // Et le login employé ne consulte JAMAIS ce lien.
        $authService = (string) file_get_contents(base_path('app/Core/Auth/Infrastructure/Services/AuthService.php'));

        $this->assertStringNotContainsString('UserEmployeeLink', $authService,
            'CONSTAT : AuthService ne référence jamais UserEmployeeLink — la connexion '
            .'employé passe par employees.email + findEmployeeInTenantSchemas(), pas par '
            .'le lien d\'identité. Le lien est décoratif pour l\'authentification.');
    }

    /**
     * ÉCART 4 — Il n'existe AUCUN concept de rôle personnalisé.
     *
     * Les rôles sont des enums figés (`PlatformRole`, `employees.role`,
     * `manager_role`). Aucune table `roles`/`permissions` de type Spatie, donc
     * aucune possibilité pour un admin de tenant de créer un rôle sur mesure,
     * et donc a fortiori aucune garde « un rôle personnalisé ne peut pas
     * dépasser les droits de son créateur » — ce scénario est structurellement
     * impossible, ce qui est un état sûr mais une limite produit.
     */
    public function test_no_custom_role_table_exists(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasTable('roles'),
            'Aucune table `roles` : pas de rôles personnalisés.',
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasTable('permissions'),
            'Aucune table `permissions` : les permissions sont des enums de code.',
        );

        // Les rôles réellement disponibles sont figés.
        $this->assertSame(
            ['super_admin', 'admin', 'support', 'finance', 'ops', 'marketing'],
            \App\Core\Tenant\Domain\Enums\PlatformRole::values(),
            'Les 6 rôles plateforme sont figés dans l\'enum.',
        );
    }

    /**
     * ÉCART 5 — L'impersonation n'a PAS de consentement de l'utilisateur.
     *
     * La table trace qui/quand/pourquoi/durée et permet de révoquer le token
     * dédié, mais aucune colonne ne recueille l'accord de la personne
     * impersonnée ni ne la notifie.
     */
    public function test_impersonation_has_no_consent_column(): void
    {
        $migration = (string) file_get_contents(
            base_path('database/migrations/public/2026_07_23_000003_create_platform_impersonation_sessions_table.php')
        );

        foreach (['consent', 'consented_at', 'notified_at'] as $needle) {
            $this->assertStringNotContainsString(
                "'{$needle}'",
                $migration,
                "CONSTAT : la colonne `{$needle}` n'existe pas — l'impersonation est "
                .'tracée (raison, IP, expiration, révocation) mais non consentie.',
            );
        }

        // Les garde-fous bien présents :
        $this->assertStringContainsString("'reason'", $migration, 'La raison est obligatoire.');
        $this->assertStringContainsString("'expires_at'", $migration, 'La session expire.');
        $this->assertStringContainsString("'personal_access_token_id'", $migration,
            'Le token est révocable individuellement.');
    }
}
