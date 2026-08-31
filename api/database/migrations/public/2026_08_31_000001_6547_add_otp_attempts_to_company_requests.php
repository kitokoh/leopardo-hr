<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6547 — anti-brute-force de l'OTP de trial self-service.
 *
 * `POST /trial/verify` n'avait aucun compteur d'échecs par email : un
 * attaquant pouvait brute-forcer le code 6 chiffres en rotant les IP
 * (le throttle 5/15min est keyé IP).
 *
 * - `otp_attempts` : tentatives ratées cumulées sur la demande en attente ;
 * - `otp_locked_until` : verrouillage temporaire de l'email après 5 échecs.
 *
 * Migration additive + idempotente (garde #1962), schéma public.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::table('company_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_requests', 'otp_attempts')) {
                $table->unsignedSmallInteger('otp_attempts')->default(0)->after('verification_token');
            }
            if (! Schema::hasColumn('company_requests', 'otp_locked_until')) {
                $table->timestampTz('otp_locked_until')->nullable()->after('otp_attempts');
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::table('company_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('company_requests', 'otp_locked_until')) {
                $table->dropColumn('otp_locked_until');
            }
            if (Schema::hasColumn('company_requests', 'otp_attempts')) {
                $table->dropColumn('otp_attempts');
            }
        });
    }
};
