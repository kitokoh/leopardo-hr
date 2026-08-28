<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5709 — CRM V0 : table tenant-scoped des pipelines commerciaux.
 *
 * Un pipeline est un jeu d'étapes (stages) ordonné, propre à une entreprise.
 * Les opportunités (#5709) s'y rattachent par `pipeline_id` (uuid, sans FK —
 * découplage tant que le module CRM complet n'est pas livré).
 *
 * Isolation tenant : `company_id` uuid NON nullable ; unicité du nom PAR
 * entreprise (`crm_pipelines_company_name_unique`) — deux entreprises
 * peuvent avoir un pipeline « Ventes » sans collision.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 120);
                // Pipeline par défaut de l'entreprise (un seul possible).
                $table->boolean('is_default')->default(false);
                // Étapes ordonnées : json [{"key":"prospection","label":"Prospection","probability":10}, ...]
                $table->json('stages');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'name'], 'crm_pipelines_company_name_unique');
                $table->index(['company_id', 'is_default'], 'crm_pipelines_company_default_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_pipelines');
    }
};
