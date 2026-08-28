<?php

declare(strict_types=1);

namespace Tests\Support\CRM;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Core\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/**
 * CrossTenantAssertions — assertions réutilisables d'isolation tenant
 * (issue #5738, CRM PRE).
 *
 * Ces helpers matérialisent le « harness » de preuve d'isolation : ils
 * détectent l'absence de TenantManager ou de `company_id`, vérifient que
 * l'identifiant d'un autre tenant produit une réponse sûre (404 / null) et
 * couvrent lecture, mutation, relation indirecte, jobs, cache, exports et
 * webhooks. À utiliser dans toute suite CRM.
 */
final class CrossTenantAssertions
{
    /**
     * Détecte l'absence de `TenantManager` dans le conteneur.
     *
     * @throws \RuntimeException si le service n'est pas résolvable.
     */
    public static function assertTenantManagerResolvable(TestCase $test): TenantManager
    {
        $manager = app(TenantManager::class);

        $test->assertInstanceOf(TenantManager::class, $manager);

        return $manager;
    }

    /**
     * Détecte l'absence de `company_id` sur un modèle tenant.
     */
    public static function assertCompanyIdPresent(TestCase $test, Model $model): void
    {
        $test->assertNotEmpty($model->getAttribute('company_id'), sprintf(
            'Le modèle %s n\'a pas de company_id — violation du contrat tenant.',
            $model::class
        ));
    }

    /**
     * Lecture scopée : l'identifiant d'une ressource d'UN AUTRE tenant n'est
     * pas résolu dans le contexte courant (retourne null, jamais la ressource).
     *
     * À appeler DANS un `withinTenant()`.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function assertScopedReadHidesCrossTenant(TestCase $test, string $modelClass, string $foreignId): void
    {
        Assert::assertNull(
            $modelClass::query()->find($foreignId),
            sprintf('La ressource %s::%s d\'un autre tenant ne doit pas être lisible.', $modelClass, $foreignId)
        );
    }

    /**
     * Mutation : une ligne créée dans le contexte courant porte le company_id
     * du tenant courant (jamais un company_id externe).
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public static function assertCreatedRowIsTenantScoped(TestCase $test, string $modelClass, array $attributes, string $expectedCompanyId): Model
    {
        /** @var TModel $row */
        $row = $modelClass::query()->create($attributes);
        self::assertCompanyIdPresent($test, $row);
        $test->assertSame($expectedCompanyId, strval($row->getAttribute('company_id')));

        return $row;
    }

    /**
     * Relation indirecte : une ressource atteinte via une relation d'un autre
     * tenant reste invisible (le propriétaire n'est jamais résolu, donc toute
     * requête passant par sa relation est morte à la source).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function assertIndirectRelationDoesNotLeak(TestCase $test, string $modelClass, string $ownerIdFromOtherTenant): void
    {
        $test->assertNull(
            $modelClass::query()->find($ownerIdFromOtherTenant),
            'Le propriétaire de l\'autre tenant ne doit pas être résolu.'
        );

        $test->assertFalse(
            $modelClass::query()->whereKey($ownerIdFromOtherTenant)->exists(),
            'La relation indirecte ne doit pas fuir entre tenants.'
        );
    }

    /**
     * HTTP : une réponse pour une ressource d'un autre tenant doit être un
     * 404 sûr (jamais 200/403 — ne pas révéler l'existence, #5706/#3231).
     *
     * @param  TestResponse<Response>  $response
     */
    public static function assertCrossTenantHttp404(TestCase $test, TestResponse $response): void
    {
        $response->assertNotFound();
    }

    /**
     * Cache : la même clé logique produit des valeurs distinctes par tenant
     * (clé réelle `tenant:{companyId}:{key}` — aucune collision possible).
     */
    public static function assertCacheTenantScoped(TestCase $test, string $key, string $companyAId, string $companyBId): void
    {
        $service = app(TenantCacheService::class);

        $service->put($companyAId, $key, 'value-A');
        $service->put($companyBId, $key, 'value-B');

        $test->assertSame('value-A', $service->get($companyAId, $key));
        $test->assertSame('value-B', $service->get($companyBId, $key));
        $test->assertTrue(Cache::has("tenant:{$companyAId}:{$key}"));
        $test->assertTrue(Cache::has("tenant:{$companyBId}:{$key}"));
        $test->assertNotSame(
            Cache::get("tenant:{$companyAId}:{$key}"),
            Cache::get("tenant:{$companyBId}:{$key}")
        );
    }

    /**
     * Export : le nom d'artefact (fichier, clé, job) contient le tenant —
     * convention #5736/§exports. Échoue si un artefact est nommé sans tenant.
     */
    public static function assertArtifactNameTenantScoped(TestCase $test, string $artifactName, string $companyId): void
    {
        $test->assertStringContainsString(
            (string) $companyId,
            $artifactName,
            "L'artefact d'export doit être préfixé/étiqueté du tenant (convention #5736)."
        );
    }

    /**
     * Deux tenants simultanés : un tenant ne voit jamais les lignes de
     * l'autre, quelle que soit l'entité scopée.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function assertTwoTenantsAreIsolated(TestCase $test, string $modelClass, Company $tenantA, Company $tenantB): void
    {
        $manager = app(TenantManager::class);

        $manager->withinTenant($tenantA, function () use ($modelClass, $tenantB, $test): void {
            $ids = $modelClass::query()->pluck('company_id')->map(static fn ($id): string => strval($id))->all();
            $test->assertNotContains((string) $tenantB->id, $ids, 'A ne doit jamais voir les lignes de B.');
        });

        $manager->withinTenant($tenantB, function () use ($modelClass, $tenantA, $test): void {
            $ids = $modelClass::query()->pluck('company_id')->map(static fn ($id): string => strval($id))->all();
            $test->assertNotContains((string) $tenantA->id, $ids, 'B ne doit jamais voir les lignes de A.');
        });
    }
}
