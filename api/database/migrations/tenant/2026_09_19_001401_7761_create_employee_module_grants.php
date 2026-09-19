<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — grants
 * de MODULES composables par collaborateur.
 *
 * Aujourd'hui l'accès aux modules de l'espace client repose sur UN seul
 * `manager_role` par collaborateur (`api.manager:<roles>`) : impossible de
 * déléguer « marketing + comptabilité + tickets » à la même personne sans
 * inventer un rôle. Cette table porte la composition : une ligne = UN module
 * (`module_key`, registre fermé `App\Core\Auth\Domain\Enums\ModuleKey`)
 * accordé à UN collaborateur par le responsable du tenant.
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, **aucune FK vers
 * `public.companies`** (conventions migrations tenant §2.6, même doctrine que
 * `employee_resource_assignments` #7598) ; les FK internes pointent vers
 * `employees` (table tenant, même schéma). Migration réentrante
 * (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('employee_module_grants')) {
            return;
        }

        Schema::create('employee_module_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedInteger('employee_id');
            $table->string('module_key', 40);

            // Qui a accordé le module (traçabilité du geste).
            $table->unsignedInteger('granted_by_employee_id')->nullable();

            $table->timestamps();

            // Un module n'est accordé qu'une fois par collaborateur : le PUT
            // remplace le jeu complet, il ne duplique jamais une ligne.
            $table->unique(
                ['company_id', 'employee_id', 'module_key'],
                'employee_module_grants_unique'
            );

            // « Qui a CE module ? » — la question du middleware d'enforcement.
            $table->index(['company_id', 'module_key'], 'employee_module_grants_module_idx');

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('granted_by_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_module_grants');
    }
};
