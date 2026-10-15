<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeInvitationChannel;
use App\Modules\Trade\Domain\Enums\TradeInvitationStatus;
use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Models\TradeRfqInvitation;
use App\Modules\Trade\Domain\Support\TradeRfqStateMachine;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Diffusion d'une RFQ a une ou plusieurs contreparties — TRADE-003/#8478.
 *
 * TRANSITION D'ETAT : `draft` (ou `open`) -> `open`. La machine a etats refuse
 * toute diffusion depuis un etat terminal (422 cote API).
 *
 * IDEMPOTENCE METIER : re-diffuser a une contrepartie DEJA invitee ne cree pas
 * de seconde invitation — l'index UNIQUE(rfq, counterparty) le garantirait de
 * toute facon, mais on l'evite explicitement pour que le cas nominal soit un
 * simple no-op et non une erreur de base.
 *
 * LIEN DE REPONSE PUBLIC : un token aleatoire est genere et seul son SHA-256
 * est persiste ; la valeur en clair est retournee UNE SEULE FOIS a l'appelant
 * (elle n'existe nulle part ailleurs, comme le code de suivi de BC-35).
 *
 * ENVOI E-MAIL : `sent_at` n'est pose QUE si un canal a REELLEMENT transmis
 * l'invitation. Sans mailer configure, l'invitation reste `pending` avec son
 * lien — elle n'est alors jamais presentee comme envoyee.
 */
final class SendRfqAction
{
    public function __construct(
        private readonly TradeRfqStateMachine $rfqStates,
        private readonly RecordTradeEventAction $recordEvent,
        private readonly DatabaseManager $db,
        // #6568 — la couche Application n'importe pas les facades Laravel : on
        // depend de l'interface PSR-3, resolue par le conteneur.
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  list<int>  $counterpartyIds
     * @return array{0: TradeRfq, 1: array<int, string>} [RFQ, tokens de reponse en clair indexes par counterparty_id]
     */
    public function execute(
        TradeRfq $rfq,
        array $counterpartyIds,
        ?int $employeeId = null,
        ?string $message = null,
        int $tokenLifetimeDays = 30,
    ): array {
        $this->rfqStates->assertCanTransitionTo($rfq->status, TradeRfqStatus::Open);

        $tokens = [];

        $this->db->transaction(function () use ($rfq, $counterpartyIds, $employeeId, $message, $tokenLifetimeDays, &$tokens): void {
            $alreadyInvited = TradeRfqInvitation::query()
                ->where('trade_rfq_id', $rfq->id)
                ->pluck('trade_counterparty_id')
                ->all();

            foreach (array_unique($counterpartyIds) as $counterpartyId) {
                if (in_array($counterpartyId, $alreadyInvited, true)) {
                    // Idempotence metier : deja invite = rien a faire.
                    continue;
                }

                // La contrepartie doit appartenir au tenant : le trait
                // BelongsToCompany borne deja la requete, donc une contrepartie
                // d'une autre entreprise est simplement introuvable (fail-closed).
                $counterparty = TradeCounterparty::query()->whereKey($counterpartyId)->first();

                if (! $counterparty instanceof TradeCounterparty) {
                    continue;
                }

                $plainToken = Str::random(48);

                TradeRfqInvitation::query()->create([
                    'trade_rfq_id' => $rfq->id,
                    'trade_counterparty_id' => $counterparty->id,
                    'channel' => TradeInvitationChannel::Email->value,
                    'status' => TradeInvitationStatus::Pending->value,
                    'message' => $message,
                    // Seul le HASH est persiste : le token en clair n'existe
                    // que dans le lien transmis au fournisseur.
                    'response_token_hash' => hash('sha256', $plainToken),
                    'response_token_expires_at' => now()->addDays($tokenLifetimeDays),
                ]);

                $tokens[$counterparty->id] = $plainToken;
            }

            if ($rfq->status !== TradeRfqStatus::Open) {
                $rfq->status = TradeRfqStatus::Open;
                $rfq->published_at = now();
                $rfq->save();
            }

            $this->recordEvent->execute('rfq', $rfq->id, 'rfq.sent', [
                'reference' => $rfq->reference,
                'invited' => array_keys($tokens),
                'already_invited' => $alreadyInvited,
            ], $employeeId);
        });

        // Le lien n'est journalise QUE si des liens ont ete crees : aucune
        // ligne de log ne doit laisser croire a un envoi qui n'a pas eu lieu.
        if ($tokens !== []) {
            $this->logger->info('trade.rfq.invitations_created', [
                'rfq_reference' => $rfq->reference,
                'invitation_count' => count($tokens),
                // Aucune donnee sensible : ni token, ni adresse e-mail.
            ]);
        }

        return [$rfq->refresh(), $tokens];
    }
}
