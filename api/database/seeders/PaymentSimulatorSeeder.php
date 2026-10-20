<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use Illuminate\Database\Seeder;

/**
 * BC-38 PAIEMENTS (#8684) — instance simulateur prete a l'emploi.
 *
 * Le type « simulateur » est enregistre en CODE (`ProviderTypeRegistry`) ; ce
 * seeder ne cree que la CONFIGURATION : une instance globale active, couvrant
 * tous les pays et toutes les devises, avec un taux de succes de 100 %.
 *
 * C'est exactement ce que ferait un admin depuis le Superadmin — et c'est ce
 * qui prouve le design : le simulateur n'est pas un cas particulier du code,
 * c'est une ligne de configuration.
 *
 * Idempotent : rejouable sans creer de doublon.
 */
class PaymentSimulatorSeeder extends Seeder
{
    public function run(): void
    {
        PaymentProviderInstance::query()->updateOrCreate(
            [
                'company_id' => null,
                'type_code' => SimulatorRail::CODE,
                'display_name' => 'Simulateur (global)',
            ],
            [
                'countries' => [],
                'currencies' => [],
                'status' => ProviderInstanceStatus::Active->value,
                'config' => [
                    'success_rate' => 100,
                    'webhook_success_rate' => 100,
                    'delay_ms' => 0,
                ],
                'priority' => 100,
            ],
        );
    }
}
