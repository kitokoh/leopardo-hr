<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRADE-002 (#8477) — harnais de test BC-36 : schema tenant complet.
 *
 * Garantit que les 19 tables du module Trade sont bien creees par le runner de
 * migrations tenant (`leopardo:migrate`) et donc disponibles dans tous les tests
 * Feature utilisant `RefreshTenantDatabase`.
 */
class TradeSchemaTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array<int, list<string>>
     */
    public static function tradeTables(): array
    {
        return [
            ['trade_company_profiles'],
            ['trade_products'],
            ['trade_counterparties'],
            ['trade_deals'],
            ['trade_rfqs'],
            ['trade_rfq_lines'],
            ['trade_rfq_invitations'],
            ['trade_quotes'],
            ['trade_quote_lines'],
            ['trade_orders'],
            ['trade_shipments'],
            ['trade_transport_requests'],
            ['trade_transport_quotes'],
            ['trade_cost_estimates'],
            ['trade_cost_estimate_lines'],
            ['trade_customs_cases'],
            ['trade_documents'],
            ['trade_messages'],
            ['trade_events'],
        ];
    }

    /**
     * @dataProvider tradeTables
     */
    public function test_trade_table_exists(string $table): void
    {
        self::assertTrue(
            Schema::hasTable($table),
            sprintf('Table "%s" manquante (leopardo:migrate).', $table),
        );
    }

    /**
     * Colonnes d'integration tenant et d'invariants metier sur les objets
     * centraux : leur disparition casserait l'isolation ou la tracabilite.
     */
    public function test_core_tables_expose_tenant_and_invariant_columns(): void
    {
        $expectations = [
            'trade_rfqs' => ['company_id', 'reference', 'trade_deal_id', 'status', 'incoterm', 'awarded_quote_id'],
            'trade_quotes' => ['company_id', 'reference', 'version', 'supersedes_id', 'direction', 'status', 'total_minor', 'validity_until'],
            'trade_orders' => ['company_id', 'reference', 'direction', 'trade_quote_id', 'status', 'total_minor'],
            'trade_cost_estimates' => ['company_id', 'reference', 'fx_rate', 'fx_rate_date', 'fx_source', 'total_minor', 'display_currency'],
            'trade_cost_estimate_lines' => ['company_id', 'trade_cost_estimate_id', 'type', 'amount_minor', 'currency', 'source'],
            'trade_customs_cases' => ['company_id', 'hs_code', 'duty_rate_percent', 'duty_source', 'disclaimer_acknowledged_at'],
            'trade_rfq_invitations' => ['company_id', 'response_token_hash', 'response_token_expires_at', 'response_token_used_at'],
            'trade_documents' => ['company_id', 'disk', 'path', 'type', 'status'],
        ];

        foreach ($expectations as $table => $columns) {
            $listing = Schema::getColumnListing($table);

            foreach ($columns as $column) {
                self::assertContains(
                    $column,
                    $listing,
                    sprintf('Colonne "%s" manquante sur %s.', $column, $table),
                );
            }
        }
    }

    /**
     * Les montants sont stockes en ENTIERS (minor units) : un flottant sur de
     * l'argent introduirait des erreurs d'arrondi silencieuses.
     */
    public function test_monetary_columns_are_not_floating_point(): void
    {
        foreach (['trade_quotes' => 'total_minor', 'trade_orders' => 'total_minor', 'trade_cost_estimates' => 'total_minor'] as $table => $column) {
            $type = Schema::getColumnType($table, $column);

            self::assertContains(
                $type,
                ['integer', 'bigint', 'int8', 'int4'],
                sprintf('%s.%s doit etre un entier (minor units), type observe : %s.', $table, $column, $type),
            );
        }
    }
}
