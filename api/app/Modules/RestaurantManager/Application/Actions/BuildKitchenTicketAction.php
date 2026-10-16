<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicket;
use App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicketLine;
use Illuminate\Support\Carbon;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Construit le ticket imprimable
 * (VO {@see KitchenTicket}) d'une commande.
 *
 * Pure mise en forme : aucune persistance, aucun accès matériel. Ne conserve
 * que les lignes ACTIVES (les lignes annulées ne sont pas préparées) ; les
 * quantités sont formatées sans zéro inutile (2.000 → « 2 », 1.500 → « 1.5 »).
 * Les instructions de ligne proviennent de la note redigée de la commande
 * lorsque `line_index` est disponible (aucune PII : `note_redacted`).
 */
final class BuildKitchenTicketAction
{
    public function execute(
        RestaurantOrder $order,
        int $revision = 1,
        ?string $printedAt = null,
    ): KitchenTicket {
        $order->loadMissing(['items.product', 'branch', 'table']);

        $lines = [];

        foreach ($order->items as $item) {
            if ($item->status !== OrderItemStatus::ACTIVE) {
                continue;
            }

            $lines[] = new KitchenTicketLine(
                name: (string) ($item->product?->name ?? 'Article #'.$item->product_id),
                quantity: $this->formatQuantity((float) $item->quantity),
            );
        }

        return new KitchenTicket(
            kind: PrintTicketKind::KITCHEN,
            orderId: (int) $order->id,
            reference: (string) $order->reference,
            branchId: (int) $order->branch_id,
            branchName: (string) ($order->branch?->name ?? 'Restaurant'),
            orderType: $order->order_type,
            tableLabel: $order->table?->label,
            covers: $order->covers !== null ? (int) $order->covers : null,
            lines: $lines,
            revision: $revision,
            printedAt: $printedAt ?? Carbon::now()->format('d/m/Y H:i'),
            note: $order->note_redacted !== null && $order->note_redacted !== ''
                ? (string) $order->note_redacted
                : null,
        );
    }

    private function formatQuantity(float $quantity): string
    {
        if (floor($quantity) === $quantity) {
            return (string) (int) $quantity;
        }

        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
