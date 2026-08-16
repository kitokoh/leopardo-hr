<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #4186 : ApplyAsPartner (Growth) créait des lignes partners vides —
 * name/email/phone/website/commission_rate/employee_id/company_id n'étaient
 * ni colonnes ni fillable → perte silencieuse des candidatures partenaires.
 * Ajout additif, nullable (les lignes existantes restent valides).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'name')) {
                $table->string('name', 150)->nullable()->after('type');
            }
            if (! Schema::hasColumn('partners', 'email')) {
                $table->string('email', 150)->nullable()->after('name');
            }
            if (! Schema::hasColumn('partners', 'phone')) {
                $table->string('phone', 40)->nullable()->after('email');
            }
            if (! Schema::hasColumn('partners', 'website')) {
                $table->string('website', 255)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('partners', 'company_id')) {
                $table->uuid('company_id')->nullable()->after('website')->index();
            }
            if (! Schema::hasColumn('partners', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('partners', 'commission_rate')) {
                // Taux demandé par le candidat (decimal 0.10 = 10 %) — distinct de
                // default_commission_rate (en basis points, défini à l'approbation).
                $table->decimal('commission_rate', 6, 4)->nullable()->after('employee_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table) {
            $columns = array_filter([
                'name', 'email', 'phone', 'website', 'company_id', 'employee_id', 'commission_rate',
            ], fn (string $col): bool => Schema::hasColumn('partners', $col));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
