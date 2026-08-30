<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_tracking_shares')) {
            Schema::create('delivery_tracking_shares', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('delivery_id');
                $table->string('share_token', 64)->unique();
                $table->timestamp('expires_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'delivery_id'], 'delivery_tracking_shares_company_delivery_idx');
                $table->index(['company_id', 'expires_at'], 'delivery_tracking_shares_company_expires_idx');
            });

            DB::statement('COMMENT ON TABLE delivery_tracking_shares IS \'Liens de suivi publics bornes (DELIVERY-204/#6288) - token 64 chars = credential (pattern AccountingDocumentShare #5225), expiration, anti-enumeration.\';');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tracking_shares');
    }
};
