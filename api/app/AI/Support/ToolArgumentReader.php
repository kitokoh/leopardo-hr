<?php

declare(strict_types=1);

namespace App\AI\Support;

/**
 * Lecture tolerante des arguments produits par le LLM — #9887.
 *
 * Le LLM n'est pas un client type : il envoie `"3"` la ou le schema annonce un
 * entier, omet une cle, ou glisse un tableau la ou une chaine est attendue.
 * Ces deux helpers existaient dans `IntentEngine` (1 375 lignes) et servaient
 * a la fois aux handlers de lecture et au resume de confirmation. Les extraire
 * ici donne UNE definition de « argument absent ou mal type » au lieu de deux
 * copies qui pouvaient diverger.
 *
 * Le contrat est volontairement permissif : jamais d'exception, toujours une
 * valeur de repli. Un argument hostile ne doit pas faire echouer un outil de
 * lecture — il doit produire une reponse vide et explicite.
 */
final class ToolArgumentReader
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function string(array $arguments, string $key, string $default): string
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function int(array $arguments, string $key, int $default): int
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_numeric($value) ? (int) $value : $default;
    }
}
