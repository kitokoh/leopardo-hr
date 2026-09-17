<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7598 (R1 de l'épique #7597) — socle « accès aux ressources ».
 *
 * Aujourd'hui les ressources d'un tenant (restaurants, véhicules, caméras,
 * sites) ne sont **pas** un objet d'autorisation : toutes les policies se
 * contentent de « même `company_id` + rôle », donc un responsable ne peut pas
 * donner à un collaborateur l'accès à **ses** restaurants seulement.
 *
 * Cette table porte cette relation : `employee_resource_assignments`.
 * - `resource_type` : clé du registre `config/resource_types.php` (pas de FK —
 *   la ressource vit dans une autre table, potentiellement un autre module) ;
 * - `resource_id` : identifiant de la ressource **dans son propre domaine** ;
 * - `access_level` : `view` < `operate` < `manage` (ordre défini dans
 *   `EmployeeResourceAssignment::ACCESS_LEVELS`).
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, **aucune FK vers
 * `public.companies`** (conventions migrations tenant §2.6) ; les FK internes
 * pointent vers `employees` (table tenant, même schéma). Migration réentrante
 * (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('employee_resource_assignments')) {
            return;
        }

        Schema::create('employee_resource_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedInteger('employee_id');
            $table->string('resource_type', 40);
            $table->unsignedInteger('resource_id');
            $table->enum('access_level', ['view', 'operate', 'manage'])->default('view');

            // Qui a posé l'accès (traçabilité du geste, pas seulement de la ligne).
            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();

            // Une seule ligne par couple (collaborateur, ressource) : le niveau
            // se met à jour, il ne se duplique pas.
            $table->unique(
                ['employee_id', 'resource_type', 'resource_id'],
                'employee_resource_assignments_unique'
            );

            // « Qui a accès à CETTE ressource ? » — la question des policies (R2+).
            $table->index(['company_id', 'resource_type', 'resource_id'], 'employee_resource_assignments_resource_idx');
            // « À quoi CE collaborateur a-t-il accès ? » — la question des helpers et de l'API.
            $table->index(['company_id', 'employee_id', 'resource_type'], 'employee_resource_assignments_employee_idx');

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_resource_assignments');
    }
};
