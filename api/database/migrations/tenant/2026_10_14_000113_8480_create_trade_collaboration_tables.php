<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8480 (TRADE-005) — BC-36 TRADE : tables tenant `trade_messages`
 * + `trade_documents` + `trade_events`.
 *
 * CONVERSATION rattachee au DOSSIER (`trade_messages`) : auteur interne,
 * direction (`internal|outbound|inbound`), corps, pieces jointes sur le disque
 * prive. L'envoi e-mail REEL n'a lieu que si le mailer est configure — sinon
 * le message est journalise sans pretendre avoir ete envoye.
 *
 * DOCUMENTS (`trade_documents`) : pro forma, facture commerciale, packing
 * list, connaissement (B/L), LTA, certificat d'origine, certificat de
 * conformite, certificat d'assurance, declaration douaniere, contrat. Disque
 * `private` par defaut, telechargement apres autorisation, suppression
 * logique. L'acces croise entre tenants repond 404 (anti-enumeration).
 *
 * TIMELINE (`trade_events`) : journal metier du dossier (`rfq.sent`,
 * `quote.accepted`, `order.shipped`…) ecrit dans la MEME transaction que la
 * transition — l'historique ne peut pas mentir par desynchronisation.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.5
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_messages')) {
            Schema::create('trade_messages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trade_deal_id')->index();

                $table->string('direction', 20)->default('internal');
                $table->text('body');
                $table->json('attachments')->nullable();

                $table->unsignedBigInteger('employee_id')->nullable();

                $table->string('sent_via', 40)->nullable();
                $table->timestamp('sent_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'trade_deal_id', 'created_at'], 'trade_messages_company_deal_date_idx');
            });

            DB::statement("COMMENT ON TABLE trade_messages IS 'Messages de dossier Trade - conversation rattachee a la transaction, pieces jointes disque prive, envoi email seulement si configure (TRADE-005/#8480).';");
        }

        if (! schemaTableExists('trade_documents')) {
            Schema::create('trade_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trade_deal_id')->nullable();
                $table->unsignedBigInteger('trade_order_id')->nullable();
                $table->unsignedBigInteger('trade_quote_id')->nullable();
                $table->unsignedBigInteger('trade_rfq_id')->nullable();

                $table->string('type', 40);
                $table->string('title', 200);

                $table->string('disk', 40)->default('private');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                $table->string('status', 20)->default('draft');

                $table->unsignedBigInteger('uploaded_by_employee_id')->nullable();

                $table->timestamp('shared_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'type'], 'trade_documents_company_type_idx');
                $table->index(['company_id', 'trade_deal_id'], 'trade_documents_company_deal_idx');
            });

            DB::statement("COMMENT ON TABLE trade_documents IS 'Documents Trade (pro forma, facture, packing list, B/L, certificats...) - disque prive, telechargement autorise, 404 anti-enumeration cross-tenant (TRADE-005/#8480).';");
        }

        if (! schemaTableExists('trade_events')) {
            Schema::create('trade_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('subject_type', 40);
                $table->unsignedBigInteger('subject_id');

                $table->string('type', 60);
                $table->json('payload')->nullable();

                $table->unsignedBigInteger('employee_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'subject_type', 'subject_id', 'created_at'], 'trade_events_company_subject_date_idx');
            });

            DB::statement("COMMENT ON TABLE trade_events IS 'Timeline metier Trade - ecrite dans la meme transaction que la transition, employee_id null = systeme (TRADE-005/#8480).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_events');
        Schema::dropIfExists('trade_documents');
        Schema::dropIfExists('trade_messages');
    }
};
