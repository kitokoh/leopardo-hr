<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingSettings;

/**
 * #5223 — Numérotation paramétrable des documents comptables.
 *
 * Format : `{prefix}-{YYYY}-{NNNN}` (ex. FAC-2026-0001), série configurable
 * par entreprise via `AccountingSettings.number_series` :
 *   number_series.invoice = {prefix: FAC, year: true, pad: 4}
 *
 * Défauts par type (conventions FR, surchargeables) : FAC / PRO / DEV /
 * AVO / BL / RC. La sécurité concurrente est portée par la contrainte
 * unique `(company_id, number)` du socle #5221 : `nextNumber()` calcule le
 * candidat (max existant + 1) et l'appelant (transaction de création)
 * retente sur SQLSTATE 23505 (pattern upsert #4978) — jamais de doublon.
 */
class DocumentNumberingService implements DocumentNumberingInterface
{
    /**
     * @var array<string, array{prefix: string, year: bool, pad: int}>
     */
    private const DEFAULT_SERIES = [
        'invoice' => ['prefix' => 'FAC', 'year' => true, 'pad' => 4],
        'proforma' => ['prefix' => 'PRO', 'year' => true, 'pad' => 4],
        'quote' => ['prefix' => 'DEV', 'year' => true, 'pad' => 4],
        'credit_note' => ['prefix' => 'AVO', 'year' => true, 'pad' => 4],
        'delivery_note' => ['prefix' => 'BL', 'year' => true, 'pad' => 4],
        'receipt' => ['prefix' => 'RC', 'year' => true, 'pad' => 4],
    ];

    public function nextNumber(string $companyId, DocumentType $type): string
    {
        $series = $this->seriesFor($companyId, $type);
        $prefix = $series['prefix'];
        $pad = $series['pad'];
        $withYear = $series['year'];

        $year = $withYear ? now()->format('Y') : null;
        $pattern = $withYear ? "{$prefix}-{$year}-%" : "{$prefix}-%";

        // Candidat : dernier compteur existant de la série (entreprise + type).
        // withoutGlobalScopes + company_id explicite : déterministe aussi hors
        // contexte tenant (console/jobs) — jamais de compteur cross-tenant.
        $lastNumber = (string) AccountingDocument::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', $type->value)
            ->where('number', 'like', $pattern)
            ->orderByDesc('id')
            ->value('number');

        $next = 1;
        if ($lastNumber !== '') {
            // Le numéro le plus récent de la série porte le compteur maximal :
            // parse `PREFIX-YYYY-0007` → 7.
            preg_match('/(\d+)$/', $lastNumber, $matches);
            $next = ((int) ($matches[1] ?? 0)) + 1;
        }

        return $withYear
            ? sprintf('%s-%s-%s', $prefix, $year, str_pad((string) $next, $pad, '0', STR_PAD_LEFT))
            : sprintf('%s-%s', $prefix, str_pad((string) $next, $pad, '0', STR_PAD_LEFT));
    }

    /**
     * Série effective pour un type : surcharge entreprise sinon défaut.
     *
     * @return array{prefix: string, year: bool, pad: int}
     */
    private function seriesFor(string $companyId, DocumentType $type): array
    {
        $default = self::DEFAULT_SERIES[$type->value];

        /** @var AccountingSettings|null $settings */
        $settings = AccountingSettings::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $override = $settings?->number_series[$type->value] ?? null;
        if (! is_array($override)) {
            return $default;
        }

        return [
            'prefix' => is_string($override['prefix'] ?? null) && $override['prefix'] !== ''
                ? $override['prefix']
                : $default['prefix'],
            'year' => array_key_exists('year', $override)
                ? (bool) $override['year']
                : $default['year'],
            'pad' => isset($override['pad']) && is_numeric($override['pad'])
                ? max(1, (int) $override['pad'])
                : $default['pad'],
        ];
    }
}
