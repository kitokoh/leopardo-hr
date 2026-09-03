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
        if (! Schema::hasTable('delivery_notifications')) {
            Schema::create('delivery_notifications', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('delivery_id');
                $table->string('event_type', 30);
                $table->string('channel', 20)->default('whatsapp'); // whatsapp | sms
                $table->string('recipient_phone', 40);
                $table->string('template_key', 80);
                $table->string('status', 20)->default('pending'); // pending | sent | failed | skipped
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->json('payload')->nullable();
                $table->timestamp('sent_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'delivery_id'], 'delivery_notifications_company_delivery_idx');
                $table->index(['company_id', 'status'], 'delivery_notifications_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_notifications IS 'Notifications destinataire (DELIVERY-206/#6290) - outbox tenant-scoped, templates versionnes, opt-out effectif, retry borne, aucune PII dans les logs.';");
        }

        if (! Schema::hasTable('delivery_recipient_opt_outs')) {
            Schema::create('delivery_recipient_opt_outs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('phone', 40);
                $table->timestamps();

                $table->unique(['company_id', 'phone'], 'delivery_recipient_opt_outs_company_phone_unique');
            });

            DB::statement("COMMENT ON TABLE delivery_recipient_opt_outs IS 'Opt-out destinataire (DELIVERY-206/#6290) - arrete les notifications planifiees, pas les deja envoyees.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_recipient_opt_outs');
        Schema::dropIfExists('delivery_notifications');
    }
};
