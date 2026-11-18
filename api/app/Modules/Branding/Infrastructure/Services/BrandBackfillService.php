<?php

declare(strict_types=1);

namespace App\Modules\Branding\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\Palette\BrandPalette;
use App\Shared\Contracts\Persistence\TransactionRunner;

/**
 * Backfill `companies.metadata.branding` → `brands` (BC-41, #9582).
 *
 * ## Pourquoi un backfill plutôt qu'une migration de données
 *
 * Le branding existant vit dans un JSON libre (`companies.metadata.branding`)
 * dont la forme n'est pas garantie : certaines entreprises n'en ont pas,
 * d'autres en ont un partiel, d'autres encore en ont un avec des clés qui
 * n'existent plus. Une migration de données qui lit ce JSON doit gérer ces cas
 * et DOIT pouvoir être rejouée après un incident — ce qu'une migration ne fait
 * pas confortablement. D'où un service + une commande, idempotents.
 *
 * ## Idempotence
 *
 * La clé d'idempotence est `(company_id, slug)` : le slug est dérivé du nom
 * d'affichage. Si une Brand existe déjà pour ce slug, on la MET À JOUR (les
 * couleurs ont pu changer depuis) au lieu d'en créer une seconde. Deux
 * exécutions produisent donc le même état.
 *
 * ## Ce que le backfill ne fait JAMAIS
 *
 * - Il ne supprime pas `companies.metadata.branding`. La double lecture est
 *   assumée : tant que tous les lecteurs n'ont pas migré, effacer l'ancienne
 *   clé rendrait des surfaces publiques orphelines.
 * - Il ne publie pas les Brands. Le backfill reconstitue l'identité, il ne
 *   décide pas de la mise en ligne — un tenant republie consciemment.
 * - Il ne crée pas de Brand pour une entreprise sans branding : une Brand sans
 *   identité serait un brouillon vide de plus.
 */
final class BrandBackfillService
{
    public function __construct(
        private readonly TransactionRunner $transactions,
    ) {}

    /**
     * Traite un lot de tenants.
     *
     * @param  int|null  $limit  borne du lot (null = tous)
     * @return array{processed: int, created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function run(?int $limit = null): array
    {
        $query = Company::query()->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $report = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($query->get() as $company) {
            $report['processed']++;

            try {
                $outcome = $this->backfillCompany($company);

                match ($outcome) {
                    'created' => $report['created']++,
                    'updated' => $report['updated']++,
                    default => $report['skipped']++,
                };
            } catch (\Throwable $e) {
                // Un tenant en erreur ne doit pas interrompre le lot : on
                // journalise et on continue, sinon un seul JSON malformé
                // bloquerait le backfill de tout le parc.
                $report['errors'][] = sprintf('%s: %s', (string) $company->id, $e->getMessage());
            }
        }

        return $report;
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    public function backfillCompany(Company $company): string
    {
        $metadata = is_array($company->metadata ?? null) ? $company->metadata : [];
        $branding = $metadata['branding'] ?? null;

        if (! is_array($branding)) {
            return 'skipped';
        }

        $name = $this->extractName($branding);

        if ($name === null) {
            return 'skipped';
        }

        $slug = Brand::normalizeSlug($name);

        if ($slug === null || Brand::isReservedSlug($slug)) {
            // Un nom qui ne produit pas de slug exploitable (ou qui entre en
            // collision avec une route) n'est pas réparable automatiquement :
            // le tenant devra choisir. On saute plutôt que d'inventer un slug.
            return 'skipped';
        }

        return $this->transactions->run(function () use ($company, $branding, $name, $slug): string {
            $existing = $this->findForCompany($company, $slug);

            $palette = BrandPalette::fromArray([
                'primary' => $branding['primary_color'] ?? null,
                'accent' => $branding['accent_color'] ?? null,
                'surface' => $branding['surface_color'] ?? null,
            ]);

            $identity = [
                'display_name' => $name,
                'logo_url' => $this->stringOrNull($branding['logo_url'] ?? null),
            ];

            if ($existing instanceof Brand) {
                // Mise à jour des couleurs et du logo : ils ont pu changer
                // depuis la dernière exécution. On ne touche NI au statut NI
                // au host — ce sont des décisions du tenant.
                $existing->palette = $palette->toArray();
                $existing->identity = array_merge(
                    is_array($existing->identity) ? $existing->identity : [],
                    $identity,
                );
                $existing->save();

                return 'updated';
            }

            $isFirst = $this->countForCompany($company) === 0;

            Brand::create([
                'company_id' => $company->id,
                'slug' => $slug,
                'name' => $name,
                'vertical' => BrandVertical::Generic->value,
                'status' => BrandStatus::Draft->value,
                'is_default' => $isFirst,
                'palette' => $palette->toArray(),
                'identity' => $identity,
            ]);

            return 'created';
        });
    }

    /**
     * Cherche une Brand du tenant SANS le scope global (commande console).
     */
    private function findForCompany(Company $company, string $slug): ?Brand
    {
        /** @var Brand|null $brand */
        $brand = Brand::crossTenantForSystemTask('backfill branding #9582')
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->first();

        return $brand;
    }

    private function countForCompany(Company $company): int
    {
        return Brand::crossTenantForSystemTask('backfill branding #9582')
            ->where('company_id', $company->id)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function extractName(array $branding): ?string
    {
        foreach (['display_name', 'brand_name', 'name'] as $key) {
            $value = $branding[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
