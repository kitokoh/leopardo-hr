<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8544 (TRAVEL-OS) — trace des mises à disposition du billet.
 *
 * Chaque mise à disposition du PDF (première émission puis réimpressions) est
 * journalisée : opérateur, canal, numéro de réimpression. Table TENANT-FIRST,
 * sans FK (convention du module : cf. `vtc_driver_positions`), réentrante et
 * `down()` non destructif pour le reste du schéma.
 *
 * Journal PUR : réimprimer un billet ne modifie JAMAIS son statut — ce n'est ni
 * un check-in ni une consommation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_ticket_print_events')) {
            $this->comment();

            return;
        }

        Schema::create('travel_ticket_print_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('channel', 32)->default('api');
            $table->unsignedInteger('reprint_number')->default(0);
            $table->boolean('is_reprint')->default(false);

            $table->timestamps();

            $table->index(['company_id', 'ticket_id'], 'travel_ticket_print_events_company_ticket_idx');
            $table->index(['company_id', 'created_at'], 'travel_ticket_print_events_company_created_idx');
        });

        $this->comment();
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_ticket_print_events');
    }

    /**
     * Le libell\u00e9 contient une apostrophe : on construit donc la requ\u00eate par
     * concat\u00e9nation de deux litt\u00e9raux (`STDIN` sans \u00e9chappement).
     */
    private function comment(): void
    {
        DB::statement(
            'COMMENT ON TABLE travel_ticket_print_events IS '
            ."'Trace des mises a disposition du PDF du billet : emission (reprint_number=0) '"
            ."'puis reimpressions (1, 2, ...). La reimpression ne modifie jamais le statut du billet.'"
        );
    }
};
