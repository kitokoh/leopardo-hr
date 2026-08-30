<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-907 (#6110) — Extension de `travel_payments` aux annonces payantes.
 *
 * `booking_id` devient nullable (un paiement référence soit une réservation,
 * soit une annonce via `advert_id`) — additif, réentrant, aucun renommage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_payments')) {
            DB::statement('ALTER TABLE travel_payments ALTER COLUMN booking_id DROP NOT NULL');
            DB::statement('ALTER TABLE travel_payments ADD COLUMN IF NOT EXISTS advert_id bigint NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS travel_payments_company_advert_idx ON travel_payments (company_id, advert_id)');
            DB::statement("COMMENT ON COLUMN travel_payments.advert_id IS 'Annonce payée (TRAVEL-907/#6110) — booking_id nullable depuis ce lot.'");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('travel_payments')) {
            DB::statement('DROP INDEX IF EXISTS travel_payments_company_advert_idx');
            DB::statement('ALTER TABLE travel_payments DROP COLUMN IF EXISTS advert_id');
        }
    }
};
