<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

enum PlanCode: string
{
    case Free = 'free';
    case Pilot = 'pilot';
    case Operations = 'operations';
    case Enterprise = 'enterprise';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $plan): string => $plan->value, self::cases());
    }

    /**
     * Convert legacy billing identifiers to the canonical product identifiers.
     * Boundary callers may still send old values, but persisted matrix rows use only canonical values.
     */
    public static function normalize(string $value): self
    {
        return match (strtolower(trim($value))) {
            'trial', 'free' => self::Free,
            'starter', 'pilot' => self::Pilot,
            'business', 'operations' => self::Operations,
            'enterprise', 'scale' => self::Enterprise,
            default => throw new \InvalidArgumentException("Unsupported plan code: {$value}"),
        };
    }
}
