<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\DTOs;

use App\Modules\HR\Domain\Models\UserInvitation;

/**
 * #9322 — Résultat d'une émission d'invitation collaborateur.
 *
 * `UserInvitationService::createAndSend()` retournait un simple `string` ; les
 * appelants ne savaient donc pas si le courriel était réellement parti. Le
 * `catch` volontaire de #1776 / #1784 (un transport mail absent ne doit PAS
 * faire échouer le flux principal) masquait l'échec : le manager voyait un
 * succès, le collaborateur jamais l'invitation, et l'invitation restait
 * intransmissible puisque le jeton en clair n'était exposé nulle part.
 *
 * Ce DTO porte l'issue de l'envoi pour que les endpoints puissent relayer le
 * lien d'activation EUX-MÊMES — et uniquement quand le courriel a échoué.
 *
 * Garde-fous (#9114 / #9117) :
 * - `token === ''` ⇒ invitation déjà consommée, aucun lien à exposer ;
 * - `hasFallbackLink()` est le SEUL prédicat autorisé pour décider d'exposer
 *   `activationUrl` — jamais `! $mailSent` seul ;
 * - le jeton en clair n'est jamais journalisé (le lien EST la credential).
 */
final readonly class UserInvitationDispatchResult
{
    public function __construct(
        public string $token,
        public ?string $activationUrl,
        public bool $mailSent,
        public ?UserInvitation $invitation = null,
    ) {}

    /**
     * Invitation déjà consommée (compte actif) : rien n'est émis ni envoyé.
     */
    public static function alreadyAccepted(): self
    {
        return new self(token: '', activationUrl: null, mailSent: false, invitation: null);
    }

    /**
     * Vrai uniquement quand un lien utilisable existe ET que le courriel n'est
     * pas parti — le manager doit alors le transmettre à la main.
     */
    public function hasFallbackLink(): bool
    {
        return $this->token !== '' && ! $this->mailSent && $this->activationUrl !== null;
    }
}
