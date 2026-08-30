<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;

/**
 * TRAVEL-805 (#6096) — Création/mise à jour d'un taux avec garde anti-
 * chevauchement de périodes pour la même paire (from, to) d'un tenant.
 */
final class UpsertCurrencyRateAction
{
    /**
     * @param  array{from_currency: string, to_currency: string, rate_minor: int, valid_from: string, valid_to?: string|null}  $data
     */
    public function create(array $data): TravelCurrencyRate
    {
        $this->assertNoOverlap($data['from_currency'], $data['to_currency'], $data['valid_from'], $data['valid_to'] ?? null);

        return TravelCurrencyRate::query()->create($data);
    }

    /**
     * @param  array{from_currency: string, to_currency: string, rate_minor: int, valid_from: string, valid_to?: string|null}  $data
     */
    public function update(TravelCurrencyRate $rate, array $data): TravelCurrencyRate
    {
        $this->assertNoOverlap(
            $data['from_currency'],
            $data['to_currency'],
            $data['valid_from'],
            $data['valid_to'] ?? null,
            $rate->id,
        );

        $rate->update($data);

        return $rate->refresh();
    }

    private function assertNoOverlap(string $from, string $to, string $validFrom, ?string $validTo, ?int $exceptId = null): void
    {
        $query = TravelCurrencyRate::query()
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->where(function ($builder) use ($validFrom) {
                // Période existante ouverte ou se terminant après le début de la nouvelle.
                $builder->whereNull('valid_to')->orWhereDate('valid_to', '>=', $validFrom);
            })
            ->whereDate('valid_from', '<=', $validTo ?? $validFrom);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            abort(422, 'Une periode de taux se chevauche deja pour cette paire de devises.');
        }
    }
}
