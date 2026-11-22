<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

/**
 * BOS-002 (#8144) — identifiant de journalisation d'un email (pseudonyme).
 *
 * Les logs applicatifs du parcours trial ne doivent JAMAIS porter l'email du
 * prospect en clair (PII) : on journalise un hachage tronque, qui conserve la
 * correlation entre evenements d'un meme email sans exposer la donnee. Maille
 * volontairement courte (16 hex) : suffisante pour correler, insuffisante pour
 * etre reversible par force brute a l'echelle des volumes de logs.
 *
 * Extrait du `SelfServiceTrialController` (#9732) : la meme regle etait
 * dupliquee dans chaque Action du parcours, donc trois copies pouvaient
 * deriver silencieusement.
 */
final class TrialEmailHash
{
    public static function of(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
    }
}
