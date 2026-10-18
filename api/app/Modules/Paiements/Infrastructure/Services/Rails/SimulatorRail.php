<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services\Rails;

use App\Modules\Paiements\Domain\Contracts\PaymentRailInterface;
use App\Modules\Paiements\Domain\DTOs\CashInRequest;
use App\Modules\Paiements\Domain\DTOs\CashOutRequest;
use App\Modules\Paiements\Domain\DTOs\RailResult;
use App\Modules\Paiements\Domain\DTOs\WebhookPayload;

/**
 * BC-38 PAIEMENTS (#8684) — rail « simulateur » (mock configurable).
 *
 * Premier type livre, et le seul de ce lot : il permet aux BC Cagnottes, VTC et
 * Boutique de developper et tester sans integration reelle.
 *
 * Point capital : ce rail emprunte **exactement le meme chemin de code** que les
 * futurs fournisseurs reels. Il n'existe aucun `if simulateur` dans le ledger ni
 * dans l'API — le simulateur n'est qu'une implementation de plus de
 * `PaymentRailInterface`, choisie par la configuration.
 *
 * Configuration (chiffree dans l'instance) :
 *   - `success_rate`         : 0-100, pourcentage de succes (defaut 100)
 *   - `webhook_success_rate` : 0-100, pourcentage de succes des webhooks (defaut 100)
 *   - `delay_ms`             : latence simulee en millisecondes (defaut 0)
 *   - `fail_reason`          : message d'echec simule
 *
 * La decision est **deterministe** (derivee de la cle d'idempotence) : un meme
 * appel rejoue donne le meme resultat, ce qui rend les tests reproductibles.
 */
class SimulatorRail implements PaymentRailInterface
{
    public const CODE = 'simulator';

    public function code(): string
    {
        return self::CODE;
    }

    public function initierCashIn(CashInRequest $request): RailResult
    {
        return $this->decide($request->config, $request->idempotencyKey, 'cash_in');
    }

    public function initierCashOut(CashOutRequest $request): RailResult
    {
        return $this->decide($request->config, $request->idempotencyKey, 'cash_out');
    }

    public function traiterWebhook(WebhookPayload $payload): RailResult
    {
        $rate = $this->intConfig($payload->config, 'webhook_success_rate', 100);

        if ($this->roll($payload->externalReference, $rate)) {
            return RailResult::succeeded($payload->externalReference, ['simulated' => true]);
        }

        return RailResult::failed(
            $this->stringConfig($payload->config, 'fail_reason', 'echec simule (webhook)'),
            ['simulated' => true],
        );
    }

    public function verifierStatut(string $externalReference): RailResult
    {
        return RailResult::succeeded($externalReference, ['simulated' => true]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function decide(array $config, string $idempotencyKey, string $operation): RailResult
    {
        $delayMs = $this->intConfig($config, 'delay_ms', 0);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $rate = $this->intConfig($config, 'success_rate', 100);

        if ($this->roll($idempotencyKey, $rate)) {
            return RailResult::succeeded(
                'sim_'.substr(hash('sha256', $operation.'|'.$idempotencyKey), 0, 24),
                ['simulated' => true],
            );
        }

        return RailResult::failed(
            $this->stringConfig($config, 'fail_reason', 'echec simule'),
            ['simulated' => true],
        );
    }

    /**
     * Tirage deterministe : meme entree -> meme resultat.
     */
    private function roll(string $seed, int $rate): bool
    {
        if ($rate >= 100) {
            return true;
        }

        if ($rate <= 0) {
            return false;
        }

        return (crc32($seed) % 100) < $rate;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function intConfig(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
