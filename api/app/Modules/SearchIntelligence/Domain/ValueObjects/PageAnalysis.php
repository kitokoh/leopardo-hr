<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\ValueObjects;

use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueType;

/**
 * GSI-2 (#8513) — résultat de l'analyse d'une page : ce que le scanner a LU,
 * et les anomalies qui en découlent.
 *
 * Value object immuable : l'analyse est une observation, pas un état mutable.
 * Le scanner persiste ce résultat, le workflow le consomme.
 */
final class PageAnalysis
{
    /**
     * @param  list<SeoIssueType>  $issues
     */
    public function __construct(
        public readonly string $url,
        public readonly int $httpStatus,
        public readonly ?string $title,
        public readonly ?string $metaDescription,
        public readonly ?string $h1,
        public readonly ?string $canonical,
        public readonly bool $hasStructuredData,
        public readonly array $issues,
    ) {}

    public function has(SeoIssueType $type): bool
    {
        return in_array($type, $this->issues, true);
    }

    /** Une page saine n'a AUCUNE anomalie : c'est la définition du scope `healthy`. */
    public function isHealthy(): bool
    {
        return $this->issues === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'http_status' => $this->httpStatus,
            'title' => $this->title,
            'meta_description' => $this->metaDescription,
            'h1' => $this->h1,
            'canonical' => $this->canonical,
            'has_structured_data' => $this->hasStructuredData,
            'issues' => array_map(
                static fn (SeoIssueType $issue): string => $issue->value,
                $this->issues,
            ),
            'healthy' => $this->isHealthy(),
        ];
    }
}
