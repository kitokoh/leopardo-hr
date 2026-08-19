<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5120 — Ajoute `punch_methods` (jsonb nullable) sur `zkteco_devices`.
 *
 * Valeurs autorisées : 'fingerprint' | 'face' | 'card'.
 * null ou [] = toutes les méthodes autorisées (rétro-compatibilité).
 * Un index GIN optionnel est ajouté si le driver est PostgreSQL
 * (silencieusement ignoré sinon).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('zkteco_devices', 'punch_methods')) {
            Schema::table('zkteco_devices', function (Blueprint $table): void {
                $table->json('punch_methods')->nullable()->after('capabilities')
                    ->comment('Méthodes de pointage autorisées : fingerprint|face|card. null = toutes.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('zkteco_devices', 'punch_methods')) {
            Schema::table('zkteco_devices', function (Blueprint $table): void {
                $table->dropColumn('punch_methods');
            });
        }
    }
};
