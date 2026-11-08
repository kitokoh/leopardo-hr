<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\JobBoards;

use Illuminate\Support\Carbon;

/**
 * BC-10 RECRUITMENT — offre normalisée telle que retournée par un client
 * d'agrégateur, avant persistance.
 *
 * Objet de transfert immuable : le mapping « payload du fournisseur → modèle
 * interne » est fait par le client, une fois, et le service de synchronisation
 * ne manipule ensuite que cette forme canonique. Cela évite que la logique
 * d'upsert connaisse les particularités de chaque fournisseur.
 */
final readonly class JobBoardOffer
{
    /**
     * @param  array<string, mixed>  $raw  charge utile d'origine (traçabilité)
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public string $companyName,
        public string $applyUrl,
        public ?string $description = null,
        public ?string $location = null,
        public ?string $country = null,
        public string $remotePolicy = 'onsite',
        public string $contractType = 'cdi',
        public ?float $salaryMin = null,
        public ?float $salaryMax = null,
        public ?string $currency = null,
        public ?Carbon $publishedAt = null,
        public ?Carbon $expiresAt = null,
        public array $raw = [],
    ) {}

    /**
     * Forme persistable (colonnes de `external_job_offers`).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(int $sourceId): array
    {
        return [
            'source_id' => $sourceId,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'description' => $this->description,
            'company_name' => $this->companyName,
            'location' => $this->location,
            'country' => $this->country,
            'remote_policy' => $this->remotePolicy,
            'contract_type' => $this->contractType,
            'salary_min' => $this->salaryMin,
            'salary_max' => $this->salaryMax,
            'currency' => $this->currency,
            'apply_url' => $this->applyUrl,
            'published_at' => $this->publishedAt,
            'expires_at' => $this->expiresAt,
            'status' => 'published',
            'raw_payload' => $this->raw === [] ? null : $this->raw,
        ];
    }
}
