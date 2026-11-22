<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #9806 — `public.invoices` doit porter le schéma du modèle Billing.
 *
 * ## Le bug que ce test verrouille
 *
 * Deux tables `invoices` coexistaient : `public.invoices` (13 colonnes, héritée
 * de 2026_04_01_000003) et `shared_tenants.invoices` (17 colonnes, Billing).
 * Le modèle `Invoice` n'épingle aucun schéma : il résout par le `search_path`.
 *
 * En CI le tenant est migré avec `DB_SEARCH_PATH=shared_tenants` → la table
 * riche est créée → tout passe. En dev/production le `search_path` est
 * `public,shared_tenants` → `schemaTableExists('invoices')` trouve la table
 * PAUVRE, la migration tenant saute la création, et `billing:generate-invoices`
 * échoue en `column "subscription_id" does not exist`.
 *
 * ## Ce que ce test mesure
 *
 * Il force le `search_path` de PRODUCTION (`public,shared_tenants`) et vérifie
 * que la table résolue porte bien toutes les colonnes du modèle. Sans le
 * correctif, `public.invoices` n'a ni `subscription_id` ni `number` ni
 * `tax_amount` ni `total` → échec.
 */
class PublicInvoicesSchemaParityTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Colonnes que le modèle `Invoice` et les services Billing écrivent.
     *
     * @return list<string>
     */
    private function requiredColumns(): array
    {
        return [
            'company_id',
            'subscription_id',
            'number',
            'period',
            'amount',
            'currency',
            'tax_amount',
            'total',
            'status',
            'due_date',
            'paid_at',
            'payment_method',
            'stripe_invoice_id',
            'pdf_path',
        ];
    }

    public function test_public_invoices_carries_every_column_the_billing_model_writes(): void
    {
        $present = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'invoices')
            ->pluck('column_name')
            ->all();

        $missing = array_values(array_diff($this->requiredColumns(), $present));

        $this->assertSame(
            [],
            $missing,
            'public.invoices doit porter le schéma Billing. Colonnes manquantes : '.implode(', ', $missing)
        );
    }

    public function test_invoice_model_resolves_to_a_table_that_has_its_columns(): void
    {
        // search_path de PRODUCTION (render.*.yaml, config/database.php défaut).
        $connection = DB::connection();
        $name = $connection->getName();
        config(["database.connections.{$name}.search_path" => 'public,shared_tenants']);
        DB::purge($name);
        DB::reconnect($name);

        $this->assertSame(
            'public, shared_tenants',
            DB::selectOne('SHOW search_path')->search_path
        );

        // La table que le modèle résout réellement doit porter ses colonnes.
        $model = new Invoice;
        $this->assertSame('invoices', $model->getTable());

        $present = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'invoices')
            ->pluck('column_name')
            ->all();

        foreach (['subscription_id', 'number', 'tax_amount', 'total', 'stripe_invoice_id'] as $column) {
            $this->assertContains(
                $column,
                $present,
                "La table résolue par le modèle Invoice doit porter `{$column}` (search_path de production)."
            );
        }
    }

    public function test_monthly_invoice_generation_succeeds_under_production_search_path(): void
    {
        $connection = DB::connection();
        $name = $connection->getName();
        config(["database.connections.{$name}.search_path" => 'public,shared_tenants']);
        DB::purge($name);
        DB::reconnect($name);

        /** @var Company $company */
        $company = Company::factory()->create();

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'operations',
            'status' => 'active',
            'current_period_start' => now()->subMonths(1)->startOfMonth(),
            'current_period_end' => now()->subDays(1),
        ]);

        $exitCode = Artisan::call('billing:generate-invoices');

        $this->assertSame(0, $exitCode);

        // Assertion CIBLÉE sur l'entreprise du test : la base de test est
        // partagée entre les classes (garde #6948), d'autres factures peuvent
        // exister. Ce qui compte : CETTE souscription a produit SA facture.
        $invoice = Invoice::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull(
            $invoice,
            'La facturation mensuelle doit produire une facture sous le search_path de production.'
        );
        $this->assertNotNull($invoice->number);
        $this->assertNotNull($invoice->subscription_id);
        $this->assertSame('99.00', (string) $invoice->total);
    }
}
