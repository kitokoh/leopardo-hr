<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains\Contracts;

/**
 * Réponse normalisée d'un fournisseur de domaines.
 *
 * Les fournisseurs parlent des dialectes différents (`pending_deployment`,
 * `ssl_pending`, `active`…). On normalise en quatre états seulement, parce que
 * le reste n'a aucun effet sur nos décisions : soit on sert, soit on attend,
 * soit c'est cassé, soit c'est absent.
 */
final readonly class CustomDomainProviderResult
{
    public const STATE_ACTIVE = 'active';

    public const STATE_PENDING = 'pending';

    public const STATE_FAILED = 'failed';

    public const STATE_ABSENT = 'absent';

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $state,
        public bool $certificateActive = false,
        public ?string $detail = null,
        public array $raw = [],
    ) {
    }

    public static function active(bool $certificateActive = true): self
    {
        return new self(self::STATE_ACTIVE, $certificateActive);
    }

    public static function pending(string $detail = 'en attente de validation DNS'): self
    {
        return new self(self::STATE_PENDING, false, $detail);
    }

    public static function failed(string $detail): self
    {
        return new self(self::STATE_FAILED, false, $detail);
    }

    public static function absent(): self
    {
        return new self(self::STATE_ABSENT, false, 'absent chez le fournisseur');
    }

    /**
     * Le host peut-il servir du trafic **maintenant** ?
     *
     * Les deux conditions comptent : un host `active` sans certificat actif
     * sert du HTTP en clair, ce qui est pire qu'une indisponibilité.
     */
    public function isServing(): bool
    {
        return $this->state === self::STATE_ACTIVE && $this->certificateActive;
    }
}
