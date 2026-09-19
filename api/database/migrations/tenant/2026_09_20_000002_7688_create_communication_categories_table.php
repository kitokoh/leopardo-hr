<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7688 (R3 Communication, spec MODULE_COMMUNICATION_EMAIL_IA.md §3.3)
 * — taxonomie de categories d'emails PARAMETRABLE PAR TENANT.
 *
 * Les defauts (`config('communication.classification.default_categories')`)
 * sont materialises paresseusement au premier usage (`is_system = true`,
 * `label` null -> libelle i18n `communication.category_<key>` FR/EN/AR/TR).
 * Le tenant peut renommer (`label`), desactiver (`active`) ou ajouter ses
 * propres categories ; la sortie du LLM est VALIDEE contre les cles actives
 * (jamais de categorie inventee persistee).
 *
 * Isolation : `company_id` uuid indexe, UNIQUE (company_id, key).
 * Migration reentrante (`schemaTableExists`, garde #1613), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('communication_categories')) {
            return;
        }

        Schema::create('communication_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->string('key', 64);
            $table->string('label', 128)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'key'], 'communication_categories_company_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_categories');
    }
};
