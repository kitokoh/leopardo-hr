<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Application\Services\ReceiptMentionsResolver;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\ValueObjects\CustomerReceipt;
use App\Modules\RestaurantManager\Domain\ValueObjects\CustomerReceiptLine;
use Illuminate\Support\Carbon;

/**
 * REST-OS-8 (#8610) — Construit le reçu client imprimable d'une commande.
 *
 * Défaut corrigé : `RenderPrintJobPayloadAction` construisait TOUJOURS un
 * {@see \App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicket} via
 * `BuildKitchenTicketAction`, quel que soit le `ticket_kind` du travail. Un
 * travail `CUSTOMER_RECEIPT` imprimait donc un ticket CUISINE — sans montants,
 * sans mentions légales, avec l'en-tête « CUISINE ». Le `kind` était stocké,
 * indexé et affiché dans l'UI, mais ignoré au moment du rendu.
 *
 * Décisions de conception :
 *
 * 1. **Les montants viennent du serveur, jamais du client.** Le sous-total, la
 *    TVA, la remise et le total sont recalculés par {@see BillCalculator} — la
 *    source de vérité unique de la verticale — et non relus depuis les colonnes
 *    dénormalisées de la commande. Un reçu est une pièce comptable : il doit
 *    refléter le calcul réel, pas un cache potentiellement désynchronisé.
 *
 * 2. **Aucun paiement non validé n'est confirmé.** Seuls les paiements au
 *    statut `CONFIRMED` comptent dans `paidMinor`. Un reçu émis sur une
 *    commande non réglée porte `paidMinor = 0` et le renderer imprime
 *    « NON ACQUITTÉ » — l'issue l'exige explicitement.
 *
 * 3. **Les lignes annulées sont exclues** (comme pour le ticket cuisine) : le
 *    client ne paie pas ce qui a été annulé.
 *
 * 4. **Aucune PII** : la note de commande n'est pas reprise sur le reçu client
 *    (elle est déjà `note_redacted` côté cuisine, et un reçu circule).
 */
final class BuildCustomerReceiptAction
{
    public function __construct(
        private readonly BillCalculator $bill,
        private readonly ReceiptMentionsResolver $mentions,
    ) {}

    public function execute(
        RestaurantOrder $order,
        int $revision = 1,
        ?string $printedAt = null,
    ): CustomerReceipt {
        $order->loadMissing(['items.product', 'branch', 'table', 'payments']);

        $lines = [];

        foreach ($order->items as $item) {
            if ($item->status !== OrderItemStatus::ACTIVE) {
                continue;
            }

            $lines[] = new CustomerReceiptLine(
                name: (string) ($item->product->name ?? 'Article #'.$item->product_id),
                quantity: $this->formatQuantity((float) $item->quantity),
                unitPriceMinor: (int) $item->unit_price_minor,
                lineTotalMinor: (int) $item->line_total_minor,
            );
        }

        // Remise : on reprend celle RÉELLEMENT appliquée à la commande (elle a
        // été validée serveur à l'encaissement) et on la borne au sous-total,
        // exactement comme BillCalculator.
        $totals = $this->bill->calculate($order, (int) $order->discount_minor);

        $paid = $this->paidMinor($order);

        $branch = $order->branch;
        $country = $this->countryOf($order);

        $printedAt = $printedAt ?? Carbon::now()->format('d/m/Y H:i');

        return new CustomerReceipt(
            kind: PrintTicketKind::CUSTOMER_RECEIPT,
            orderId: (int) $order->id,
            reference: (string) $order->reference,
            branchId: (int) $order->branch_id,
            branchName: (string) ($branch->name ?? 'Restaurant'),
            branchAddress: $this->branchAddress($branch),
            branchPhone: $branch?->phone !== null && $branch->phone !== ''
                ? (string) $branch->phone
                : null,
            orderType: $order->order_type,
            tableLabel: $order->table?->label,
            covers: $order->covers !== null ? (int) $order->covers : null,
            lines: $lines,
            subtotalMinor: $totals['subtotal_minor'],
            taxMinor: $totals['tax_minor'],
            discountMinor: $totals['discount_minor'],
            totalMinor: $totals['total_minor'],
            paidMinor: $paid,
            changeMinor: max(0, $paid - $totals['total_minor']),
            currency: (string) ($order->currency ?? $this->mentions->defaultCurrency($country)),
            revision: $revision,
            printedAt: $printedAt,
            mentions: $this->mentions->resolve(
                $country,
                [
                    'name' => $branch?->name,
                    'address' => $this->branchAddress($branch),
                    'phone' => $branch?->phone,
                    'tax_id' => $this->taxIdOf($order),
                ],
                (string) $order->reference,
                $printedAt,
            ),
        );
    }

    /**
     * Somme des paiements CONFIRMÉS uniquement.
     *
     * Un paiement `pending` (mobile money en attente de callback) ou `failed`
     * ne doit jamais apparaître comme encaissé : le reçu confirmerait un
     * paiement qui n'a pas eu lieu.
     */
    private function paidMinor(RestaurantOrder $order): int
    {
        return (int) $order->payments
            ->filter(fn ($payment) => $payment->status === PaymentStatus::CONFIRMED)
            ->sum('amount_minor');
    }

    private function branchAddress(mixed $branch): ?string
    {
        if ($branch === null) {
            return null;
        }

        $parts = array_filter([
            $branch->address !== null && $branch->address !== '' ? (string) $branch->address : null,
            $branch->city !== null && $branch->city !== '' ? (string) $branch->city : null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Pays de l'entreprise émettrice — pilote les mentions légales.
     *
     * Résolu via `currentCompany()` (contexte tenant déjà posé par le
     * middleware) : le reçu est le seul chemin qui a besoin du pays, imposer
     * une relation `company()` à tous les appelants serait un coût inutile.
     */
    private function countryOf(RestaurantOrder $order): ?string
    {
        $company = $this->companyOf($order);

        return $company?->country !== null ? (string) $company->country : null;
    }

    /**
     * Identifiant fiscal de l'entreprise, s'il est configuré.
     *
     * Aucune valeur n'est inventée : sans identifiant réel, la mention est
     * simplement absente du reçu (voir ReceiptMentionsResolver). L'identifiant
     * vit dans `metadata` — `companies` n'a pas de colonne fiscale dédiée, et
     * en ajouter une pour un seul usage serait un schéma prématuré.
     */
    private function taxIdOf(RestaurantOrder $order): ?string
    {
        $company = $this->companyOf($order);

        if ($company === null) {
            return null;
        }

        $metadata = $company->metadata;

        foreach (['tax_id', 'vat_number', 'tax_number'] as $key) {
            $value = $metadata[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Entreprise émettrice du reçu.
     *
     * `currentCompany()` est la source de vérité du tenant courant ; le repli
     * par identifiant couvre les chemins hors contexte tenant (job, console)
     * où le reçu est reconstruit pour un tenant explicite.
     */
    private function companyOf(RestaurantOrder $order): ?Company
    {
        if (app()->bound('current_company')) {
            $current = currentCompany();

            if ((string) $current->id === (string) $order->company_id) {
                return $current;
            }
        }

        return Company::query()->find($order->company_id);
    }

    private function formatQuantity(float $quantity): string
    {
        if (floor($quantity) === $quantity) {
            return (string) (int) $quantity;
        }

        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
