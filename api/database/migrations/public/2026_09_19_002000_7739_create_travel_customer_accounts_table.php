<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7739 — Comptes clients grand public du site marketplace travel (épic #7736).
 *
 * Table PLATEFORME (schéma public, pas de company_id) : un client de la place
 * de marché n'appartient à AUCUN tenant — il réserve chez plusieurs agences.
 * Même pattern pré-tenant que `marketing_leads` (PA2-MKT-007) et
 * `acquisition_funnel_events` (#7496). Le rattachement aux réservations se
 * fait côté tenant via `travel_bookings.customer_account_id` (colonne nue,
 * sans FK cross-schéma — constitution §II).
 *
 * `email_verified_at` : posé à la création quand le compte revendique des
 * réservations existantes par e-mail (rattachement « à la création », #7739) ;
 * une vérification par lien est un lot ultérieur de l'épic.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_customer_accounts')) {
            return;
        }

        Schema::create('travel_customer_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 255)->unique();
            $table->string('phone', 40)->nullable();
            // Hash bcrypt/argon (Hash::make) — jamais de mot de passe en clair.
            $table->string('password', 255);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_customer_accounts');
    }
};
