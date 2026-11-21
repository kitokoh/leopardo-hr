<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreEmployeeRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeRequest;
use App\Support\SearchPathGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\RefreshTenantDatabase;
use Tests\Support\FixturePasswords;
use Tests\TestCase;

/**
 * Issue #9115 — `StoreEmployeeRequest` / `UpdateEmployeeRequest` posaient le
 * `search_path` du tenant dans `prepareForValidation()` sans jamais le rendre.
 *
 * ## Pourquoi ce test n'utilise PAS la pile HTTP
 *
 * Le middleware `tenant` restaure déjà le `search_path` dans son propre
 * `finally` (`TenantMiddleware::handle()` → `resetToPrevious()`). Un test qui
 * passe par `$this->postJson(...)` verrait donc la session restaurée **même
 * sans le correctif** : il ne prouverait rien sur le FormRequest.
 *
 * On appelle donc `validateResolved()` directement — c'est la méthode que le
 * conteneur invoque avant l'action du contrôleur, et elle exécute bien
 * `prepareForValidation()` puis la validation. L'état de la session est mesuré
 * juste après, sans middleware pour masquer une fuite.
 *
 * ## Ce que ces tests prouvent
 *
 * 1. validation RÉUSSIE → `search_path` rendu à son état d'entrée ;
 * 2. validation ÉCHOUÉE (422) → `search_path` rendu quand même (c'est le cas
 *    que `passedValidation()` ne couvre pas, puisqu'il n'est appelé que si la
 *    validation passe) ;
 * 3. le `search_path` tenant est bien POSÉ pendant la validation — sans quoi
 *    les règles `Rule::unique` / `Rule::exists` interrogeraient le mauvais
 *    schéma et le correctif serait un simple retrait de fonctionnalité.
 */
final class EmployeeRequestSearchPathTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Preuve search_path : PostgreSQL uniquement.');
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'tenancy_type' => 'shared',
            'schema_name' => 'shared_tenants',
        ]);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
        ]);
        $this->manager = $manager;
    }

    public function test_store_request_restores_search_path_after_successful_validation(): void
    {
        $this->assertSearchPathRestored(
            $this->makeStoreRequest([
                'first_name' => 'Ali',
                'last_name' => 'Said',
                'email' => 'ali.said@x.dz',
                'role' => 'employee',
                'password' => FixturePasswords::VALID,
            ]),
        );
    }

    public function test_store_request_restores_search_path_when_validation_fails(): void
    {
        // `first_name` manquant → ValidationException. C'est le cas que
        // `passedValidation()` ne couvre pas : sans le `finally` du garde, la
        // session resterait sur le schéma du tenant après un simple 422.
        $this->assertSearchPathRestored(
            $this->makeStoreRequest([
                'last_name' => 'Said',
                'email' => 'ali.said@x.dz',
                'role' => 'employee',
                'password' => FixturePasswords::VALID,
            ]),
        );
    }

    public function test_update_request_restores_search_path_when_validation_fails(): void
    {
        $request = UpdateEmployeeRequest::create('/api/v1/employees/1', 'PUT', [
            'email' => 'pas-un-email',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn (): Employee => $this->manager);
        app()->instance('current_company', $this->company);

        $this->assertSearchPathRestored($request);
    }

    public function test_tenant_search_path_is_wired_for_schema_tenancy(): void
    {
        // Contrôle négatif : si le correctif avait simplement supprimé la
        // bascule, les règles `Rule::unique`/`Rule::exists` interrogeraient le
        // mauvais schéma. On vérifie donc que le chemin tenant est bien
        // CALCULÉ — c'est lui qui est passé à `SearchPathGuard::run()`.
        //
        // La restauration elle-même est prouvée par les trois tests ci-dessus,
        // qui exécutent une vraie validation. Ici on ne touche pas à la base :
        // le mode schema-per-tenant est GELÉ (ADR-0028 / BOS-005 #8203,
        // `Company::booted()` refuse toute création `tenancy_type=schema`), et
        // valider contre un schéma vide avorterait la transaction du test.
        $schema = 't_req_'.substr(md5(uniqid('', true)), 0, 8);

        $tenant = new Company([
            'tenancy_type' => 'schema',
            'schema_name' => $schema,
        ]);
        $tenant->id = $this->company->id;

        $request = $this->makeStoreRequest([
            'first_name' => 'Ali',
            'last_name' => 'Said',
            'email' => 'ali.said@x.dz',
            'role' => 'employee',
            'password' => FixturePasswords::VALID,
        ], $tenant);

        $method = new \ReflectionMethod($request, 'tenantSearchPath');
        $method->setAccessible(true);

        $this->assertSame(
            '"'.$schema.'",public',
            $method->invoke($request),
            'Le FormRequest doit cibler le schéma du tenant (sinon la bascule a été retirée).',
        );
    }

    /**
     * Pose un état d'entrée distinctif, exécute la validation, et vérifie que
     * la session est revenue exactement à cet état.
     */
    private function assertSearchPathRestored(StoreEmployeeRequest|UpdateEmployeeRequest $request): void
    {
        DB::statement('SET search_path TO public');

        $before = SearchPathGuard::current();
        $this->assertSame('public', $before, 'État d\'entrée du test non appliqué.');

        try {
            $request->validateResolved();
        } catch (ValidationException) {
            // Attendu sur le cas « validation échouée » : on ne mesure que
            // l'état de la session, pas le verdict de validation.
        }

        $this->assertSame(
            $before,
            SearchPathGuard::current(),
            'Le search_path doit être rendu à son état d\'entrée après la validation '
            .'(issue #9115 : la session restait sur le schéma du tenant).',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeStoreRequest(array $payload, ?Company $company = null): StoreEmployeeRequest
    {
        $company ??= $this->company;

        // `tenantSearchPath()` lit `$this->user()?->company` en premier : on
        // aligne la relation du manager sur la société testée, sinon la
        // société partagée du setUp l'emporterait.
        $this->manager->setRelation('company', $company);

        $request = StoreEmployeeRequest::create('/api/v1/employees', 'POST', $payload);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn (): Employee => $this->manager);
        app()->instance('current_company', $company);

        return $request;
    }
}
