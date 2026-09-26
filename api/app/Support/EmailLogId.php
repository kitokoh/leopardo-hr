<?php

declare(strict_types=1);

namespace App\Support;

/**
 * BOS-002 (#8144) / #8164 — identifiant de journalisation d'un email
 * (pseudonyme).
 *
 * Les logs applicatifs ne doivent JAMAIS porter d'email en clair (PII) : on
 * journalise un hachage tronqué, qui conserve la corrélation entre
 * événements d'un même email sans exposer la donnée. Maille volontairement
 * courte (16 hex) : suffisante pour corréler, insuffisante pour être
 * réversible par force brute à l'échelle des volumes de logs.
 *
 * Implémentation canonique partagée (avant : copie privée dans
 * SelfServiceTrialController) — tout nouveau point de log touchant un email
 * DOIT passer par ce helper.
 */
final class EmailLogId
{
    public static function hash(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
    }
}
