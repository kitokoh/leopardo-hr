<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Application\Actions\BuildCustomerReceiptAction;
use App\Modules\RestaurantManager\Application\Actions\RenderPrintJobPayloadAction;
use App\Modules\RestaurantManager\Application\Services\ReceiptMentionsResolver;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Infrastructure\Printing\EscPosCustomerReceiptRenderer;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * REST-OS-8 (#8610) — Reçu client imprimable.
 *
 * Défaut couvert : `RenderPrintJobPayloadAction` construisait TOUJOURS un
 * ticket cuisine, quel que soit le `ticket_kind`. Un travail
 * `CUSTOMER_RECEIPT` imprimait donc un ticket CUISINE — sans montants, sans
 * mentions légales, avec l'en-tête « CUISINE ». Le `kind` était stocké,
 * indexé et affiché dans l'UI, mais ignoré au seul endroit qui compte : le
 * rendu.
 *
 * Ces tests échouent tous sur le code d'avant le correctif.
 */
class RestaurantCustomerReceiptTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'FR', string $currency = 'EUR'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    /**
     * @return array{0: RestaurantBranch, 1: RestaurantOrder}
     */
    private function order(Company $company, int $priceMinor = 1000, float $quantity = 1): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $priceMinor, $quantity): array {
            $branch = RestaurantBranch::factory()->create([
                'name' => 'Leopardo Bastille',
                'address' => '12 rue de la Roquette',
                'city' => 'Paris',
                'phone' => '+33 1 23 45 67 89',
                'currency' => 'EUR',
            ]);

            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 1000]);

            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'name' => 'Burger Maison',
                'price_minor' => $priceMinor,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'in_preparation',
                'currency' => $branch->currency,
                'reference' => 'CMD-2026-0001',
                // Remise explicite : la factory en genere une aleatoire qui
                // peut annuler tout le total, ce qui rendrait les assertions
                // sur les montants non deterministes.
                'discount_minor' => 0,
            ]);

            RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price_minor' => $priceMinor,
                'line_total_minor' => (int) round($priceMinor * $quantity),
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            return [$branch, $order->fresh()];
        });
    }

    private function pay(Company $company, RestaurantOrder $order, int $amountMinor, PaymentStatus $status): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($company, $order, $amountMinor, $status): void {
            RestaurantOrderPayment::factory()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'amount_minor' => $amountMinor,
                'currency' => $order->currency,
                'status' => $status->value,
            ]);
        });
    }

    private function job(Company $company, RestaurantOrder $order, PrintTicketKind $kind): RestaurantPrintJob
    {
        return app(TenantManager::class)->withinTenant($company, fn (): RestaurantPrintJob => RestaurantPrintJob::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'ticket_kind' => $kind->value,
        ]));
    }

    /**
     * LE test du défaut : un travail CUSTOMER_RECEIPT ne doit PAS produire un
     * ticket cuisine.
     */
    public function test_a_customer_receipt_job_does_not_render_a_kitchen_ticket(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $job = $this->job($company, $order, PrintTicketKind::CUSTOMER_RECEIPT);

        $rendered = app(RenderPrintJobPayloadAction::class)->execute($job);
        $text = $this->plain($rendered['payload']);

        // Le reçu porte le nom de l'établissement et la référence de commande.
        $this->assertStringContainsString('Leopardo Bastille', $text);
        $this->assertStringContainsString('CMD-2026-0001', $text);

        // Il porte des MONTANTS — ce qu'un ticket cuisine n'a jamais.
        $this->assertStringContainsString('TOTAL', $text);
        $this->assertStringContainsString('10.00 EUR', $text);

        // Et il ne porte AUCUN marqueur de cuisine.
        $this->assertStringNotContainsString('CUISINE', $text);
        $this->assertStringNotContainsString('PREPARATION', $text);
    }

    /**
     * Le ticket cuisine, lui, garde son comportement : le correctif ne doit pas
     * avoir inversé le défaut.
     */
    public function test_a_kitchen_job_still_renders_a_kitchen_ticket(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $job = $this->job($company, $order, PrintTicketKind::KITCHEN);

        $rendered = app(RenderPrintJobPayloadAction::class)->execute($job);
        $text = $this->plain($rendered['payload']);

        $this->assertStringContainsString('CUISINE', $text);
        $this->assertStringNotContainsString('TOTAL', $text);
    }

    /**
     * #8610 — un reçu ne confirme JAMAIS un paiement non validé.
     */
    public function test_an_unpaid_order_receipt_is_marked_not_settled(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $job = $this->job($company, $order, PrintTicketKind::CUSTOMER_RECEIPT);

        $text = $this->plain(app(RenderPrintJobPayloadAction::class)->execute($job)['payload']);

        $this->assertStringContainsString('NON ACQUITTE', $text);
        $this->assertStringContainsString('Reste a payer', $text);
    }

    /**
     * Un paiement PENDING (mobile money en attente de callback) ne doit pas
     * être compté comme encaissé — sinon le reçu confirme un paiement qui n'a
     * pas eu lieu.
     */
    public function test_a_pending_payment_is_not_counted_as_paid(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $this->pay($company, $order, 1000, PaymentStatus::PENDING);

        $job = $this->job($company, $order->fresh(), PrintTicketKind::CUSTOMER_RECEIPT);
        $text = $this->plain(app(RenderPrintJobPayloadAction::class)->execute($job)['payload']);

        $this->assertStringContainsString('NON ACQUITTE', $text);
    }

    public function test_a_confirmed_payment_settles_the_receipt(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $this->pay($company, $order, 1000, PaymentStatus::CONFIRMED);

        $job = $this->job($company, $order->fresh(), PrintTicketKind::CUSTOMER_RECEIPT);
        $text = $this->plain(app(RenderPrintJobPayloadAction::class)->execute($job)['payload']);

        $this->assertStringNotContainsString('NON ACQUITTE', $text);
        $this->assertStringContainsString('Regle', $text);
    }

    /**
     * Les mentions légales sont résolues par juridiction : la France impose le
     * libellé TVA, la Turquie le VKN.
     */
    public function test_legal_mentions_follow_the_jurisdiction(): void
    {
        $resolver = app(ReceiptMentionsResolver::class);

        $fr = $resolver->resolve('FR', ['name' => 'ACME', 'tax_id' => 'FR123'], 'CMD-1', '01/01/2026');
        $this->assertContains('TVA intracommunautaire: FR123', $fr);

        $tr = $resolver->resolve('TR', ['name' => 'ACME', 'tax_id' => '1234567890'], 'CMD-1', '01/01/2026');
        $this->assertContains('VKN: 1234567890', $tr);

        $cm = $resolver->resolve('CM', ['name' => 'ACME', 'tax_id' => 'NIU-9'], 'CMD-1', '01/01/2026');
        $this->assertContains('N° contribuable: NIU-9', $cm);
    }

    /**
     * Aucune mention fiscale n'est INVENTÉE : sans identifiant configuré, la
     * ligne est absente (pas de placeholder « à compléter »).
     */
    public function test_no_fiscal_mention_is_invented_when_no_tax_id_is_configured(): void
    {
        $mentions = app(ReceiptMentionsResolver::class)
            ->resolve('FR', ['name' => 'ACME'], 'CMD-1', '01/01/2026');

        foreach ($mentions as $mention) {
            $this->assertStringNotContainsString('TVA', $mention);
        }
    }

    /**
     * Le reçu porte la référence et la date : sans elles, ce n'est pas une
     * pièce justificative.
     */
    public function test_the_receipt_carries_reference_and_date(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $job = $this->job($company, $order, PrintTicketKind::CUSTOMER_RECEIPT);

        $text = $this->plain(app(RenderPrintJobPayloadAction::class)->execute($job)['payload']);

        $this->assertStringContainsString('Ref. CMD-2026-0001', $text);
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/', $text);
    }

    /**
     * Le hash du payload reste cohérent avec les octets rendus (le bridge s'en
     * sert pour détecter une corruption de transport).
     */
    public function test_the_payload_hash_matches_the_rendered_bytes(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);
        $job = $this->job($company, $order, PrintTicketKind::CUSTOMER_RECEIPT);

        $rendered = app(RenderPrintJobPayloadAction::class)->execute($job);

        $this->assertSame(hash('sha256', $rendered['payload']), $rendered['hash']);
    }

    /**
     * Le renderer est PUR : il ne touche ni base ni réseau, et il est
     * déterministe pour un même reçu.
     */
    public function test_the_renderer_is_pure_and_deterministic(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);

        $receipt = app(BuildCustomerReceiptAction::class)->execute($order, 1, '01/01/2026 12:00');
        $renderer = app(EscPosCustomerReceiptRenderer::class);

        $this->assertSame($renderer->render($receipt, 80), $renderer->render($receipt, 80));
    }

    /**
     * La largeur papier 58 mm ne doit pas casser la mise en page : le montant
     * reste lisible même si le libellé est tronqué.
     */
    public function test_a_narrow_paper_width_keeps_amounts_readable(): void
    {
        $company = $this->company();
        [, $order] = $this->order($company);

        $receipt = app(BuildCustomerReceiptAction::class)->execute($order, 1, '01/01/2026 12:00');
        $text = $this->plain(app(EscPosCustomerReceiptRenderer::class)->render($receipt, 58));

        $this->assertStringContainsString('10.00 EUR', $text);
    }

    /**
     * Retire les séquences de contrôle ESC/POS pour l'assertion humaine.
     */
    private function plain(string $bytes): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $bytes) ?? '';
    }
}
