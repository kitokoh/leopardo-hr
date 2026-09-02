<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Application\DTOs;

final class UploadDocumentDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly int    $employeeId,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int    $size,
        public readonly string $disk,
        public readonly string $path,
        public readonly ?int    $folderId = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId:    (int) $data['company_id'],
            employeeId:   (int) $data['employee_id'],
            originalName: $data['original_name'],
            mimeType:     $data['mime_type'],
            size:         (int) $data['size'],
            disk:         $data['disk'] ?? 'local',
            path:         $data['path'],
            folderId:     isset($data['folder_id']) ? (int) $data['folder_id'] : null,
            notes:        $data['notes'] ?? null,
        );
    }
}
