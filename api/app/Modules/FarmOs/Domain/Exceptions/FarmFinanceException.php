<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — T4.1 Saisie financiere refusee.
 *
 * Le message est un CODE stable (jamais un libelle francais en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmFinanceException extends RuntimeException
{
    public static function campaignNotInCompany(int $campaignId): self
    {
        return new self('FARM_FINANCE_CAMPAIGN_NOT_IN_COMPANY:'.$campaignId);
    }

    public static function campaignClosed(int $campaignId): self
    {
        return new self('FARM_FINANCE_CAMPAIGN_CLOSED:'.$campaignId);
    }

    public static function invalidAmount(string $amount): self
    {
        return new self('FARM_FINANCE_INVALID_AMOUNT:'.$amount);
    }

    /**
     * Un lien de vente doit referencer un document du service de VENTES EXISTANT :
     * Farm OS ne fabrique jamais de document commercial.
     */
    public static function saleDocumentRequired(): self
    {
        return new self('FARM_FINANCE_SALE_DOCUMENT_REQUIRED');
    }

    /**
     * Un lien de vente doit viser un objet agricole (lot de recolte ou lot
     * d'animaux) : sinon le chiffre d'affaires ne serait rattache a rien.
     */
    public static function saleTargetRequired(): self
    {
        return new self('FARM_FINANCE_SALE_TARGET_REQUIRED');
    }

    /**
     * Un meme document de vente ne peut pas etre impute deux fois au meme lot :
     * c'est la garde anti-double-comptage du chiffre d'affaires.
     */
    public static function saleAlreadyLinked(int $documentId, int $lotId): self
    {
        return new self(sprintf('FARM_FINANCE_SALE_ALREADY_LINKED:%d:%d', $documentId, $lotId));
    }

    /**
     * Code stable exploitable par le controleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
