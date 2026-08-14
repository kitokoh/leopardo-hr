<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2216 — secret d'authentification par device ZKTeco.
 *
 * Colonne `sync_token_hash` sur `zkteco_devices` (même pattern que les
 * kiosques `attendance_kiosks.sync_token_hash`, #1716) : le token brut est
 * généré à l'enregistrement, renvoyé UNE SEULE fois en clair, puis seul le
 * hash est stocké. Les endpoints publics `heartbeat`/`sync-attendance`
 * exigent l'en-tête `X-Device-Token` (Hash::check) → plus d'injection de
 * présences sans authentification. Additif, idempotent (garde
 * `schemaHasColumn` F-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('zkteco_devices');
        if ($schema === null || schemaHasColumn('zkteco_devices', 'sync_token_hash')) {
            return;
        }

        Schema::table("{$schema}.zkteco_devices", function (Blueprint $table): void {
            $table->string('sync_token_hash', 255)->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('zkteco_devices');
        if ($schema === null || ! schemaHasColumn('zkteco_devices', 'sync_token_hash')) {
            return;
        }

        Schema::table("{$schema}.zkteco_devices", function (Blueprint $table): void {
            $table->dropColumn('sync_token_hash');
        });
    }
};
