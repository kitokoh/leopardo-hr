<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8478 (TRADE-003) — BC-36 TRADE : table tenant `trade_rfq_invitations`.
 *
 * DIFFUSION d'une RFQ a une contrepartie (fournisseur). Chaque invitation
 * porte son propre etat (`pending|sent|viewed|responded|declined|expired`) :
 * une RFQ diffusee a 5 fournisseurs a 5 etats independants.
 *
 * `response_token_hash` : lien de reponse PUBLIC sans authentification
 * (pattern `cabinet/shared/{token}`) — le token est stocke HACHE (SHA-256),
 * jamais en clair, avec expiration. Le prestataire repond ainsi a une cotation
 * sans creer de compte, et l'acces est strictement borne a l'invitation
 * (aucune donnee du dossier au-dela de ce qui est diffuse).
 *
 * `channel` : `internal_note|email`. En v1 l'envoi e-mail REEL n'est effectue
 * que si le mailer est configure — sinon l'invitation reste journalisee
 * (`sent_at` non pose) sans jamais pretendre avoir ete envoyee.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('trade_rfq_invitations')) {
            Schema::create('trade_rfq_invitations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trade_rfq_id')->index();
                $table->unsignedBigInteger('trade_counterparty_id')->index();

                $table->string('channel', 20)->default('email');
                $table->string('status', 20)->default('pending');

                $table->text('message')->nullable();

                // Lien de reponse sans auth : token hache, usage borne, expiration.
                $table->string('response_token_hash', 64)->nullable();
                $table->timestamp('response_token_expires_at')->nullable();
                $table->timestamp('response_token_used_at')->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('responded_at')->nullable();

                $table->timestamps();

                $table->unique(['trade_rfq_id', 'trade_counterparty_id'], 'trade_rfq_invitations_rfq_counterparty_unique');
                $table->index(['company_id', 'status'], 'trade_rfq_invitations_company_status_idx');
                $table->index('response_token_hash', 'trade_rfq_invitations_token_idx');
            });

            DB::statement("COMMENT ON TABLE trade_rfq_invitations IS 'Invitations fournisseurs d une RFQ Trade - etat par invitation, lien de reponse public a token hashe SHA-256 borne et expirant (TRADE-003/#8478).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_rfq_invitations');
    }
};
