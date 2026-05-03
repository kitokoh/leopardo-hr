<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0111 - features (registre des fonctionnalitÃ©s API)
 *
 * OBJECTIF :
 * - CrÃ©er la table features pour le systÃ¨me de synchronisation mobile-API
 * - Maintenir l'inventaire centralisÃ© de toutes les fonctionnalitÃ©s API
 * - Support du versioning et de la compatibilitÃ© mobile
 * - Gestion des permissions et mÃ©tadonnÃ©es pour gÃ©nÃ©ration d'interface mobile
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index(); // NULL en mode schema isolÃ©

            // â”€â”€ Identification de la fonctionnalitÃ© â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $table->string('key', 100)->unique(); // Identifiant unique de la fonctionnalitÃ©
            $table->string('title', 200); // Titre affichÃ© dans l'interface
            $table->text('description'); // Description dÃ©taillÃ©e

            // â”€â”€ Configuration API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $table->string('endpoint', 500); // URL de l'endpoint API
            $table->jsonb('http_methods'); // MÃ©thodes HTTP supportÃ©es ['GET', 'POST', etc.]
            $table->jsonb('parameters'); // SchÃ©ma des paramÃ¨tres d'entrÃ©e
            $table->jsonb('response_schema'); // SchÃ©ma de la rÃ©ponse API

            // â”€â”€ SÃ©curitÃ© et permissions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $table->jsonb('permissions'); // Permissions requises ['employees.view', etc.]

            // â”€â”€ CompatibilitÃ© mobile â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $table->string('mobile_version_min', 20); // Version mobile minimale requise
            $table->string('mobile_version_max', 20)->nullable(); // Version mobile maximale supportÃ©e
            $table->string('api_version', 20); // Version API de la fonctionnalitÃ©

            // â”€â”€ Ã‰tat et mÃ©tadonnÃ©es â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $table->enum('status', ['active', 'deprecated', 'removed'])->default('active');
            $table->jsonb('metadata'); // MÃ©tadonnÃ©es pour gÃ©nÃ©ration UI mobile

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // â”€â”€ Index pour performance â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $table->index('status');
            $table->index('api_version');
            $table->index('mobile_version_min');
            $table->index(['company_id', 'status']);
            $table->index(['status', 'api_version']);
            $table->unique(['company_id', 'key']); // ClÃ© unique par entreprise
        });

        // â”€â”€ Commentaires pour documentation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        DB::statement("COMMENT ON TABLE features IS 'Registre centralisÃ© des fonctionnalitÃ©s API pour synchronisation mobile'");
        DB::statement("COMMENT ON COLUMN features.key IS 'Identifiant unique de la fonctionnalitÃ© (ex: employee_management)'");
        DB::statement("COMMENT ON COLUMN features.endpoint IS 'URL de l''endpoint API (ex: /api/v1/employees)'");
        DB::statement("COMMENT ON COLUMN features.http_methods IS 'MÃ©thodes HTTP supportÃ©es au format JSON array'");
        DB::statement("COMMENT ON COLUMN features.parameters IS 'SchÃ©ma des paramÃ¨tres d''entrÃ©e au format JSON'");
        DB::statement("COMMENT ON COLUMN features.response_schema IS 'SchÃ©ma de la rÃ©ponse API au format JSON'");
        DB::statement("COMMENT ON COLUMN features.permissions IS 'Permissions requises au format JSON array'");
        DB::statement("COMMENT ON COLUMN features.mobile_version_min IS 'Version mobile minimale requise (ex: 1.0.0)'");
        DB::statement("COMMENT ON COLUMN features.mobile_version_max IS 'Version mobile maximale supportÃ©e (NULL = pas de limite)'");
        DB::statement("COMMENT ON COLUMN features.api_version IS 'Version API de la fonctionnalitÃ© (ex: 1.2.0)'");
        DB::statement("COMMENT ON COLUMN features.status IS 'Ã‰tat: active (disponible), deprecated (obsolÃ¨te), removed (supprimÃ©e)'");
        DB::statement("COMMENT ON COLUMN features.metadata IS 'MÃ©tadonnÃ©es pour gÃ©nÃ©ration UI mobile (ui_type, form_schema, etc.)'");
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
