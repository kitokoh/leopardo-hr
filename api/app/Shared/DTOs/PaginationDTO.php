<?php

namespace App\Shared\DTOs;

final readonly class PaginationDTO
{
    public function __construct(
        public int $perPage = 20,
        public int $page = 1,
        public ?string $sortBy = null,
        public string $sortDir = 'asc',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            perPage: min((int) ($data['per_page'] ?? 20), 100),
            page: max((int) ($data['page'] ?? 1), 1),
            sortBy: $data['sort_by'] ?? null,
            sortDir: in_array($data['sort_dir'] ?? 'asc', ['asc', 'desc']) ? ($data['sort_dir'] ?? 'asc') : 'asc',
        );
    }
}
