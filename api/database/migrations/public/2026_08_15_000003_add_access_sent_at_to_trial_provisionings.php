<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2629 — horodatage de l'envoi du magic link d'accès démo
 * (ProvisionDemoTenantJob::issueDemoAccess) sur trial_provisionings.
 * Additive : ne touche pas aux colonnes existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trial_provisionings')) {
            return;
        }

        Schema::table('public.trial_provisionings', function (Blueprint $table): void {
            if (! Schema::hasColumn('public.trial_provisionings', 'access_sent_at')) {
                $table->timestampTz('access_sent_at')->nullable()->after('provisioned_at');
            }
        });
    }

    public function down(): void
    {
        if (schemaTableExists('trial_provisionings')
            && Schema::hasColumn('public.trial_provisionings', 'access_sent_at')) {
            Schema::table('public.trial_provisionings', function (Blueprint $table): void {
                $table->dropColumn('access_sent_at');
            });
        }
    }
};
