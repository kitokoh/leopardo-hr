<?php

declare(strict_types=1);

namespace App\Shared\Establishments;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910) \u2014 \u00e9l\u00e9ment de la liste unifi\u00e9e.
 *
 * DTO immuable : chaque {@see \App\Shared\Contracts\Establishments\EstablishmentProvider}
 * en produit, l'agr\u00e9gateur les s\u00e9rialise tels quels. Les noms de champs
 * expos\u00e9s (`vertical`, `id`, `name`, `code`, `status`, `href_stats`,
 * `href_team`, `staff_count`) sont ceux sp\u00e9cifi\u00e9s par l'issue \u2014 c'est un
 * contrat d'API, pas un d\u00e9tail d'impl\u00e9mentation.
 *
 * Les liens `href_stats` / `href_team` pointent vers des pages R\u00c9ELLES de la
 * verticale (v\u00e9rifi\u00e9es dans `front/web/src/app/(dashboard)/`), avec le
 * filtre d'\u00e9tablissement pr\u00e9-s\u00e9lectionn\u00e9 en query string. Un lien vers une
 * page inexistante serait exactement le bouton inop\u00e9rant que le hub doit
 * \u00e9viter.
 */
final readonly class EstablishmentItem
{
    public function __construct(
        public string $vertical,
        public int $id,
        public string $name,
        public ?string $code,
        public string $status,
        public ?string $hrefStats,
        public ?string $hrefTeam,
        public int $staffCount,
    ) {}

    /**
     * Forme expos\u00e9e par l'API.
     *
     * @return array{vertical: string, id: int, name: string, code: string|null, status: string, href_stats: string|null, href_team: string|null, staff_count: int}
     */
    public function toArray(): array
    {
        return [
            'vertical' => $this->vertical,
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            'href_stats' => $this->hrefStats,
            'href_team' => $this->hrefTeam,
            'staff_count' => $this->staffCount,
        ];
    }
}
