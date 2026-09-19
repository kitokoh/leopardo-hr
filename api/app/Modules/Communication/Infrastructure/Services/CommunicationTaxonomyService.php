<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Models\CommunicationCategory;

/**
 * Taxonomie email du tenant (BC-29 COMMUNICATION, R3 #7688, spec §3.3) —
 * catégories PARAMÉTRABLES PAR TENANT avec défauts i18n FR/EN/AR/TR.
 *
 * Matérialisation PARESSEUSE : au premier usage (classification, listing),
 * les défauts de `config('communication.classification.default_categories')`
 * sont insérés (`is_system = true`, `label` null → libellé i18n
 * `communication.category_<key>`). Idempotent (unique company+key, firstOr).
 */
class CommunicationTaxonomyService
{
    /**
     * Catégories ACTIVES du tenant (défauts matérialisés si besoin).
     *
     * @return list<CommunicationCategory>
     */
    public function activeCategories(string $companyId): array
    {
        $this->materializeDefaults($companyId);

        return array_values(CommunicationCategory::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('key')
            ->get()
            ->all());
    }

    /**
     * Toutes les catégories du tenant, actives ou non (écran réglages).
     *
     * @return list<CommunicationCategory>
     */
    public function allCategories(string $companyId): array
    {
        $this->materializeDefaults($companyId);

        return array_values(CommunicationCategory::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('key')
            ->get()
            ->all());
    }

    /**
     * Clés de catégories ACTIVES — l'allowlist contre laquelle la sortie du
     * LLM est validée (jamais de catégorie inventée persistée).
     *
     * @return list<string>
     */
    public function activeCategoryKeys(string $companyId): array
    {
        return array_map(
            static fn (CommunicationCategory $category): string => $category->key,
            $this->activeCategories($companyId),
        );
    }

    private function materializeDefaults(string $companyId): void
    {
        $existing = CommunicationCategory::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_system', true)
            ->pluck('key')
            ->all();

        foreach ($this->defaultKeys() as $key) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            $category = new CommunicationCategory;
            $category->forceFill([
                'company_id' => $companyId,
                'key' => $key,
                'label' => null,
                'is_system' => true,
                'active' => true,
            ]);
            $category->save();
        }
    }

    /**
     * @return list<string>
     */
    private function defaultKeys(): array
    {
        /** @var mixed $configured */
        $configured = config('communication.classification.default_categories', []);

        $keys = [];

        foreach (is_array($configured) ? $configured : [] as $key) {
            if (is_string($key) && preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) === 1) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
