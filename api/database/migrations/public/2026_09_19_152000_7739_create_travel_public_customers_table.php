<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7739 — Comptes clients grand public de la marketplace voyage.
 *
 * Table CROSS-TENANT du schéma `public` (pattern `users`,
 * migration 2026_05_02_100001) : un client grand public n'appartient à
 * AUCUNE agence — ses réservations restent, elles, dans les tables tenant
 * (`travel_bookings.public_customer_id`, référence PAR VALEUR, aucune FK
 * inter-schémas, pattern #7638).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        if (! Schema::hasTable('travel_public_customers')) {
            Schema::create('travel_public_customers', function (Blueprint $table): void {
                $table->id();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email')->unique();
                $table->string('phone', 40)->nullable();
                // Hash bcrypt — jamais de mot de passe en clair (#7739).
                $table->string('password');
                $table->string('preferred_language', 2)->default('fr');
                $table->string('status', 20)->default('active');
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->integer('failed_login_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::dropIfExists('travel_public_customers');
    }
};
