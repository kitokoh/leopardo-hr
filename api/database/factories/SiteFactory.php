<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * #9898 — `Site` déclarait `use HasFactory` sans type de factory.
 *
 * PHPStan (level max, `missingType.generics`) signalait l'usage du générique
 * `HasFactory` sans son paramètre `TFactory`. La correction n'est pas de
 * taire l'erreur : `Site::factory()` n'avait **aucune** factory à résoudre,
 * donc l'appel échouait à l'exécution. Le type manquant révélait un trou
 * réel, pas un bruit d'analyse.
 *
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' — '.$this->faker->randomElement(['Siège', 'Agence', 'Dépôt', 'Point de vente']),
            'code' => strtoupper($this->faker->unique()->bothify('SITE-####')),
            'site_type' => $this->faker->randomElement(['office', 'store', 'restaurant', 'station']),
            'status' => Site::STATUS_ACTIVE,
            'address' => $this->faker->streetAddress(),
            'gps_radius_m' => 150,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => Site::STATUS_INACTIVE]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => Site::STATUS_ARCHIVED]);
    }
}
