<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Events\CatalogInquiryReceived;
use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use Illuminate\Database\ConnectionInterface;

/**
 * Cas d'usage « recevoir une demande de devis publique » (BOS-024f, #8217).
 *
 * Extrait de `CatalogPublicInquiryController::store` (C-LEAD #6884) : la
 * re-verification fail-closed du produit publie (il peut avoir ete depublie
 * entre la validation et l'ecriture), la persistance minimisee RGPD
 * (consentement horodate, conservation bornee, IP hashee) et l'emission de
 * `CatalogInquiryReceived` (lead CRM BC-11 + notification tenant BC-13).
 *
 * Le honeypot anti-spam reste dans la couche HTTP : il repond 201 sans rien
 * persister, c'est une decision de contrat de reponse, pas un cas d'usage.
 * Retourne `null` quand le produit n'est plus publie (l'appelant compose le
 * 422 existant).
 *
 * Fixation explicite du tenant : le scope global `company` de
 * `BelongsToCompany` ne s'applique que si un contexte compagnie est lie ;
 * l'Action ajoute donc `company_id` a la recherche produit pour rester
 * fail-closed hors requete HTTP (meme resultat sur le chemin public, ou le
 * tenant est resolu par slug — aucun changement de comportement).
 */
final class SubmitCatalogInquiryAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<string, mixed>  $payload  Payload valide (StoreCatalogInquiryRequest).
     */
    public function execute(string $companyId, array $payload, ?string $ip): ?CatalogInquiry
    {
        $product = CatalogProduct::query()
            ->where('company_id', $companyId)
            ->where('slug', (string) ($payload['product_slug'] ?? ''))
            ->where('status', CatalogProductStatus::Published->value)
            ->first();

        if (! $product instanceof CatalogProduct) {
            return null;
        }

        $consentAt = now();
        $retentionDays = (int) config('catalog.inquiry_retention_days', 90);

        /** @var CatalogInquiry $inquiry */
        $inquiry = $this->db->transaction(function () use (
            $companyId,
            $product,
            $payload,
            $consentAt,
            $retentionDays,
            $ip
        ): CatalogInquiry {
            /** @var CatalogInquiry $inquiry */
            $inquiry = CatalogInquiry::query()->create([
                'company_id' => $companyId,
                'product_slug' => (string) $product->slug,
                'product_name' => (string) $product->name,
                'quantity' => $payload['quantity'] ?? null,
                'company_name' => (string) ($payload['company_name'] ?? ''),
                'email' => (string) ($payload['email'] ?? ''),
                'message' => $payload['message'] ?? null,
                'status' => CatalogInquiryStatus::New->value,
                'consent_at' => $consentAt,
                'retention_until' => $consentAt->copy()->addDays($retentionDays)->toDateString(),
                'ip_hash' => is_string($ip) && $ip !== '' && $ip !== '127.0.0.1'
                    ? hash('sha256', $ip)
                    : null,
            ]);

            return $inquiry;
        });

        event(new CatalogInquiryReceived($companyId, $inquiry));

        return $inquiry;
    }
}
