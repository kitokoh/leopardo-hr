<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Pages\Domain\Enums\PageStatus;
use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Modules\Pages\Domain\Exceptions\PageSlugException;
use App\Modules\Pages\Domain\Models\Page;

/**
 * Crée la Page d'un établissement (BC PAGES, #8728).
 *
 * ## Une Page par Site, et le slug est GLOBAL
 *
 * L'unicité `site_id` est portée par la base (`pages_site_unique`) : deux
 * Pages pour un même établissement rendraient le rendu public non
 * déterministe. Le slug est unique **globalement** (index `pages_slug_unique`)
 * parce qu'une URL publique ne doit jamais dépendre du tenant courant — règle
 * reprise de la publication par verticale (ADR `ADR-publication-publique.md`
 * §2).
 *
 * ## Le slug est normalisé, jamais inventé
 *
 * `Page::normalizeSlug()` translittère et borne. Si rien d'exploitable ne
 * reste, on refuse : fabriquer un slug de secours à partir de l'ID produirait
 * une URL opaque que l'utilisateur ne peut ni lire ni corriger.
 *
 * ## La Page naît en brouillon, non vérifiée
 *
 * Publier et vérifier sont deux gestes distincts et conscients. Une création
 * qui publierait directement exposerait au public un contenu que personne n'a
 * relu.
 */
final class CreatePage
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws PageSlugException
     */
    public function execute(string $companyId, Site $site, array $payload): Page
    {
        $rawSlug = $payload['slug'] ?? null;

        $slug = is_string($rawSlug) && trim($rawSlug) !== ''
            ? Page::normalizeSlug($rawSlug)
            : Page::normalizeSlug((string) ($payload['title'] ?? $site->name));

        if ($slug === null) {
            throw PageSlugException::unusable();
        }

        if (Page::isReservedSlug($slug)) {
            throw PageSlugException::reserved($slug);
        }

        /** @var Page $page */
        $page = Page::query()->create([
            'company_id' => $companyId,
            'site_id' => $site->id,
            'slug' => $slug,
            'status' => PageStatus::Draft,
            'verification_status' => PageVerificationStatus::Unverified,
            'theme' => $payload['theme'] ?? 'default',
            'settings' => $payload['settings'] ?? null,
            'legal' => $payload['legal'] ?? null,
            'seo' => $payload['seo'] ?? null,
            'locales' => $payload['locales'] ?? null,
            'default_locale' => $payload['default_locale'] ?? 'fr',
            'schema_version' => 1,
        ]);

        // Le `site_id` vient d'être écrit : on vérifie l'appartenance tenant
        // AVANT de rendre la Page à l'appelant. Un POST forgé qui associerait
        // l'établissement d'un autre tenant doit échouer ici, pas au premier
        // rendu public.
        $page->assertSameTenantAsSite();

        return $page;
    }
}
