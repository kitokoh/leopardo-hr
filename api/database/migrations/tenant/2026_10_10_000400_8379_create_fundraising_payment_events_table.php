<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verticale FUNDRAISING — table `fundraising_payment_events`.
 *
 * Journal d'idempotence et d'audit des webhooks providers (spec §3.4) :
 * un `event_id` déjà présent ⇒ webhook acquitté sans retraitement (200) —
 * aucune double comptabilisation possible.
 *
 * Sans FK (conventions migrations tenant §2.6). Idempotente + down()
 * limité à ce fichier (leçon #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fundraising_payment_events')) {
            Schema::create('fundraising_payment_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->nullable();
                $table->string('provider', 30);
                $table->string('event_id', 190);
                $table->unsignedBigInteger('contribution_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'event_id'], 'fundraising_payment_events_provider_event_unique');
            });

            DB::statement("COMMENT ON TABLE fundraising_payment_events IS 'Journal idempotence/audit des webhooks paiement (verticale FUNDRAISING) : (provider, event_id) unique — double livraison acquittee sans retraitement.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_payment_events');
    }
};
