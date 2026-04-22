<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0003 — Résolution dépendance circulaire + employee_devices
 *
 * Cette migration :
 * 1. Ajoute departments.manager_id (FK → employees) — impossible avant la création des employees
 * 2. Crée employee_devices (FCM tokens) — DÉCISION: table séparée, pas JSONB dans employees
 * 3. Crée devices (appareils ZKTeco/QR)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. departments.manager_id — résolution dépendance circulaire ───────
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedInteger('manager_id')->nullable()->after('name');
            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->nullOnDelete();
        });

        // ── 2. employee_devices ────────────────────────────────────────────────
        // DÉCISION ARCHITECTURALE : table séparée (PAS fcm_tokens JSONB dans employees)
        // Raison : scalable multi-device, permet last_seen par appareil, révocation ciblée
        Schema::create('employee_devices', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('device_name', 100);                     // ex: 'iPhone 15 Pro d\'Ahmed'
            $table->text('fcm_token')->unique();                    // Token Firebase Cloud Messaging
            $table->string('platform', 10)->default('android');     // 'android' | 'ios'
            $table->string('app_version', 20)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('employee_id');
            $table->comment('Tokens FCM par appareil. DÉCISION: table séparée (pas JSONB dans employees). Permet multi-device et révocation ciblée');
        });

        // ── 3. devices (appareils ZKTeco / QR) ────────────────────────────────
        Schema::create('devices', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['zkteco', 'qrcode_terminal', 'tablet']);
            $table->string('serial_number', 100)->nullable();
            $table->string('ip_address', 45)->nullable();           // IPv4 ou IPv6
            $table->text('device_token')->nullable();               // Token auth pour API ZKTeco
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('employee_devices');
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn('manager_id');
        });
    }
};
