<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\DTOs;

/**
 * #8747 [BOS-CHAT] — curseur de lecture de l'historique d'un fil.
 *
 * Deux directions, jamais mélangées :
 *
 *  - **`beforeSeq`** (remonter le temps) — le client affiche les 30 derniers
 *    messages et demande la page précédente. La réponse contient les messages
 *    dont `seq < beforeSeq`, triés par `seq` DÉCROISSANT, et `has_more` indique
 *    s'il en reste.
 *  - **`afterSeq`** (rattraper le temps) — après une reconnexion (#8749), le
 *    client demande tout ce qui est arrivé depuis son dernier `seq` connu. La
 *    réponse est triée par `seq` CROISSANT, donc directement concaténable.
 *
 * Un seul des deux peut être fourni : accepter les deux rendrait l'ordre de
 * réponse ambigu et donc le client incorrect.
 */
final readonly class MessageCursor
{
    private function __construct(
        public ?int $beforeSeq,
        public ?int $afterSeq,
        public int $perPage,
    ) {}

    public static function fromQuery(?int $beforeSeq, ?int $afterSeq, ?int $perPage = null): self
    {
        $max = (int) config('conversations.messages.max_per_page', 100);
        $default = (int) config('conversations.messages.per_page', 30);

        $size = $perPage ?? $default;
        $size = max(1, min($max, $size));

        return new self(
            beforeSeq: $beforeSeq !== null && $beforeSeq > 0 ? $beforeSeq : null,
            afterSeq: $afterSeq !== null && $afterSeq >= 0 ? $afterSeq : null,
            perPage: $size,
        );
    }

    /**
     * Le mode « rattrapage » (après reconnexion) est-il demandé ?
     */
    public function isCatchUp(): bool
    {
        return $this->afterSeq !== null;
    }
}
