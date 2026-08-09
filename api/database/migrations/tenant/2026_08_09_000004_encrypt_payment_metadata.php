<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 (#1547/#1595) — chiffrement au repos des métadonnées de paiement.
 *
 * Suite de `2026_08_09_000003` (payment_documents) : les métadonnées des
 * paiements (payment_batches / payment_items / payment_confirmations) peuvent
 * contenir des références de paiement, notes libres, numéros de téléphone ou
 * user-agents — données personnelles au sens RGPD.
 *
 * Même convention que payment_documents :
 * 1. Colonne `json` → `text` (le cast encrypted:array stocke un payload
 *    chiffré base64, non-JSON — PostgreSQL rejette ce format en json, 22P02).
 * 2. Backfill : chaque ligne en clair est chiffrée avec l'encrypter courant
 *    (AES-256, APP_KEY). Idempotente — une valeur déjà chiffrée est laissée
 *    telle quelle.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> table => colonne
     */
    private const TABLES = [
        'payment_batches' => 'metadata',
        'payment_items' => 'metadata',
        'payment_confirmations' => 'metadata',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columnType = DB::selectOne("
                SELECT data_type FROM information_schema.columns
                WHERE table_name = ?
                  AND column_name = ?
                  AND table_schema = current_schema()
            ", [$table, $column]);

            if ($columnType && $columnType->data_type === 'json') {
                Schema::table($table, function (Blueprint $table) use ($column): void {
                    $table->text($column)->nullable()->change();
                });
            }

            $rows = DB::table($table)
                ->whereNotNull($column)
                ->whereRaw("{$column}::text <> ''")
                ->get(['id', $column]);

            foreach ($rows as $row) {
                try {
                    // Déjà chiffré → rien à faire (idempotence).
                    Crypt::decryptString((string) $row->{$column});
                    continue;
                } catch (DecryptException) {
                    // En clair → chiffrer.
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => Crypt::encryptString((string) $row->{$column})]);
            }
        }
    }

    public function down(): void
    {
        // Non destructif : le cast encrypted:array lit les deux formats.
    }
};
