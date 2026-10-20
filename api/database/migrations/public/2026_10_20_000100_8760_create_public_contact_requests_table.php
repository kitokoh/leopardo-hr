<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8760 — Demandes de contact issues de la passerelle publique (Leopardo Public).
 *
 * Table PLATEFORME (schéma public, PAS de `company_id`) : une demande de
 * contact naît d'un visiteur qui n'appartient à aucun tenant — il peut
 * contacter plusieurs professionnels dans la même session. C'est exactement
 * la constitution des tables pré-tenant déjà présentes (`travel_customer_accounts`
 * #7739, `marketing_leads`, `acquisition_funnel_events` #7496).
 *
 * ⚠️ Ne JAMAIS stocker `company_id` ici : la fuite cross-tenant viendrait
 * précisément de cette colonne lisible sans contexte tenant. Le rattachement
 * au tenant destinataire se fait par le slug public du profil
 * (`target_type` + `target_slug`), résolu au moment de la rédaction dans le
 * contexte du tenant ; `resolved_company_id` n'est qu'une trace d'audit
 * d'acheminement, jamais un critère de lecture publique.
 *
 * Minimalisation RGPD : nom/email/message + consentement horodaté
 * (`consent_at`), IP hashée (`ip_hash`, jamais en clair), et conservation
 * bornée (`retention_until`) pour permettre la purge.
 *
 * Idempotence : `idempotency_key` unique — un retry réseau du navigateur
 * renvoie la MÊME demande au lieu d'en créer une seconde (même garantie que
 * `IdempotentGuestWrite` côté tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public_contact_requests')) {
            return;
        }

        Schema::create('public_contact_requests', function (Blueprint $table): void {
            $table->id();

            // Profil public visé (le slug EST l'identifiant public).
            $table->string('target_type', 40);
            $table->string('target_slug', 160);

            // Expéditeur — données minimisées, aucune donnée RH/tenant.
            $table->string('sender_name', 120);
            $table->string('sender_email', 255);
            $table->string('sender_phone', 40)->nullable();
            $table->text('message');

            // Consentement explicite + conservation bornée (RGPD).
            $table->timestampTz('consent_at');
            $table->timestampTz('retention_until')->nullable();

            // Anti-doublon navigateur : unique GLOBAL (le visiteur n'a pas de tenant).
            $table->string('idempotency_key', 80)->unique();

            // IP hashée (HMAC applicatif) — jamais l'IP en clair.
            $table->string('ip_hash', 64)->nullable();

            // Trace d'acheminement interne, JAMAIS un critère de lecture publique.
            $table->uuid('resolved_company_id')->nullable();

            // Cycle de vie côté Business OS : new | handled | closed.
            $table->string('status', 20)->default('new');

            $table->timestampsTz();

            $table->index(['target_type', 'target_slug'], 'public_contact_requests_target_index');
            $table->index('status', 'public_contact_requests_status_index');
            $table->index('created_at', 'public_contact_requests_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_contact_requests');
    }
};
