<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-17 (#1547/#1595) — chiffrement au repos des données personnelles de paie.
 *
 * `payment_documents.metadata` (JSON) peut contenir des références de paiement,
 * montants et périodes : on le chiffre avec le cast `encrypted:array` du modèle
 * PaymentDocument.
 *
 * 1. La colonne passe de `json` à `text` : le cast encrypted:array stocke un
 *    payload chiffré (base64, non-JSON) — PostgreSQL rejette ce format dans une
 *    colonne json (SQLSTATE 22P02). Même convention que les colonnes chiffrées
 *    existantes (employees.iban/bank_account : text + EncryptedCast).
 * 2. Backfill : chaque ligne en clair est chiffrée avec la clé APP_KEY
 *    (AES-256, même encrypter que le cast Eloquent). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_documents')) {
            return;
        }

        // 1) json → text pour permettre le stockage du payload chiffré.
        $columnType = DB::selectOne("
            SELECT data_type FROM information_schema.columns
            WHERE table_name = 'payment_documents'
              AND column_name = 'metadata'
              AND table_schema = current_schema()
        ");
        if ($columnType && $columnType->data_type === 'json') {
            Schema::table('payment_documents', function (Blueprint $table): void {
                $table->text('metadata')->nullable()->change();
            });
        }

        // 2) Backfill des lignes historiques en clair.
        $rows = DB::table('payment_documents')
            ->whereNotNull('metadata')
            ->whereRaw("metadata::text <> ''")
            ->get(['id', 'metadata']);

        foreach ($rows as $row) {
            try {
                // Déjà chiffré (payload valide pour l'encrypter courant) → rien à faire.
                Crypt::decryptString((string) $row->metadata);
                continue;
            } catch (DecryptException) {
                // En clair → chiffrer (la valeur brute est un JSON string).
            }

            DB::table('payment_documents')
                ->where('id', $row->id)
                ->update(['metadata' => Crypt::encryptString((string) $row->metadata)]);
        }
    }

    public function down(): void
    {
        // Non destructif : le cast encrypted:array lit les deux formats.
        // Un déchiffrement massif serait risqué (perte de données) — conservé.
    }
};
