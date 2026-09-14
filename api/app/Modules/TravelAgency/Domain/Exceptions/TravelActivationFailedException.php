<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Exceptions;

use RuntimeException;

/**
 * #7393 — l'activation de la verticale TravelAgency n'a pas été persistée.
 *
 * Levée par `ActivateTravelAgencyAction::execute()` après relecture de la
 * société depuis la base : si le flag `travelagency` n'est pas retrouvé à
 * `true` (écriture perdue, search_path inattendu, kill switch), l'activation
 * est un échec explicite — jamais un succès annoncé à tort.
 */
final class TravelActivationFailedException extends RuntimeException
{
    /**
     * Message volontairement sans accents : c'est un diagnostic
     * d'exploitation (console/artisan), meme convention que les autres
     * messages internes du module — la garde i18n (#5432) refuse les
     * chaines francaises accentuees en dur sur les surfaces API.
     */
    public static function flagNotPersisted(string $companyId): self
    {
        return new self(
            "Activation TravelAgency incomplete : le flag travelagency n'est pas actif "
            ."apres ecriture (company {$companyId}). Aucune activation annoncee : "
            ."verifier la persistance (search_path) et l'etat du kill switch.",
        );
    }
}
