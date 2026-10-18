<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Contracts;

use App\Modules\Paiements\Domain\DTOs\CashInRequest;
use App\Modules\Paiements\Domain\DTOs\CashOutRequest;
use App\Modules\Paiements\Domain\DTOs\RailResult;
use App\Modules\Paiements\Domain\DTOs\WebhookPayload;

/**
 * BC-38 PAIEMENTS (#8683) — contrat stable d'un rail de paiement.
 *
 * C'est le seul point d'extension du BC : ajouter une famille technique =
 * un nouveau `PaymentProviderType` + son adapter implementant cette interface,
 * **sans modifier** les entites ni l'API existante (Open/Closed).
 *
 * Aucun nom commercial n'apparait ici : les types sont generiques, la marque
 * vit dans la configuration (`PaymentProviderInstance`).
 */
interface PaymentRailInterface
{
    /** Code du type, unique dans le registre. */
    public function code(): string;

    public function initierCashIn(CashInRequest $request): RailResult;

    public function initierCashOut(CashOutRequest $request): RailResult;

    public function traiterWebhook(WebhookPayload $payload): RailResult;

    public function verifierStatut(string $externalReference): RailResult;
}
