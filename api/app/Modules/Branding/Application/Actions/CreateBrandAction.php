<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application\Actions;

use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\Palette\BrandPalette;
use App\Shared\Contracts\Persistence\TransactionRunner;

/**
 * Crée une Brand pour le tenant courant (BC-41, #9578).
 *
 * ## Ce que l'action garantit
 *
 * 1. **Le slug est normalisé et validé** avant toute écriture — un slug
 *    réservé ou non normalisable lève, il ne devient jamais une URL bancale.
 * 2. **La palette est construite par le domaine**, donc `on_primary` est
 *    calculé. L'action ne laisse jamais le client fournir `on_primary`.
 * 3. **La première Brand d'un tenant devient la Brand par défaut.** Sans cela,
 *    un tenant qui crée sa première enseigne n'aurait aucune Brand par défaut,
 *    et ses Pages existantes n'auraient plus de repli.
 * 4. **La création est transactionnelle** : la vérification « est-ce la
 *    première ? » et l'insertion doivent voir le même état, sinon deux
 *    créations concurrentes produiraient deux défauts — que l'index partiel
 *    refuserait, mais avec une erreur SQL au lieu d'un message métier.
 */
final class CreateBrandAction
{
    public function __construct(
        private readonly TransactionRunner $transactions,
    ) {}

    /**
     * @param  array{
     *   slug: string,
     *   name: string,
     *   vertical?: string,
     *   palette?: array{primary?: string, accent?: string, surface?: string},
     *   identity?: array<string, mixed>,
     *   surface?: array<string, mixed>,
     *   host?: string|null
     * }  $input
     */
    /**
     * @param  array{
     *     slug: string,
     *     name: string,
     *     vertical?: string,
     *     palette?: array{primary?: string, accent?: string, surface?: string},
     *     identity?: array<string, mixed>,
     *     surface?: array<string, mixed>,
     *     host?: string|null
     * }  $input
     */
    public function execute(array $input): Brand
    {
        $slug = Brand::assertValidSlug($input['slug']);

        $vertical = BrandVertical::tryFrom($input['vertical'] ?? BrandVertical::Generic->value)
            ?? BrandVertical::Generic;

        $paletteInput = $input['palette'] ?? [];
        $palette = BrandPalette::of(
            primary: is_string($paletteInput['primary'] ?? null) ? $paletteInput['primary'] : '#0F766E',
            accent: is_string($paletteInput['accent'] ?? null) ? $paletteInput['accent'] : '#0D9488',
            surface: is_string($paletteInput['surface'] ?? null) ? $paletteInput['surface'] : '#F8FAFC',
        );

        $host = isset($input['host']) && is_string($input['host'])
            ? \App\Modules\Branding\Domain\Services\BrandResolver::normalizeHost($input['host'])
            : null;

        return $this->transactions->run(function () use ($slug, $vertical, $palette, $input, $host): Brand {
            $isFirst = Brand::query()->count() === 0;

            /** @var Brand $brand */
            $brand = Brand::create([
                'slug' => $slug,
                'name' => $input['name'],
                'vertical' => $vertical->value,
                'status' => BrandStatus::Draft->value,
                'is_default' => $isFirst,
                'palette' => $palette->toArray(),
                'identity' => $input['identity'] ?? null,
                'surface' => $input['surface'] ?? null,
                'host' => $host,
            ]);

            return $brand;
        });
    }
}
