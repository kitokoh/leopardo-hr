<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Events\CatalogInquiryErased;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;

/**
 * Cas d'usage « effacer les donnees acheteur d'une demande » (BOS-024f, #8217).
 *
 * Extrait de `CatalogInquiryController::destroy` : droit d'effacement RGPD
 * (C-RGPD #6889, canal support tenant). La demande est supprimee puis
 * l'evenement `CatalogInquiryErased` propage l'effacement aux leads CRM BC-11
 * du meme acheteur — le contrat cross-BC reste porte par le listener, jamais
 * par un import direct.
 */
final class EraseCatalogInquiryAction
{
    public function execute(CatalogInquiry $inquiry, string $companyId): void
    {
        $buyerEmail = (string) $inquiry->email;
        $inquiryId = (int) $inquiry->id;

        $inquiry->delete();

        event(new CatalogInquiryErased($companyId, $buyerEmail, $inquiryId));
    }
}
