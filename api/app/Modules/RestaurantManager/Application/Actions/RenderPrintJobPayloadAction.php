<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Contracts\CustomerReceiptRendererInterface;
use App\Modules\RestaurantManager\Domain\Contracts\OrderTicketRendererInterface;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Fournit le flux ESC/POS prêt à
 * imprimer pour un travail réclamé par le bridge local.
 *
 * Le rendu reste PUR (aucune I/O) : le bridge récupère les octets et les remet
 * lui-même à l'imprimante thermique (USB / réseau 9100 / CUPS) — c'est le seul
 * composant qui peut réellement confirmer l'impression.
 *
 * **Défaut corrigé (#8610, REST-OS-8)** — cette action construisait TOUJOURS un
 * ticket cuisine, quel que soit le `ticket_kind` du travail. Un travail
 * `CUSTOMER_RECEIPT` imprimait donc un ticket CUISINE : en-tête « CUISINE »,
 * quantités sans prix, aucune mention légale, aucun total. Le `kind` était
 * pourtant stocké, indexé et affiché dans l'UI — il était simplement ignoré au
 * moment du rendu, c'est-à-dire au seul endroit qui compte.
 *
 * Le dispatch est désormais EXPLICITE et **fail-closed** : un `kind` sans
 * renderer associé lève une exception plutôt que de retomber silencieusement
 * sur le ticket cuisine. Un repli par défaut reproduirait exactement le bug
 * qu'on corrige — mieux vaut un travail en échec visible qu'un document faux
 * remis au client.
 */
final class RenderPrintJobPayloadAction
{
    public function __construct(
        private readonly BuildKitchenTicketAction $buildKitchenTicket,
        private readonly BuildCustomerReceiptAction $buildCustomerReceipt,
        private readonly OrderTicketRendererInterface $kitchenRenderer,
        private readonly CustomerReceiptRendererInterface $receiptRenderer,
    ) {}

    /**
     * @return array{payload: string, hash: string}
     */
    public function execute(RestaurantPrintJob $job, ?string $paperWidth = null): array
    {
        $job->loadMissing('order');
        $order = $job->order;

        if ($order === null) {
            return ['payload' => '', 'hash' => ''];
        }

        $width = $this->paperWidth($paperWidth);
        $revision = (int) $job->revision;

        $payload = match ($job->ticket_kind) {
            PrintTicketKind::KITCHEN => $this->kitchenRenderer->render(
                $this->buildKitchenTicket->execute($order, $revision),
                $width,
            ),
            PrintTicketKind::CUSTOMER_RECEIPT => $this->receiptRenderer->render(
                $this->buildCustomerReceipt->execute($order, $revision),
                $width,
            ),
        };

        return ['payload' => $payload, 'hash' => hash('sha256', $payload)];
    }

    private function paperWidth(?string $override): int
    {
        if ($override !== null && is_numeric($override)) {
            return (int) $override;
        }

        $configured = config('restaurantmanager.printing.paper_width', 80);

        return is_numeric($configured) ? (int) $configured : 80;
    }
}
