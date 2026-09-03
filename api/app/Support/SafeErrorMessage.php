<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * #6559 (audit fiabilité 2026-08-31) — messages d'erreur SÛRS à persister
 * dans `error_message` (visible dans l'UI) et/ou à renvoyer à un client.
 *
 * Un message brut d'exception peut contenir des détails internes :
 * SQLSTATE + extraits de requête, chemins absolus du conteneur, DSN/URLs de
 * connexion. Ce helper scrubbe ces fragments tout en CONSERVANT le message
 * métier (ex. « Configuration bancaire entreprise manquante ») qui, lui, est
 * volontairement exposé. Les logs (report/exception) gardent l'exception
 * complète : le masquage ne s'applique qu'aux surfaces persistées/rendues.
 */
final class SafeErrorMessage
{
    /**
     * Résumé sûr d'une exception pour une surface visible (UI / erreur
     * persistée). Les erreurs de base de données (SQLSTATE / SQL: /
     * QueryException) sont réduites à un code générique — le détail exact
     * reste dans les logs serveur. Les messages métier sont conservés.
     */
    public static function summarize(\Throwable $e, int $maxLength = 1000): string
    {
        $class = (new \ReflectionClass($e))->getShortName();
        $message = trim((string) $e->getMessage());

        $isDatabaseError = $e instanceof QueryException
            || str_contains($message, 'SQLSTATE')
            || str_contains($message, 'SQL:')
            || str_contains($message, 'Unique violation')
            || str_contains($message, 'violates unique constraint');

        if ($isDatabaseError) {
            return $class.': database_error (détail technique en logs)';
        }

        // Fragments internes jamais exposés tels quels.
        $message = (string) preg_replace('#(?:/[A-Za-z0-9_.-]+){2,}#', '[path]', $message);
        $message = (string) preg_replace('#[a-z][a-z0-9+.-]*://[^\s]+#i', '[url]', $message);
        $message = (string) preg_replace('/\s+/', ' ', $message);
        $message = trim($message);

        if ($message === '') {
            return $class;
        }

        return $class.': '.mb_substr($message, 0, $maxLength);
    }
}
