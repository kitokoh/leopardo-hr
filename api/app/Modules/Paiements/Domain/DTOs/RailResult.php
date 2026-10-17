<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\DTOs;

use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;

/**
 * Resultat normalise d'un appel a un rail (BC-38, #8683).
 *
 * Le domaine ne connait que ce contrat : un rail reel et le simulateur
 * produisent exactement la meme forme de resultat, ce qui garantit qu'ils
 * empruntent le meme chemin de code en aval.
 */
final readonly class RailResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public PaymentTransactionStatus $status,
        public ?string $externalReference = null,
        public array $metadata = [],
        public ?string $failureReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function succeeded(?string $externalReference = null, array $metadata = []): self
    {
        return new self(PaymentTransactionStatus::Succeeded, $externalReference, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function failed(string $reason, array $metadata = []): self
    {
        return new self(PaymentTransactionStatus::Failed, null, $metadata, $reason);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function processing(?string $externalReference = null, array $metadata = []): self
    {
        return new self(PaymentTransactionStatus::Processing, $externalReference, $metadata);
    }
    public function isSucceeded(): bool
    {
        return $this->status === PaymentTransactionStatus::Succeeded;
    }
}
