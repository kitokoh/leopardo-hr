<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 0002 — Table publique : companies
 *
 * Schéma : public
 * Dépendances : plans (0001)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET search_path TO public");

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('sector', 100);
            $table->char('country', 2);                             // ISO 3166-1 alpha-2 (DZ, MA, FR, TR...)
            $table->string('city', 100);
            $table->text('address')->nullable();
            $table->string('email', 150)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->unsignedInteger('plan_id')->references('id')->on('plans');
            $table->string('schema_name', 60)->unique();            // ex: 'company_a1b2c3d4'
            $table->enum('tenancy_type', ['schema', 'shared'])->default('shared');
            $table->enum('status', ['active', 'trial', 'suspended', 'expired'])->default('trial');
            $table->date('subscription_start');
            $table->date('subscription_end');
            $table->char('language', 2)->default('fr');             // Langue par défaut de l'entreprise
            $table->string('timezone', 50)->default('Africa/Algiers');
            $table->char('currency', 3)->default('DZD');
            $table->text('notes')->nullable();                      // Note interne Super Admin uniquement
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('status');
            $table->index('plan_id');
            $table->index('subscription_end');
            $table->index('tenancy_type');
        });

        DB::statement("COMMENT ON COLUMN companies.schema_name IS 'Nom du schéma PostgreSQL dédié (mode Enterprise). En mode shared: pointe vers shared_tenants'");
        DB::statement("COMMENT ON COLUMN companies.notes IS 'Note interne — visible uniquement par le Super Admin'");
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
