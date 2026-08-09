<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('employees');

        Schema::table("{$schema}.employees", function (Blueprint $table): void {
            if (! schemaHasColumn('employees', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }
            if (! schemaHasColumn('employees', 'preferred_name')) {
                $table->string('preferred_name', 100)->nullable()->after('last_name');
            }
            if (! schemaHasColumn('employees', 'personal_email')) {
                $table->string('personal_email', 150)->nullable()->after('email');
            }
            if (! schemaHasColumn('employees', 'place_of_birth')) {
                $table->string('place_of_birth', 120)->nullable()->after('date_of_birth');
            }
            if (! schemaHasColumn('employees', 'marital_status')) {
                $table->string('marital_status', 30)->nullable()->after('nationality');
            }
            if (! schemaHasColumn('employees', 'address_line')) {
                $table->string('address_line', 255)->nullable()->after('phone');
            }
            if (! schemaHasColumn('employees', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('address_line');
            }
            if (! schemaHasColumn('employees', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 150)->nullable()->after('postal_code');
            }
            if (! schemaHasColumn('employees', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
            }
            if (! schemaHasColumn('employees', 'emergency_contact_relation')) {
                $table->string('emergency_contact_relation', 60)->nullable()->after('emergency_contact_phone');
            }
            if (! schemaHasColumn('employees', 'biometric_face_enabled')) {
                $table->boolean('biometric_face_enabled')->default(false)->after('photo_path');
            }
            if (! schemaHasColumn('employees', 'biometric_fingerprint_enabled')) {
                $table->boolean('biometric_fingerprint_enabled')->default(false)->after('biometric_face_enabled');
            }
            if (! schemaHasColumn('employees', 'biometric_face_reference_path')) {
                $table->string('biometric_face_reference_path', 255)->nullable()->after('biometric_fingerprint_enabled');
            }
            if (! schemaHasColumn('employees', 'biometric_fingerprint_reference_path')) {
                $table->string('biometric_fingerprint_reference_path', 255)->nullable()->after('biometric_face_reference_path');
            }
            if (! schemaHasColumn('employees', 'biometric_consent_at')) {
                $table->timestampTz('biometric_consent_at')->nullable()->after('biometric_fingerprint_reference_path');
            }
            if (! schemaHasColumn('employees', 'invitation_accepted_at')) {
                $table->timestampTz('invitation_accepted_at')->nullable()->after('biometric_consent_at');
            }
        });
    }

    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('employees');

        Schema::table("{$schema}.employees", function (Blueprint $table): void {
            foreach ([
                'middle_name',
                'preferred_name',
                'personal_email',
                'place_of_birth',
                'marital_status',
                'address_line',
                'postal_code',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relation',
                'biometric_face_enabled',
                'biometric_fingerprint_enabled',
                'biometric_face_reference_path',
                'biometric_fingerprint_reference_path',
                'biometric_consent_at',
                'invitation_accepted_at',
            ] as $column) {
                if (schemaHasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
