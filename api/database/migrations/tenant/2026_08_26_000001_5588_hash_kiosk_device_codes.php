<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issue #5588 (durcissement) : le `device_code` kiosque était stocké en
     * clair (Str::random(10) uppercase). Décision : SHA-256 hex (64 chars) —
     * déterministe donc queryable par la borne qui présente le code dans
     * l'URL (bcrypt ne l'est pas), même philosophie que les tokens ZKTeco
     * (`sync_token_hash`). Le code clair n'existe plus qu'en réponse de
     * création (affichage sur la borne) et dans les logs d'audit du flux.
     *
     * Irréversible par conception : on ne peut pas restorer les codes clairs
     * depuis le hash.
     */
    public function up(): void
    {
        // Schéma résolu via le search_path (convention #1613 / F-17) — même
        // pattern que 2026_04_19_000108 (add_kiosk_sync_fields).
        $schemaKiosks = resolveTableSchema('attendance_kiosks');
        $schemaLogs = resolveTableSchema('attendance_logs');

        Schema::table("{$schemaKiosks}.attendance_kiosks", function (Blueprint $table): void {
            // SHA-256 hex = 64 chars (varchar(40) tronquait/refusait).
            $table->string('device_code', 64)->change();
        });

        Schema::table("{$schemaLogs}.attendance_logs", function (Blueprint $table): void {
            // source_device_code porte le device_code haché (64 chars).
            $table->string('source_device_code', 64)->nullable()->change();
        });

        // Rehash des codes existants (les codes déjà en sha256 hex 64 sont
        // laissés inchangés — idempotent).
        DB::table("{$schemaKiosks}.attendance_kiosks")
            ->select(['id', 'device_code'])
            ->orderBy('id')
            ->chunkById(500, function ($kiosks) use ($schemaKiosks): void {
                foreach ($kiosks as $kiosk) {
                    $code = (string) $kiosk->device_code;
                    if (preg_match('/^[a-f0-9]{64}$/', $code)) {
                        continue;
                    }
                    DB::table("{$schemaKiosks}.attendance_kiosks")
                        ->where('id', $kiosk->id)
                        ->update(['device_code' => hash('sha256', strtoupper($code))]);
                }
            });
    }

    public function down(): void
    {
        // Irréversible (codes clairs perdus par conception).
    }
};
