<?php

declare(strict_types=1);

namespace Tests\Unit\Restaurant;

use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicket;
use App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicketLine;
use App\Modules\RestaurantManager\Infrastructure\Printing\EscPosOrderTicketRenderer;
use Tests\TestCase;

/**
 * REST-OS-1 (Mission Leopardo Restaurant OS) — Rendu ESC/POS du ticket
 * cuisine.
 *
 * Test PUR : aucun accès base, aucun matériel. Vérifie que le flux contient
 * les informations nécessaires à la préparation (table, référence, articles,
 * quantités, instructions) et les séquences ESC/POS attendues (init, gras,
 * coupe).
 */
class EscPosOrderTicketRendererTest extends TestCase
{
    private function ticket(): KitchenTicket
    {
        return new KitchenTicket(
            kind: PrintTicketKind::KITCHEN,
            orderId: 42,
            reference: 'RST-ABC1234567',
            branchId: 7,
            branchName: 'Leopardo Centre',
            orderType: OrderType::DINE_IN,
            tableLabel: 'T12',
            covers: 4,
            lines: [
                new KitchenTicketLine(name: 'Burger Royal', quantity: '2', note: 'Sans oignon'),
                new KitchenTicketLine(name: 'Frites', quantity: '1'),
                new KitchenTicketLine(name: 'Salade', quantity: '1', isCancelled: true),
            ],
            revision: 3,
            printedAt: '2026-10-16 12:30',
            note: 'Client pressé',
        );
    }

    public function test_it_renders_a_kitchen_ticket_with_expected_content(): void
    {
        $out = (new EscPosOrderTicketRenderer)->render($this->ticket(), 80);

        $this->assertStringContainsString('CUISINE', $out);
        $this->assertStringContainsString('Leopardo Centre', $out);
        $this->assertStringContainsString('RST-ABC1234567', $out);
        $this->assertStringContainsString('Table T12', $out);
        $this->assertStringContainsString('4 couv.', $out);
        $this->assertStringContainsString('2 x Burger Royal', $out);
        $this->assertStringContainsString('> Sans oignon', $out);
        $this->assertStringContainsString('ANNUL 1 x Salade', $out);
        $this->assertStringContainsString('Note: Client pressé', $out);
        $this->assertStringContainsString('Rev. 3', $out);
    }

    public function test_it_emits_escpos_init_bold_and_cut_sequences(): void
    {
        $out = (new EscPosOrderTicketRenderer)->render($this->ticket(), 80);

        // ESC @ (init), ESC E (gras on/off), GS V (coupe).
        $this->assertStringContainsString("\x1B@", $out);
        $this->assertStringContainsString("\x1BE\x01", $out);
        $this->assertStringContainsString("\x1BE\x00", $out);
        $this->assertStringContainsString("\x1DV\x01", $out);
    }

    public function test_it_truncates_long_lines_to_the_paper_width(): void
    {
        $long = new KitchenTicket(
            kind: PrintTicketKind::KITCHEN,
            orderId: 1,
            reference: 'RST-LONG',
            branchId: 1,
            branchName: 'Restaurant avec un nom de branche extrêmement long qui dépasse',
            orderType: OrderType::TAKEAWAY,
            tableLabel: null,
            covers: null,
            lines: [new KitchenTicketLine(
                name: 'Plat au nom interminable qui ne tiendra jamais sur une seule ligne de papier thermique',
                quantity: '1',
            )],
            revision: 1,
            printedAt: '2026-10-16 12:30',
        );

        $out = (new EscPosOrderTicketRenderer)->render($long, 58);

        foreach (explode("\n", $out) as $line) {
            // Les séquences ESC/POS restent sur la ligne ; on borne la partie
            // textuelle visible (hors octets de contrôle).
            $visible = preg_replace('/[\x00-\x1F\x7F]/', '', $line) ?? '';
            $this->assertLessThanOrEqual(58, mb_strlen($visible), 'Ligne trop longue pour le papier 58 mm.');
        }
    }

    public function test_58mm_paper_produces_a_narrower_stream_than_80mm(): void
    {
        $renderer = new EscPosOrderTicketRenderer;

        $w58 = $renderer->render($this->ticket(), 58);
        $w80 = $renderer->render($this->ticket(), 80);

        // Les règles de séparation (tirets) diffèrent → le flux 58 mm compte
        // moins de tirets que le 80 mm.
        $this->assertLessThan(substr_count($w80, '-'), substr_count($w58, '-'));
    }
}
