<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 (BC-17 RETAIL, epic Leopardo Marche) — Jetons d'authentification
 * des comptes acheteurs marketplace.
 *
 * Jeton OPAQUE cote client, seul le hash SHA-256 (64 hex) est persiste —
 * Sanctum n'est pas utilise ici : ses personal access tokens vivent dans
 * les schemas tenants (Employee) alors que les acheteurs sont des comptes
 * PLATEFORME (schema public, cross-tenant). Expiration cote serveur
 * (`expires_at`), revocation au logout (suppression de la ligne).
 *
 * Sans FK (buyer_id simple index — regle migrations sans foreign keys),
 * idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_buyer_tokens')) {
            return;
        }

        Schema::create('marketplace_buyer_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('buyer_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_buyer_tokens');
    }
};
