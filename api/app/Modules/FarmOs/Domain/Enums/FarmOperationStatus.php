<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS (BC-37 AGRICULTURE) — Statut d'une opération agricole.
 *
 * Machine à états FERMÉE : les transitions autorisées sont déclarées ici, une
 * seule fois, et vérifiées côté serveur
 * ({@see \App\Modules\FarmOs\Application\Actions\TransitionFarmOperationAction}).
 *
 * Règle d'intégrité : une opération `Done` est IMMUABLE — toute correction doit
 * passer par une action dédiée auditée, jamais par un simple PATCH.
 */
enum FarmOperationStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function translationKey(): string
    {
        return 'farm.operation_status.'.$this->value;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::InProgress, self::Skipped, self::Cancelled],
            self::InProgress => [self::Done, self::Cancelled],
            self::Done, self::Skipped, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Statut terminal : plus aucune transition, la ligne est immuable.
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Une clôture exige-t-elle une date de fin ?
     */
    public function requiresFinishedAt(): bool
    {
        return in_array($this, [self::Done, self::Skipped, self::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
