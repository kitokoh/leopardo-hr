<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-2 (#8513) — cycle de vie d'une anomalie SEO.
 *
 * `Open → Acknowledged → Resolved` est le chemin nominal. `Ignored` est une
 * sortie explicite : une anomalie ignorée reste visible dans les compteurs
 * pour ne pas laisser croire qu'elle a disparu, mais ne pollue plus la file de
 * traitement.
 */
enum SeoIssueStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function isTerminal(): bool
    {
        return $this === self::Resolved || $this === self::Ignored;
    }

    /**
     * Transitions autorisées. Toute autre combinaison est refusée par le
     * domaine — on ne peut pas « résoudre » une anomalie sans l'avoir au
     * moins prise en compte, ce qui garantit que `resolved_at` a un sens.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::Ignored],
            self::Acknowledged => [self::Resolved, self::Ignored],
            self::Resolved, self::Ignored => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
