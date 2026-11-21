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
            // #9744 — la factory ne tire AUCUNE contrainte métier au hasard.
            // Ces quatre colonnes valaient `optional()` : une fois sur deux la
            // promotion portait un seuil, un plafond ou une fenêtre aléatoires,
            // donc tout test qui ne les fixait pas devenait intermittent. Un
            // `min_order_minor` tire entre 500 et 20 000 refusait la commande de
            // 10 000 de `RestaurantPromotionCrudTest::test_valid_promotion_applies_and_increments_usage`
            // (422 « Order does not reach the promotion minimum. » au lieu de
            // 200) : ~1 échec sur 4 sans qu'aucun code produit n'ait changé, et
            // le même piège avait déjà coûté #9714 sur `RestaurantOrderBillTest`.
            // Une promotion créée par la factory est applicable SANS contrainte ;
            // un test qui veut un seuil, un plafond ou une fenêtre le déclare
            // explicitement. Même raisonnement que `is_active => true` (#8660).
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
