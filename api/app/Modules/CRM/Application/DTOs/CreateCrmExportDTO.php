<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\DTOs;

/**
 * Données d'entrée validées pour la création d'un export CRM (issue #5729).
 */
final class CreateCrmExportDTO
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $entity,
        public readonly array $columns = [],
        public readonly array $filters = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $columns = array_values(array_filter(
            $data['columns'] ?? [],
            static fn (mixed $c): bool => is_string($c),
        ));

        return new self(
            entity: (string) $data['entity'],
            columns: array_map(static fn (string $c): string => $c, $columns),
            filters: is_array($data['filters'] ?? null) ? $data['filters'] : [],
        );
    }
}
