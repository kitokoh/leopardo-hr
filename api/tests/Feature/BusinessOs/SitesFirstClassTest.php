<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-T-01 / #8624 — l'établissement est un axe de données de premier ordre.
 *
 * Ce test verrouille les invariants qui font qu'un `sites` est réellement
 * exploitable, et pas seulement une adresse :
 *
 *  1. `code` unique PAR TENANT, jamais globalement (deux clients peuvent
 *     utiliser `PRINCIPAL`) ;
 *  2. le même code dans le même tenant est refusé au niveau BASE (23505) et
 *     pas seulement par la validation applicative — c'est la garantie dont
 *     dépendent les imports de caisse ;
 *  3. les colonnes ajoutées existent et portent leurs défauts ;
 *  4. le provisioning guidé crée exactement UN établissement principal, et un
 *     rejeu n'en ajoute pas un second (idempotence, lien #8626).
 */
class SitesFirstClassTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function site(Company $company, ?string $code, array $attributes = []): Site
    {
        /** @var Site $site */
        $site = Site::query()->create([
            'company_id' => $company->id,
            'name' => 'Établissement '.$code,
            'code' => $code,
            'site_type' => 'restaurant',
            ...$attributes,
        ]);

        return $site;
    }

    public function test_site_code_is_unique_per_tenant_not_globally(): void
    {
        $this->site($this->companyA, 'PRINCIPAL');
        $this->site($this->companyB, 'PRINCIPAL');

        $this->assertSame(2, Site::withoutGlobalScopes()->where('code', 'PRINCIPAL')->count());
    }

    public function test_duplicate_code_in_same_tenant_is_rejected_by_the_database(): void
    {
        $this->site($this->companyA, 'SUD-01');

        // Savepoint imbriqué : l'échec est isolé, la transaction du test reste
        // utilisable (sinon PostgreSQL bascule en 25P02 et tout le tearDown
        // échoue — piège déjà rencontré sur ce dépôt).
        try {
            DB::transaction(fn () => $this->site($this->companyA, 'SUD-01'));
            $this->fail("Un code d'établissement dupliqué doit être refusé par la base.");
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('23505', $e->getCode(), 'Violation d’unicité attendue (23505).');
        }

        $this->assertSame(1, Site::query()->where('code', 'SUD-01')->count());
    }

    public function test_new_columns_exist_with_their_defaults(): void
    {
        $site = $this->site($this->companyA, 'NORD-01', ['site_type' => null]);

        // Les défauts sont posés par la BASE (DEFAULT côté serveur), pas par
        // Eloquent : on relit la ligne pour prouver le comportement réel.
        $site->refresh();

        $this->assertSame('active', $site->status);
        $this->assertSame(100, $site->gps_radius_m);
        $this->assertNull($site->site_type);
    }

    public function test_null_codes_do_not_collide(): void
    {
        // Index partiel `WHERE code IS NOT NULL` : les établissements
        // historiques sans code ne se heurtent pas entre eux.
        $this->site($this->companyA, null);
        $this->site($this->companyA, null);

        $this->assertSame(2, Site::query()->whereNull('code')->count());
    }

    public function test_active_scope_excludes_closed_sites(): void
    {
        $this->site($this->companyA, 'OUVERT');
        $this->site($this->companyA, 'FERME', ['status' => Site::STATUS_INACTIVE]);

        $this->assertSame(['OUVERT'], Site::query()->active()->pluck('code')->all());
    }

    public function test_guided_trial_creates_exactly_one_primary_site(): void
    {
        app(ProvisionGuidedTrial::class)->execute(
            email: 'fondateur@exemple.test',
            companyName: 'Groupe Test',
            country: 'DZ',
        );

        /** @var Company $company */
        $company = Company::query()->where('email', 'fondateur@exemple.test')->firstOrFail();

        $sites = DB::table('shared_tenants.sites')->where('company_id', $company->id)->get();

        $this->assertCount(1, $sites, 'Un tenant doit naître avec un lieu d’exploitation.');
        $first = $sites->first();
        $this->assertNotNull($first);
        $this->assertSame('PRINCIPAL', (string) $first->code);
    }

    public function test_replayed_provisioning_does_not_create_a_second_site(): void
    {
        $action = app(ProvisionGuidedTrial::class);

        $action->execute(
            email: 'fondateur2@exemple.test',
            companyName: 'Groupe Rejeu',
            country: 'DZ',
        );

        /** @var Company $company */
        $company = Company::query()->where('email', 'fondateur2@exemple.test')->firstOrFail();

        // Rejeu : la branche idempotente #3600 réutilise le tenant existant.
        $action->execute(
            email: 'fondateur2@exemple.test',
            companyName: 'Groupe Rejeu',
            country: 'DZ',
        );

        $this->assertSame(
            1,
            DB::table('shared_tenants.sites')->where('company_id', $company->id)->count(),
            'Un rejeu de provisioning ne doit jamais dupliquer l’établissement principal.'
        );
    }

    public function test_site_is_scoped_to_its_tenant(): void
    {
        $siteB = $this->site($this->companyB, 'B-01');

        // Le scope `BelongsToCompany` s'appuie sur le tenant courant.
        app(\App\Core\Tenant\TenantManager::class)->setTenant($this->companyA);

        try {
            $this->assertSame(0, Site::query()->where('code', 'B-01')->count());
            $this->assertNotNull(Site::withoutGlobalScopes()->find($siteB->id));
        } finally {
            app(\App\Core\Tenant\TenantManager::class)->resetToPrevious();
        }
    }
}
