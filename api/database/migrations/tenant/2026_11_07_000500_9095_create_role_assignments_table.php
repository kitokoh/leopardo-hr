<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9095 (IAM-L5) — rôles personnalisés de tenant.
 *
 * ## Le constat
 *
 * Il n'existe AUCUNE table `roles`/`permissions` : les rôles sont des enums
 * figés (`employees.role`, `employees.manager_role`, `PlatformRole`). Un admin
 * de tenant ne peut donc pas créer « Caissier » ou « Chef de rang » sans une
 * migration qui étend un `CHECK`. Ce n'est pas une faille (fail-closed), c'est
 * une **limite produit** — constat verrouillé par
 * `IdentityAccessControlGapsTest::test_no_custom_role_table_exists`.
 *
 * ## Ce que cette table ajoute
 *
 * Une ligne = UN rôle personnalisé d'UN tenant, portant un jeu de permissions
 * **borné** par le registre fermé `DelegablePermission`. Elle est lue **APRÈS**
 * les enums : `effectivePermissions = enumPermissions(role, manager_role) ∪
 * roleAssignments(active)`. Les gates existants ne sont pas réécrits.
 *
 * ## Isolation
 *
 * `company_id` (uuid indexé) porte le tenant, **aucune FK vers
 * `public.companies`** (conventions migrations tenant §2.6, même doctrine que
 * `employee_module_grants` #7761). Migration réentrante (`schemaTableExists`,
 * garde #1613) et `down()` complet — **additive** : elle ne défait que ce
 * qu'elle a créé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('role_assignments')) {
            return;
        }

        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // Nom lisible du rôle (« Caissier », « Chef de rang »).
            $table->string('name', 80);

            // Registre FERMÉ `DelegablePermission` — jamais une chaîne libre.
            $table->jsonb('permissions');

            // Verticale optionnelle : un rôle de verticale est borné à sa
            // verticale (scénario #11 de la matrice IAM).
            $table->string('vertical', 40)->nullable();

            // Qui a créé le rôle (traçabilité du geste, et base du plafond).
            $table->unsignedInteger('created_by_employee_id')->nullable();

            // Révocation : une ligne révoquée n'est plus lue. On ne supprime
            // pas — l'historique du geste reste auditable.
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // Un nom de rôle est unique par tenant (le PUT remplace).
            $table->unique(['company_id', 'name'], 'role_assignments_company_name_unique');

            // « Quels rôles actifs pour CE tenant ? » — la question du moteur.
            $table->index(['company_id', 'revoked_at'], 'role_assignments_company_active_idx');

            $table->foreign('created_by_employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
