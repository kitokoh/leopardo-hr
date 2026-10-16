<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Contracts\OrderTicketRendererInterface;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Fournit le flux ESC/POS prêt à
 * imprimer pour un travail réclamé par le bridge local.
 *
 * Le rendu reste PUR (aucune I/O) : le bridge récupère les octets et les remet
 * lui-même à l'imprimante thermique (USB / réseau 9100 / CUPS) — c'est le seul
 * composant qui peut réellement confirmer l'impression.
 */
final class RenderPrintJobPayloadAction
{
    public function __construct(
        private readonly BuildKitchenTicketAction $buildTicket,
        private readonly OrderTicketRendererInterface $renderer,
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

        $ticket = $this->buildTicket->execute($order, (int) $job->revision);

        $width = $paperWidth !== null && is_numeric($paperWidth)
            ? (int) $paperWidth
            : (int) config('restaurantmanager.printing.paper_width', 80);

        $payload = $this->renderer->render($ticket, $width);

        return ['payload' => $payload, 'hash' => hash('sha256', $payload)];
    }
}
