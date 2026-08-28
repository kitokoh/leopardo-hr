<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

use App\Modules\CRM\Domain\Exceptions\CrmProviderException;

/**
 * Contrat commun des canaux CRM (issue #5727) — le CRM n'est jamais couplé
 * à un fournisseur unique : chaque provider implémente cet adaptateur.
 *
 * API : send / verify / normalize / revoke.
 */
interface ChannelAdapterContract
{
    /**
     * Envoie un message via le provider.
     *
     * @param  array<string, mixed>  $settings  configuration non sensible du canal
     * @return array{provider_message_id: string, status: string, cost?: float}
     *
     * @throws CrmProviderException  erreur fournisseur (retryable ou non)
     */
    public function send(string $toAddress, ?string $body, ?string $templateName, array $settings): array;

    /**
     * Vérifie qu'une adresse de destination est utilisable (numéro valide,
     * format accepté). Retourne true si le provider accepte l'adresse.
     */
    public function verify(string $address, array $settings): bool;

    /**
     * Normalise une adresse de destination (E.164 pour téléphones, lowercase
     * pour emails). Retourne null si le format est inacceptable.
     */
    public function normalize(string $address): ?string;

    /**
     * Tente de révoquer/supprimer un message déjà envoyé (best-effort).
     * Retourne true si le provider confirme la révocation.
     */
    public function revoke(string $providerMessageId, array $settings): bool;

    /**
     * Type de canal servi par cet adaptateur (CrmChannelType::*).
     */
    public function channelType(): string;
}
