<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Crm;

/**
 * Contrat partage de consentement email CRM (BC-11 CRM).
 *
 * Permet au module Communication (BC-29, R4 #7689) de verifier le
 * consentement CRM + la liste d'unsubscribe AVANT toute relance automatique
 * SANS import croise `Modules/Communication -> Modules/CRM` (isolation
 * #5584) : le consommateur ne depend que de ce contrat, implemente par
 * `CRM\Infrastructure\Services\CrmEmailFollowUpConsentGate` et binde par
 * `CrmServiceProvider` (meme pattern que `EmailContactDirectory`, R3).
 * L'arete BC-29 -> BC-11 est deja declaree au registre des bounded contexts.
 *
 * Semantique FAIL-CLOSED sur les signaux negatifs :
 * - adresse presente dans `crm_email_suppressions` (bounce, plainte,
 *   unsubscribe) -> relance INTERDITE ;
 * - contact CRM correspondant avec un consentement email RETIRE ou REFUSE
 *   (`crm_consents`, statuts withdrawn/denied) -> relance INTERDITE ;
 * - expediteur inconnu du CRM et non supprime -> relance autorisee (une
 *   relance 1-1 poursuit une correspondance engagee par le destinataire,
 *   ce n'est pas une campagne marketing soumise a l'opt-in prealable).
 */
interface EmailFollowUpConsentGate
{
    /**
     * TRUE si aucune contre-indication CRM (suppression/unsubscribe,
     * consentement retire) n'interdit de relancer cette adresse.
     */
    public function allowsFollowUp(string $companyId, string $email): bool;
}
