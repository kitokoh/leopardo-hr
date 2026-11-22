<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8866 (lot L4 de #8396) — `crm_contacts.phone` ne peut pas contenir la
 * valeur que le code y écrit.
 *
 * Le modèle `CrmContact` caste `email` ET `phone` en `encrypted`
 * (#5713 / #8332, PII chiffrée au repos). Or la migration d'origine
 * `2026_08_28_000002_5708_create_crm_contacts_table.php` déclare :
 *
 *   - `email`  → `text`            (assez large : OK)
 *   - `phone`  → `string('phone', 60)` (TROP ÉTROIT)
 *
 * Le payload chiffré d'un numéro de téléphone fait 228 caractères
 * (AES-256-CBC + base64 + enveloppe JSON de Laravel, mesuré : 228 pour
 * `+213555999999` comme pour `0600000000`). Écrire un contact avec un
 * téléphone lève donc :
 *
 *   SQLSTATE[22001]: String data, right truncated:
 *   value too long for type character varying(60)
 *
 * Effet mesuré, et non seulement en test : `POST /api/v1/crm/contacts`
 * (CreateContactAction → cast `encrypted`) renvoie **HTTP 500** dès qu'un
 * téléphone est fourni. C'est la même classe de défaut que #8862 : une
 * fonctionnalité livrée dont la colonne ne peut pas porter ce que le code
 * applicatif y écrit.
 *
 * Correctif : élargir `phone` en `text`, exactement comme `email` l'est déjà
 * dans la même table. La migration est **réentrante** et sans perte :
 * `schemaTableExists` + inspection du type courant avant tout ALTER, donc la
 * rejouer sur une base déjà corrigée (ou sur un tenant non provisionné) ne
 * fait rien.
 *
 * La migration d'origine est conservée telle quelle : elle est déjà appliquée
 * partout, la modifier ne rejouerait rien et ferait diverger l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widenPhoneToText();
    }

    public function down(): void
    {
        // Volontairement vide : la colonne `phone` appartient à la
        // fonctionnalité CRM livrée par #5708/#5713, pas à ce rattrapage.
        // La rétrécir en rollback casserait l'écriture des contacts chiffrés
        // (règle #8207 : un `down()` ne détruit que ce que son `up()` a créé ;
        // ici l'état cible EST l'état attendu par le code applicatif).
    }

    private function widenPhoneToText(): void
    {
        // Garde résolue EXPLICITEMENT sur les schémas tenant, pas via le
        // search_path de la session.
        //
        // `schemaTableExists()` suit `current_schemas(false)` : lancée par un
        // `migrate --path=…` dont la session a un search_path réduit à
        // `public` (cas du test `CrmMigrationsTest`, et de tout appel artisan
        // hors requête HTTP), la garde répondait `false` alors que
        // `crm_contacts` existait bien dans `shared_tenants` — l'ALTER était
        // silencieusement sauté et `phone` restait `varchar(60)`, donc le cast
        // `encrypted` continuait de lever `22001` en production.
        $schema = $this->resolveCrmSchema();
        if ($schema === null) {
            return;
        }

        if (! Schema::hasColumn($schema.'.crm_contacts', 'phone')) {
            return;
        }

        // Idempotence : ne rien faire si la colonne est déjà en `text`.
        $type = DB::selectOne(
            'SELECT data_type
               FROM information_schema.columns
              WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?',
            [$schema, 'crm_contacts', 'phone']
        );

        if ($type !== null && ($type->data_type ?? null) === 'text') {
            return;
        }

        DB::statement(sprintf('ALTER TABLE %s.crm_contacts ALTER COLUMN phone TYPE text', $schema));
    }

    /**
     * Schéma réel de `crm_contacts`, cherché dans les schémas tenant connus
     * plutôt que dans le seul `search_path` de la session.
     */
    private function resolveCrmSchema(): ?string
    {
        $row = DB::selectOne(
            "SELECT table_schema
               FROM information_schema.tables
              WHERE table_name = 'crm_contacts'
                AND table_schema IN ('shared_tenants', 'public')
              ORDER BY CASE table_schema WHEN 'shared_tenants' THEN 0 ELSE 1 END
              LIMIT 1"
        );

        return $row !== null ? (string) $row->table_schema : null;
    }
};
