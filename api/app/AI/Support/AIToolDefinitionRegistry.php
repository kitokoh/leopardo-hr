<?php

declare(strict_types=1);

namespace App\AI\Support;

use InvalidArgumentException;

/**
 * Registre des définitions d'outils déclarées par les BC — issue #6850.
 *
 * Chaque BC propriétaire enregistre ses AIToolDefinition (typiquement dans
 * son ServiceProvider au boot). L'hôte BC-23 les découvre sans connaître les
 * BC : un nouveau BC ajoute ses outils sans toucher au code de l'assistant.
 *
 * Usage (BC propriétaire) :
 *   AIToolDefinitionRegistry::register(new AIToolDefinition(
 *       name: 'absence_decision',
 *       description: 'Approuver ou refuser une demande d'absence.',
 *       inputSchema: [...],
 *       permission: 'approve-absence',
 *       sensitivity: AIToolSensitivity::Write,
 *       bc: 'BC-06',
 *   ));
 *
 * Le registre est statique (collecteur) ; app()->forgetInstance() ou
 * self::reset() permettent de le réinitialiser dans les tests.
 *
 * ⚠️ Contrat d'enregistrement (#6947) : le collecteur statique survit aux
 * boots applicatifs (PHP-FPM : un worker re-boote les providers à chaque
 * requête ; PHPUnit : un process = N boots). Tout fournisseur qui enregistre
 * au boot DOIT vérifier `self::has($name)` avant `register()` (idempotence
 * par boot) — `register()` reste strict pour détecter un VRAI doublon
 * intra-boot (deux BC déclarant le même nom d'outil).
 */
final class AIToolDefinitionRegistry
{
    /** @var array<string, AIToolDefinition> */
    private static array $definitions = [];

    public static function register(AIToolDefinition $definition): void
    {
        if (isset(self::$definitions[$definition->name])) {
            throw new InvalidArgumentException(
                "AIToolDefinition dupliquée : \"{$definition->name}\" déjà enregistrée."
            );
        }

        self::$definitions[$definition->name] = $definition;
    }

    /**
     * @return array<string, AIToolDefinition>
     */
    public static function all(): array
    {
        return self::$definitions;
    }

    /**
     * @return array<string, AIToolDefinition>
     */
    public static function forBc(string $bc): array
    {
        return array_filter(
            self::$definitions,
            fn (AIToolDefinition $d): bool => $d->bc === $bc,
        );
    }

    public static function has(string $name): bool
    {
        return isset(self::$definitions[$name]);
    }

    public static function find(string $name): ?AIToolDefinition
    {
        return self::$definitions[$name] ?? null;
    }

    public static function reset(): void
    {
        self::$definitions = [];
    }
}
