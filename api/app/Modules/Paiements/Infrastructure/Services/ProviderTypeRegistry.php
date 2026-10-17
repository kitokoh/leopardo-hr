<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Contracts\PaymentRailInterface;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use InvalidArgumentException;

/**
 * BC-38 PAIEMENTS (#8683) — registre des types de fournisseur (NIVEAU 1, le CODE).
 *
 * C'est la liste des familles techniques reellement implementees. Elle vit dans
 * le code, pas en base : un admin ne peut ni la modifier ni l'etendre depuis le
 * Superadmin (lecture seule). Ajouter une famille = ecrire un adapter et
 * l'enregistrer ici — c'est un travail de developpeur, et c'est voulu.
 *
 * Les types sont **generiques** : aucun nom commercial n'apparait. La marque
 * vit dans `PaymentProviderInstance` (NIVEAU 2, la CONFIGURATION).
 */
class ProviderTypeRegistry
{
    /** @var array<string, class-string<PaymentRailInterface>> */
    private array $types = [];

    /** @var array<string, string> */
    private array $labels = [];

    public function register(string $code, string $label, string $railClass): void
    {
        if (! is_a($railClass, PaymentRailInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Le rail %s doit implementer %s.',
                $railClass,
                PaymentRailInterface::class,
            ));
        }

        $this->types[$code] = $railClass;
        $this->labels[$code] = $label;
    }

    /**
     * @return list<array{code: string, label: string, rail: string}>
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->types as $code => $railClass) {
            $out[] = [
                'code' => $code,
                'label' => $this->labels[$code] ?? $code,
                'rail' => $railClass,
            ];
        }

        return $out;
    }

    public function has(string $code): bool
    {
        return isset($this->types[$code]);
    }

    /**
     * @return class-string<PaymentRailInterface>
     */
    public function railClassFor(string $code): string
    {
        if (! $this->has($code)) {
            throw new InvalidArgumentException(sprintf('Type de fournisseur inconnu : %s.', $code));
        }

        return $this->types[$code];
    }

    public function make(string $code): PaymentRailInterface
    {
        $class = $this->railClassFor($code);

        return app($class);
    }

    /**
     * Enregistrement par defaut : le simulateur est le premier type livre.
     */
    public static function withDefaults(): self
    {
        $registry = new self;
        $registry->register(SimulatorRail::CODE, 'Simulateur (test)', SimulatorRail::class);

        return $registry;
    }
}
