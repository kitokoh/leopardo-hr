<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9095 (IAM-L5) — ATTRIBUTION d'un rôle personnalisé à un collaborateur.
 *
 * ## Le manque que cette table comble
 *
 * `role_assignments` (migration `2026_11_07_000500_9095`) porte la DÉFINITION
 * d'un rôle personnalisé, mais rien ne reliait un rôle à un collaborateur :
 * `RoleDefinitionController` ne savait créer, modifier et révoquer des rôles
 * que « dans le vide ». L'écran « Rôles personnalisés » (#9518) affiche un
 * compteur de porteurs (`holder_count`) — sans table d'attribution, ce
 * compteur ne peut pas exister, et un rôle créé ne sert à personne.
 *
 * ## Isolation
 *
 * `company_id` (uuid indexé) porte le tenant, aucune FK vers
 * `public.companies` (conventions migrations tenant §2.6, même doctrine que
 * `employee_module_grants` #7761). Migration **additive** et `down()`
 * complet : elle ne défait que ce qu'elle a créé.
 *
 * ## Ce que cette table n'est PAS
 *
 * Elle n'ajoute AUCUNE permission à un gate. Les rôles personnalisés sont lus
 * APRÈS les enums (`EmployeeResource::capabilities()`) : cette table porte la
 * donnée d'attribution, l'enforcement reste l'étape ultérieure du plan IAM-L5.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('employee_role_assignments')) {
            return;
        }

        Schema::create('employee_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id');
            $table->unsignedBigInteger('role_assignment_id');

            // Révocation : une ligne révoquée n'est plus lue. On ne supprime
            // pas — l'historique du geste reste auditable (même doctrine que
            // `role_assignments.revoked_at`).
            $table->timestamp('revoked_at')->nullable();

            $table->unsignedInteger('assigned_by_employee_id')->nullable();

            $table->timestamps();

            // Un collaborateur ne porte qu'une fois un rôle donné (le
            // ré-attribuer après révocation crée une NOUVELLE ligne).
            $table->index(
                ['company_id', 'employee_id', 'revoked_at'],
                'employee_role_assignments_employee_active_idx'
            );

            // « Combien de porteurs ce rôle a-t-il ? » — la question de l'écran.
            $table->index(
                ['role_assignment_id', 'revoked_at'],
                'employee_role_assignments_role_active_idx'
            );

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();

            $table->foreign('role_assignment_id')
                ->references('id')
                ->on('role_assignments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_role_assignments');
    }
};
