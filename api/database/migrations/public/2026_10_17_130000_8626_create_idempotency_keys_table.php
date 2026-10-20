<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOS-O-05 / #8626 — idempotence des écritures PUBLIQUES (sans session).
 *
 * Constat d'audit (gap matrix O-05) : `POST /trial/signup` est une écriture
 * PUBLIQUE — aucun `Authorization`. Le middleware `IdempotencyMiddleware`
 * (#5277) ne couvre que les requêtes authentifiées : sa clé de cache est
 * scopée par le token (`sha1(Authorization)`), donc une requête anonyme n'est
 * jamais dédupliquée. Un double clic ou un retry réseau sur l'inscription
 * créait donc deux `company_requests` et deux OTP pour la même intention.
 *
 * Cette table porte la clé d'idempotence CLIENTE (`Idempotency-Key`) pour les
 * écritures publiques : la première réponse 2xx est mémorisée puis rejouée à
 * l'identique. La contrainte unique `(scope, idempotency_key)` est la garantie
 * de non-duplication — le contrôle applicatif seul resterait une course.
 *
 * `request_hash` (sha256 du corps) empêche la réutilisation d'une clé pour une
 * intention DIFFÉRENTE : même clé + corps différent → 422, jamais un rejeu
 * mensonger de la réponse d'une autre intention.
 *
 * Migration additive, idempotente et rejouable.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (schemaTableExists('idempotency_keys')) {
            return;
        }

        Schema::create('public.idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 60);
            $table->string('idempotency_key', 255);
            $table->string('request_hash', 64);
            // in_progress = une requête détient la clé ; completed = réponse mémorisée.
            $table->string('status', 20)->default('in_progress');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key'], 'idempotency_keys_scope_key_unique');
            $table->index('expires_at', 'idempotency_keys_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.idempotency_keys');
    }
};
