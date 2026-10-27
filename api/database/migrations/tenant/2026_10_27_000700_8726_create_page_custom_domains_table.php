<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8726 — domaines personnalisés des Pages.
 *
 * `host` est unique **globalement** : voir le commentaire de
 * `PageCustomDomain` sur le routage non déterministe. Pas d'index partiel par
 * tenant, pas de « premier à s'inscrire gagne côté applicatif » : la contrainte
 * est en base, elle ne se contourne pas par un chemin d'écriture oublié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedBigInteger('page_id');

            // Host normalisé (ASCII / IDNA), toujours en minuscules.
            $table->string('host', 253);

            $table->string('status', 24)->default('pending_dns');
            $table->string('provider', 40)->default('vercel');
            $table->string('provider_reference', 190)->nullable();

            $table->string('certificate_status', 24)->nullable();
            $table->timestampTz('certificate_expires_at')->nullable();

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('removed_at')->nullable();

            $table->unsignedInteger('check_attempts')->default(0);
            $table->string('last_error', 500)->nullable();

            // Instructions TXT/CNAME à afficher au tenant (jamais modifiées
            // chez lui : on documente ce qu'il doit faire).
            $table->jsonb('dns_instructions')->nullable();

            $table->boolean('is_apex')->default(false);
            $table->boolean('is_wildcard')->default(false);

            $table->timestampsTz();

            $table->foreign('page_id')
                ->references('id')
                ->on('pages')
                ->cascadeOnDelete();

            // 🔴 Unicité globale du host : deux tenants ne peuvent pas revendiquer
            // le même domaine, même en cas de course.
            $table->unique('host', 'page_custom_domains_host_unique');
            $table->unique(['page_id', 'host'], 'page_custom_domains_page_host_unique');

            $table->index(['company_id', 'status'], 'page_custom_domains_company_status_index');
            $table->index(['status', 'certificate_expires_at'], 'page_custom_domains_serving_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_custom_domains');
    }
};
