<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use App\Modules\Accounting\Infrastructure\Services\AccountingChartOfAccounts;
use App\Modules\Accounting\Infrastructure\Services\ChartOfAccountsDefaults;
use App\Modules\Accounting\Infrastructure\Services\ChartOfAccountsService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7925 — seed du plan comptable par référentiel pays au provisioning :
 * zone OHADA → SYSCOHADA (521/571/44571/44566), TR → Tekdüzen (120/391,
 * libellés tr + fr), CA → plan nord-américain, DZ/FR inchangés (PCG,
 * non-régression), idempotence conservée.
 */
class AccountingChartSeedByCountryTest extends TestCase
{
    use RefreshTenantDatabase;

    private function provision(string $country, ?string $language = null): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => $country,
            'language' => $language ?? 'fr',
        ]);

        app(ChartOfAccountsService::class)->ensureProvisioned(
            $company->id,
            $company->country,
            $company->language,
        );

        return $company;
    }

    /**
     * @return array<array-key, array{label: string, type: string, class: int}>
     */
    private function seededAccounts(Company $company): array
    {
        $out = [];

        foreach (AccountingChartAccount::query()->where('company_id', $company->id)->get() as $account) {
            $out[$account->code] = [
                'label' => $account->label,
                'type' => $account->type,
                'class' => (int) $account->class,
            ];
        }

        return $out;
    }

    // ── SYSCOHADA (zone OHADA) ───────────────────────────────────────────

    public function test_cameroon_tenant_receives_syscohada_chart(): void
    {
        $accounts = $this->seededAccounts($this->provision('CM'));

        $this->assertSame('Banques', $accounts['521']['label'] ?? null);
        $this->assertSame('Caisse', $accounts['571']['label'] ?? null);
        $this->assertSame('liability', $accounts['44571']['type'] ?? null);
        $this->assertSame('asset', $accounts['44566']['type'] ?? null);
        // Le PCG français (512 Banques / 53 Caisse) n'est PAS seedé.
        $this->assertArrayNotHasKey('512', $accounts);
        $this->assertArrayNotHasKey('53', $accounts);
        $this->assertCount(count(ChartOfAccountsDefaults::SYSCOHADA_ACCOUNTS), $accounts);
    }

    public function test_all_ohada_countries_resolve_syscohada_family(): void
    {
        foreach (AccountingChartOfAccounts::OHADA_COUNTRIES as $country) {
            $this->assertSame(
                AccountingChartOfAccounts::FAMILY_SYSCOHADA,
                AccountingChartOfAccounts::familyFor($country),
                $country,
            );

            $codes = array_column(ChartOfAccountsDefaults::forCountry($country), 'code');
            $this->assertContains('521', $codes, $country);
            $this->assertContains('571', $codes, $country);
        }
    }

    // ── Tekdüzen (Turquie) ───────────────────────────────────────────────

    public function test_turkish_tenant_receives_tekduzen_chart_with_turkish_labels(): void
    {
        $accounts = $this->seededAccounts($this->provision('TR', 'tr'));

        $this->assertSame('Alıcılar', $accounts['120']['label'] ?? null);
        $this->assertSame('Hesaplanan KDV', $accounts['391']['label'] ?? null);
        $this->assertSame('İndirilecek KDV', $accounts['191']['label'] ?? null);
        $this->assertSame('Satıcılar', $accounts['320']['label'] ?? null);
        $this->assertSame('revenue', $accounts['600']['type'] ?? null);
        $this->assertArrayNotHasKey('411', $accounts);
        $this->assertCount(count(ChartOfAccountsDefaults::TEKDUZEN_ACCOUNTS), $accounts);
    }

    public function test_tekduzen_labels_are_localized_in_french_when_tenant_language_is_fr(): void
    {
        $accounts = $this->seededAccounts($this->provision('TR', 'fr'));

        $this->assertSame('Clients', $accounts['120']['label'] ?? null);
        $this->assertSame('TVA collectée (KDV)', $accounts['391']['label'] ?? null);
    }

    // ── Canada ───────────────────────────────────────────────────────────

    public function test_canadian_tenant_receives_north_american_chart(): void
    {
        $accounts = $this->seededAccounts($this->provision('CA', 'en'));

        $this->assertSame('Accounts receivable', $accounts['1100']['label'] ?? null);
        $this->assertSame('GST/HST payable', $accounts['2250']['label'] ?? null);
        $this->assertSame('QST payable', $accounts['2260']['label'] ?? null);
        $this->assertArrayNotHasKey('411', $accounts);
        $this->assertCount(count(ChartOfAccountsDefaults::CA_ACCOUNTS), $accounts);
    }

    // ── Non-régression PCG (DZ/FR) ───────────────────────────────────────

    public function test_dz_and_fr_tenants_keep_pcg_chart_unchanged(): void
    {
        foreach (['DZ', 'FR'] as $country) {
            $accounts = $this->seededAccounts($this->provision($country));

            $this->assertSame('Clients', $accounts['411']['label'] ?? null, $country);
            $this->assertSame('Banques', $accounts['512']['label'] ?? null, $country);
            $this->assertSame('Caisse', $accounts['53']['label'] ?? null, $country);
            $this->assertCount(count(ChartOfAccountsDefaults::ACCOUNTS), $accounts, $country);
        }
    }

    // ── Idempotence + résolution du pays depuis le registre ─────────────

    public function test_provisioning_is_idempotent_and_resolves_country_from_company_registry(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'language' => 'fr']);

        // Sans passer le pays : résolu depuis public.companies.
        $service = app(ChartOfAccountsService::class);
        $this->assertTrue($service->ensureProvisioned($company->id));
        $this->assertFalse($service->ensureProvisioned($company->id));

        $accounts = $this->seededAccounts($company);
        $this->assertArrayHasKey('521', $accounts);
        $this->assertCount(count(ChartOfAccountsDefaults::SYSCOHADA_ACCOUNTS), $accounts);
    }
}
