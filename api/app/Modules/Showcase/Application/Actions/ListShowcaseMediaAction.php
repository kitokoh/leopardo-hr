<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Enums\ShowcaseMediaKind;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — liste des médias d'une vitrine.
 *
 * Filtres optionnels par type (`kind`) et par section (`section_id`, id
 * stable). La relation `showcase` est préchargée pour que la ressource
 * privée résolve l'URL publique sans requête supplémentaire. L'isolation
 * tenant est portée par le scope BelongsToCompany (company_id).
 */
final class ListShowcaseMediaAction
{
    /**
     * @return list<ShowcaseMedia>
     */
    public function execute(
        CompanyShowcase $showcase,
        ?ShowcaseMediaKind $kind = null,
        ?int $sectionId = null,
    ): array {
        $query = ShowcaseMedia::query()
            ->where('showcase_id', $showcase->id)
            ->with('showcase')
            ->orderBy('id');

        if ($kind instanceof ShowcaseMediaKind) {
            $query->where('kind', $kind->value);
        }

        if ($sectionId !== null) {
            $query->where('section_id', $sectionId);
        }

        return array_values($query->get()->all());
    }
}
