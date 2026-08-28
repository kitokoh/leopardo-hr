<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5729 — Jobs d'export CRM (tenants).
 *
 * `crm_export_jobs` : export asynchrone (CSV) des données CRM tenant-scoped
 * avec progression, URL/accès expirant (`expires_at`), colonnes allowlistées
 * (snapshot dans `columns`), filtres snapshot (`filters`), audit et cleanup.
 *
 * Conventions : uuid PK, `company_id` non nullable indexé, timestamps,
 * garde schemaTableExists() (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_export_jobs')) {
            Schema::create('crm_export_jobs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('user_id')->nullable()->index();
                $table->string('entity', 30);                       // accounts|contacts|leads|opportunities|activities|tasks
                $table->string('format', 10)->default('csv');
                $table->json('filters')->nullable();                // snapshot des filtres demandés
                $table->json('columns')->nullable();                // snapshot des colonnes allowlistées
                $table->string('status', 20)->default('queued');    // queued|processing|completed|failed|expired
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('file_path', 500)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'crm_exports_company_status_index');
                $table->index(['company_id', 'created_at'], 'crm_exports_company_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_export_jobs');
    }
};
