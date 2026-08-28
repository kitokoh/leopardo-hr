<?php

declare(strict_types=1);

namespace App\Core\Privacy;

/**
 * MAT-011 (#5869) — registre machine des champs PII et de leurs politiques.
 *
 * Lit `config/privacy.php` (source de vérité) : chaque champ sensible d'une
 * entité possède une politique (catégorie, chiffrement, anonymisation,
 * export, accès, rétention, base légale). Les tests de cohérence RGPD
 * s'appuient sur ce registre pour garantir la parité avec
 * `gdpr:anonymize-employee`, les casts `encrypted` d'`Employee` et le bundle
 * d'export `PrivacyController`.
 */
final class PiiFieldRegistry
{
    /**
     * Politique d'un champ PII, ou null si inconnu.
     *
     * @return array<mixed, mixed>|null
     */
    public function policy(string $entity, string $field): ?array
    {
        $fields = $this->entityFields($entity);

        $policy = $fields[$field] ?? null;

        return is_array($policy) ? $policy : null;
    }

    /**
     * Indique si un champ est déclaré PII pour une entité.
     */
    public function isPii(string $entity, string $field): bool
    {
        return $this->policy($entity, $field) !== null;
    }

    /**
     * Champs PII déclarés pour une entité (clé = nom du champ).
     *
     * @return array<string, array<mixed, mixed>>
     */
    public function entityFields(string $entity): array
    {
        $entities = config('privacy.entities');

        if (! is_array($entities)) {
            return [];
        }

        $entityConfig = $entities[$entity] ?? null;

        if (! is_array($entityConfig)) {
            return [];
        }

        $fields = $entityConfig['fields'] ?? null;

        if (! is_array($fields)) {
            return [];
        }

        $declared = [];

        foreach ($fields as $field => $policy) {
            if (is_string($field) && is_array($policy)) {
                $declared[$field] = $policy;
            }
        }

        return $declared;
    }

    /**
     * Champs PII chiffrés au repos pour une entité.
     *
     * @return array<int, string>
     */
    public function encryptedFields(string $entity): array
    {
        return $this->fieldsWhere($entity, 'encrypted', true);
    }

    /**
     * Champs PII couverts par l'anonymisation RGPD pour une entité.
     *
     * @return array<int, string>
     */
    public function anonymizedFields(string $entity): array
    {
        return $this->fieldsWhere($entity, 'anonymized', true);
    }

    /**
     * Champs PII inclus dans le bundle d'export pour une entité.
     *
     * @return array<int, string>
     */
    public function exportedFields(string $entity): array
    {
        return $this->fieldsWhere($entity, 'exported', true);
    }

    /**
     * @return array<int, string>
     */
    private function fieldsWhere(string $entity, string $policyKey, bool $expected): array
    {
        $matched = [];

        foreach ($this->entityFields($entity) as $field => $policy) {
            $value = $policy[$policyKey] ?? false;

            if (is_bool($value) && $value === $expected) {
                $matched[] = $field;
            }
        }

        sort($matched);

        return $matched;
    }
}
