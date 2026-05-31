<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 60 — Double validation des avances salaire.
 *
 * Ajoute les champs de traçabilité du workflow :
 *   Employé → Manager approuve → Manager déclare paiement → Employé confirme réception
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            // Manager approval
            $table->timestamp('manager_approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('manager_approved_by')->nullable()->after('manager_approved_at');

            // Payment declaration (RH/manager)
            $table->timestamp('payment_declared_at')->nullable()->after('manager_approved_by');
            $table->unsignedBigInteger('payment_declared_by')->nullable()->after('payment_declared_at');
            $table->string('payment_reference')->nullable()->after('payment_declared_by');
            $table->text('payment_note')->nullable()->after('payment_reference');

            // Employee confirmation
            $table->timestamp('employee_confirmed_at')->nullable()->after('payment_note');

            // Fine-grained validation status (does not replace existing `status` field)
            $table->enum('validation_status', [
                'pending',
                'manager_approved',
                'payment_declared',
                'employee_confirmed',
                'rejected',
            ])->default('pending')->after('employee_confirmed_at');
        });

        // Add foreign key constraints
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->foreign('manager_approved_by')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();

            $table->foreign('payment_declared_by')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->dropForeign(['manager_approved_by']);
            $table->dropForeign(['payment_declared_by']);
            $table->dropColumn([
                'manager_approved_at',
                'manager_approved_by',
                'payment_declared_at',
                'payment_declared_by',
                'payment_reference',
                'payment_note',
                'employee_confirmed_at',
                'validation_status',
            ]);
        });
    }
};
