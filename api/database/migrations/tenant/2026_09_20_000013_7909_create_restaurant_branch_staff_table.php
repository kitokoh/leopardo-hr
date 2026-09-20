<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7909 - RestaurantManager : affectation d'employes aux succursales.
 *
 * `restaurant_branch_staff` : pivot staff <-> branche (RH reste proprietaire
 * des employes, reference PAR VALEUR comme travel_staff_assignments) :
 * - `branch_id`   : succursale (restaurant_branches) du MEME tenant ;
 * - `employee_id` : employe RH du MEME tenant (controle cross-tenant strict
 *   dans RestaurantBranchStaffService::assign(), 422 sinon) ;
 * - `role`        : role libre optionnel (serveur, cuisinier, gerant...) ;
 * - `assigned_at` : date d'affectation ;
 * - softDeletes   : le retrait est un soft delete (historique conserve) ;
 * - unicite (company_id, branch_id, employee_id) : un employe n'est affecte
 *   qu'une fois par succursale (409 au niveau API sur doublon actif).
 *
 * Table tenant-scoped (`company_id` uuid non nullable, tenant-first), sans FK
 * (pattern Travel moderne, cf. 2026_08_30_001519_6166) : colonnes simples +
 * index nommes. Migration idempotente (garde `schemaTableExists`), `down()`
 * complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_branch_staff')) {
            Schema::create('restaurant_branch_staff', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('role', 80)->nullable();
                $table->timestamp('assigned_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'branch_id'], 'restaurant_branch_staff_company_branch_idx');
                $table->unique(['company_id', 'branch_id', 'employee_id'], 'restaurant_branch_staff_company_branch_employee_unique');
            });

            DB::statement("COMMENT ON TABLE restaurant_branch_staff IS 'Affectations employes <-> succursales restaurant - un employe par (tenant, branche), retrait en soft delete (#7909).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_branch_staff');
    }
};
