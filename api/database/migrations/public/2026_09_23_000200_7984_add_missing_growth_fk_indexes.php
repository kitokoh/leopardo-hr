<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7984 (audit 2026-09-20, point 1) — index manquants sur les FK du
 * module Growth. PostgreSQL n'indexe pas automatiquement les colonnes
 * porteuses d'une contrainte FK : `foreignId()->constrained()` crée la
 * colonne + la contrainte, pas l'index → joins et cascades en seq-scan.
 *
 * Périmètre réellement retenu après revérification (faux positifs exclus) :
 * - `partner_referrals.partner_id`, `commissions.partner_id`,
 *   `partner_links.partner_id`, `partner_payout_requests.partner_id` : AJOUTÉS ici.
 * - `partners.user_id` : déjà couvert par l'unique `partners_user_id_unique`
 *   (2026_08_15_000010) — un index unique EST un index.
 * - `partner_clicks.partner_link_id` : déjà couvert par l'index composite
 *   `partner_clicks_partner_link_id_clicked_at_index` (hardening 2026_06_13_000004,
 *   colonne de tête = la FK).
 * - `travel_webhook_subscriptions.company_id` (tenant) : déjà couvert par
 *   l'unique `(company_id, url)` (2026_08_30_000925), colonne de tête = company_id.
 */
return new class extends Migration
{
    /** @var array<string, string> table => colonne FK à indexer */
    private const INDEXES = [
        'partner_referrals' => 'partner_id',
        'commissions' => 'partner_id',
        'partner_links' => 'partner_id',
        'partner_payout_requests' => 'partner_id',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $index = sprintf('%s_%s_index', $table, $column);

            if (DB::getDriverName() === 'pgsql') {
                // IF NOT EXISTS natif — idempotent, même motif que le hardening #1710.
                DB::statement(sprintf(
                    'CREATE INDEX IF NOT EXISTS %s ON %s (%s)',
                    $index,
                    $table,
                    $column
                ));
            } else {
                Schema::table($table, function (Blueprint $blueprint) use ($column, $index): void {
                    $blueprint->index([$column], $index);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $index = sprintf('%s_%s_index', $table, $column);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(sprintf('DROP INDEX IF EXISTS %s', $index));
            } else {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index);
                });
            }
        }
    }
};
