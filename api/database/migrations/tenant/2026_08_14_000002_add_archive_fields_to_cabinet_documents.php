<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme FOCUS — F-09/#1548 (issue #1817) : archivage automatique des
 * bulletins PDF dans le Cabinet employé après clôture.
 *
 * Colonnes additives sur `cabinet_documents` :
 *   - `read_only`      — document verrouillé (un employé ne peut pas le
 *                        supprimer ; 403 côté API) ;
 *   - `document_type`  — nature du document (« payslip », …) ;
 *   - `pay_slip_id`    — bulletin source de l'archivage (idempotence :
 *                        un bulletin archivé une seule fois).
 *
 * Migration additive et idempotente (pattern schema-aware du module Payroll,
 * cf. 2026_08_14_000001_add_has_attendance_data_to_pay_slips.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('cabinet_documents');

        if ($schema === null) {
            return;
        }

        if (! schemaHasColumn('cabinet_documents', 'read_only')) {
            Schema::table("{$schema}.cabinet_documents", function (Blueprint $table): void {
                $table->boolean('read_only')->default(false)->after('notes');
            });
        }

        if (! schemaHasColumn('cabinet_documents', 'document_type')) {
            Schema::table("{$schema}.cabinet_documents", function (Blueprint $table): void {
                $table->string('document_type', 30)->nullable()->after('read_only');
            });
        }

        if (! schemaHasColumn('cabinet_documents', 'pay_slip_id')) {
            Schema::table("{$schema}.cabinet_documents", function (Blueprint $table): void {
                $table->unsignedBigInteger('pay_slip_id')->nullable()->after('document_type');

                $table->foreign('pay_slip_id')
                    ->references('id')
                    ->on('pay_slips')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('cabinet_documents');

        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.cabinet_documents", function (Blueprint $table): void {
            if (schemaHasColumn('cabinet_documents', 'pay_slip_id')) {
                $table->dropForeign(['pay_slip_id']);
            }
            foreach (['pay_slip_id', 'document_type', 'read_only'] as $column) {
                if (schemaHasColumn('cabinet_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
