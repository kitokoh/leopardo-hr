<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5588 (audit sécurité 2026-08-26) — `attendance_kiosks.device_code`
 * n'est plus stocké en clair.
 *
 * Contexte : le `device_code` (Str::random(10) majuscules, bonne entropie)
 * était persisté en clair alors que le token ZKTeco (`sync_token_hash`) est
 * haché. Un dump de la base exposait tous les codes. Le code étant une clé
 * de lookup (il voyage dans l'URL : /kiosks/{deviceCode}/...), on ne peut
 * pas utiliser un hash salé non déterministe (bcrypt) — on stocke un
 * **sha256 hex du code en MAJUSCULES** : déterministe (lookup indexé par
 * égalité après hachage de l'entrée) et irréversible au repos.
 *
 * Cette migration :
 *   1. élargit `device_code` à varchar(64) (place pour le digest hex) ;
 *   2. backfille les lignes existantes : `sha256(upper(device_code))` en hex,
 *      uniquement pour les lignes qui ne sont PAS déjà un digest 64-hex
 *      (garde d'idempotence — un re-run ne re-hache pas les digests).
 *
 * `sha256(bytea)` / `convert_to()` / `encode()` sont du PostgreSQL core
 * (≥ 11), pas d'extension pgcrypto requise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('attendance_kiosks')) {
            return;
        }

        $schema = resolveTableSchema('attendance_kiosks');
        if ($schema === null) {
            return;
        }

        DB::statement(
            "ALTER TABLE {$schema}.attendance_kiosks ALTER COLUMN device_code TYPE varchar(64)"
        );

        DB::statement(
            "UPDATE {$schema}.attendance_kiosks
                SET device_code = encode(sha256(convert_to(upper(device_code), 'UTF8')), 'hex')
              WHERE device_code !~ '^[0-9a-f]{64}$'"
        );
    }

    public function down(): void
    {
        // Irréversible : le code en clair n'est plus stocké (hachage
        // déterministe à sens unique). Pour un rollback, re-provisionner
        // les kiosques concernés (nouveau code + nouveau sync_token).
    }
};
