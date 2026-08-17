<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sécurité #2216 — Authentification des devices ZKTeco.
 *
 * Ajoute `sync_token_hash` sur `zkteco_devices` : chaque device reçoit un
 * token (retourné une seule fois à l'enregistrement, stocké hashé). Les
 * endpoints publics `/zkteco/heartbeat` et `/zkteco/sync-attendance` exigent
 * ce token (en-tête `X-Device-Token`) — plus aucune écriture de pointage sans
 * authentification (fraude de paie, cf. #2216).
 *
 * La colonne est nullable : les devices existants restent enregistrés mais
 * leurs syncs sont rejetés (401 DEVICE_TOKEN_NOT_SET) jusqu'à rotation du
 * token via `POST /zkteco/devices/{id}/regenerate-token`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('zkteco_devices') && ! schemaHasColumn('zkteco_devices', 'sync_token_hash')) {
            Schema::table('zkteco_devices', function (Blueprint $table): void {
                $table->string('sync_token_hash', 255)->nullable()->after('serial_number');
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('zkteco_devices') && schemaHasColumn('zkteco_devices', 'sync_token_hash')) {
            Schema::table('zkteco_devices', function (Blueprint $table): void {
                $table->dropColumn('sync_token_hash');
            });
        }
    }
};
