<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0111 — features (registre des fonctionnalités API)
 *
 * OBJECTIF :
 * - Créer la table features pour le système de synchronisation mobile-API
 * - Maintenir l'inventaire centralisé de toutes les fonctionnalités API
 * - Support du versioning et de la compatibilité mobile
 * - Gestion des permissions et métadonnées pour génération d'interface mobile
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();         // NULL en mode schema isolé

            // ── Identification de la fonctionnalité ─────────────────────────
            $table->string('key', 100)->unique();                   // Identifiant unique de la fonctionnalité
            $table->string('title', 200);                           // Titre affiché dans l'interface
            $table->text('description');                            // Description détaillée

            // ── Configuration API ───────────────────────────────────────────
            $table->string('endpoint', 500);                        // URL de l'endpoint API
            $table->jsonb('http_methods');                          // Méthodes HTTP supportées ['GET', 'POST', etc.]
            $table->jsonb('parameters');                            // Schéma des paramètres d'entrée
            $table->jsonb('response_schema');                       // Schéma de la réponse API

            // ── Sécurité et permissions ─────────────────────────────────────
            $table->jsonb('permissions');                           // Permissions requises ['employees.view', etc.]

            // ── Compatibilité mobile ────────────────────────────────────────
            $table->string('mobile_version_min', 20);               // Version mobile minimale requise
            $table->string('mobile_version_max', 20)->nullable();   // Version mobile maximale supportée
            $table->string('api_version', 20);                      // Version API de la fonctionnalité

            // ── État et métadonnées ─────────────────────────────────────────
            $table->enum('status', ['active', 'deprecated', 'removed'])->default('active');
            $table->jsonb('metadata');                              // Métadonnées pour génération UI mobile

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // ── Index pour performance ──────────────────────────────────────
            $table->index('status');
            $table->index('api_version');
            $table->index('mobile_version_min');
            $table->index(['company_id', 'status']);
            $table->index(['status', 'api_version']);
            $table->unique(['company_id', 'key']);                  // Clé unique par entreprise
        });

        // ── Commentaires pour documentation ─────────────────────────────────
        if (config('database.default') === 'pgsql') {
            DB::statement("COMMENT ON TABLE features IS 'Registre centralisé des fonctionnalités API pour synchronisation mobile'");
            DB::statement("COMMENT ON COLUMN features.key IS 'Identifiant unique de la fonctionnalité (ex: employee_management)'");
            DB::statement("COMMENT ON COLUMN features.endpoint IS 'URL de l''endpoint API (ex: /api/v1/employees)'");
            DB::statement("COMMENT ON COLUMN features.http_methods IS 'Méthodes HTTP supportées au format JSON array'");
            DB::statement("COMMENT ON COLUMN features.parameters IS 'Schéma des paramètres d''entrée au format JSON'");
            DB::statement("COMMENT ON COLUMN features.response_schema IS 'Schéma de la réponse API au format JSON'");
            DB::statement("COMMENT ON COLUMN features.permissions IS 'Permissions requises au format JSON array'");
            DB::statement("COMMENT ON COLUMN features.mobile_version_min IS 'Version mobile minimale requise (ex: 1.0.0)'");
            DB::statement("COMMENT ON COLUMN features.mobile_version_max IS 'Version mobile maximale supportée (NULL = pas de limite)'");
            DB::statement("COMMENT ON COLUMN features.api_version IS 'Version API de la fonctionnalité (ex: 1.2.0)'");
            DB::statement("COMMENT ON COLUMN features.status IS 'État: active (disponible), deprecated (obsolète), removed (supprimée)'");
            DB::statement("COMMENT ON COLUMN features.metadata IS 'Métadonnées pour génération UI mobile (ui_type, form_schema, etc.)'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
