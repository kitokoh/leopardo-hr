<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\PromotionDiscountType;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPromotion>
 */
class RestaurantPromotionFactory extends Factory
{
    protected $model = RestaurantPromotion::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('PROMO-###')),
            'title' => $this->faker->unique()->words(3, true),
            'discount_type' => PromotionDiscountType::PERCENT->value,
            'value_minor' => $this->faker->numberBetween(100, 5000),
            // #9744 — les CONTRAINTES METIER ne sont plus tirees au hasard.
            // `min_order_minor`, `max_uses`, `starts_at` et `ends_at` etaient
            // tires par `optional()` : toute fixture qui ne les fixait pas
            // heritait d'une promotion parfois inapplicable (seuil de panier
            // non atteint, plafond d'usage deja consomme, fenetre de validite
            // fermee) et le test echouait ~1 fois sur 4 sans qu'aucune ligne
            // de code n'ait change (`RestaurantPromotionCrudTest::test_valid_
            // promotion_applies_and_increments_usage`, 422 « Order does not
            // reach the promotion minimum »). Meme famille que #8660 pour
            // `is_active` : une promotion creee par la factory est TOUJOURS
            // applicable ; un test qui veut un seuil, un plafond ou une
            // fenetre le declare explicitement.
            'min_order_minor' => null,
            'starts_at' => null,
            'ends_at' => null,
            'max_uses' => null,
            'used_count' => 0,
            // #8660 — `is_active` était tiré au hasard (`boolean(80)`). Toute
            // fixture qui ne le fixait pas explicitement devenait donc
            // intermittente : le service rejette une promotion inactive, et le
            // test échouait ~1 fois sur 5 sans qu'aucun code n'ait changé
            // (`RestaurantPromotionTest::percent promotion computed server
            // side`). Une promotion créée par la factory est active par
            // défaut ; un test qui veut l'inverse le déclare explicitement.
            'is_active' => true,
        ];
    }
}
