<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7553 — rôles internes de la plateforme.
 *
 * `super_admins` ne portait aucun rôle : tout compte de la table était
 * implicitement omniscient, ce qui interdisait de déléguer le support, la
 * finance ou les opérations à un collaborateur interne.
 *
 * Le défaut `super_admin` préserve strictement les comptes existants (et les
 * schémas de test reconstruits sans migration) : la colonne est additive et
 * non destructive.
 *
 * Idempotence (Render rejoue des migrations sur des bases déjà migrées) :
 * garde `hasColumn` + `DO $$ ... $$` pour la contrainte CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('super_admins') && ! Schema::hasColumn('super_admins', 'platform_role')) {
            Schema::table('super_admins', function (Blueprint $table): void {
                $table->string('platform_role', 32)->default('super_admin')->after('status');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // Le défaut est aussi posé côté base : un INSERT hors Eloquent
            // (seeder, script d'exploitation) doit produire un super admin.
            DB::statement("ALTER TABLE super_admins ALTER COLUMN platform_role SET DEFAULT 'super_admin'");

            // Les rôles inconnus doivent être refusés par la base, pas
            // seulement par la validation HTTP (défense en profondeur).
            DB::statement(<<<'SQL'
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'super_admins_platform_role_check'
                    ) THEN
                        ALTER TABLE super_admins
                            ADD CONSTRAINT super_admins_platform_role_check
                            CHECK (platform_role IN ('super_admin', 'admin', 'support', 'finance', 'ops', 'marketing'));
                    END IF;
                END
                $$;
            SQL);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('super_admins') || ! Schema::hasColumn('super_admins', 'platform_role')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE super_admins DROP CONSTRAINT IF EXISTS super_admins_platform_role_check');
        }

        Schema::table('super_admins', function (Blueprint $table): void {
            $table->dropColumn('platform_role');
        });
    }
};
