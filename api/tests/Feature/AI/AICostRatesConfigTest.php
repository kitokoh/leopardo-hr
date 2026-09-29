<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\AIAuditLogger;
use Tests\TestCase;

/**
 * BOS-031 (#8221) — tarifs de coût externalisés dans `config/ai.php`
 * (`ai.costs.*`) : parité stricte avec les valeurs historiquement codées en
 * dur dans `AIAuditLogger::estimateCost()`, et prise en compte d'une
 * surcharge de configuration.
 */
class AICostRatesConfigTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function configRates(): array
    {
        /** @var array<string, mixed> $rates */
        $rates = config('ai.costs.rates', []);

        return $rates;
    }

    private function estimate(string $model, int $inputTokens, int $outputTokens): int
    {
        $logger = app(AIAuditLogger::class);
        $method = new \ReflectionMethod($logger, 'estimateCost');

        /** @var int $cents */
        $cents = $method->invoke($logger, 'openai', $model, $inputTokens, $outputTokens);

        return $cents;
    }

    public function test_config_defaults_match_the_historical_hardcoded_rates(): void
    {
        $rates = $this->configRates();

        $this->assertSame(['input' => 0.25, 'output' => 1.0], $rates['gpt-4o'] ?? null);
        $this->assertSame(['input' => 0.015, 'output' => 0.06], $rates['gpt-4o-mini'] ?? null);
        $this->assertSame(['input' => 0.3, 'output' => 1.5], $rates['claude-sonnet-4-20250514'] ?? null);

        /** @var array<string, float> $default */
        $default = config('ai.costs.default_rate', []);
        $this->assertSame(0.1, $default['input'] ?? null);
        $this->assertSame(0.3, $default['output'] ?? null);
    }

    public function test_cost_is_computed_from_the_configured_rate(): void
    {
        // 100k in + 100k out sur gpt-4o : 0.25 + 1.0 USD = 125 cents.
        $this->assertSame(125, $this->estimate('gpt-4o', 100_000, 100_000));

        // 100k in + 100k out sur gpt-4o-mini : 0.015 + 0.06 = 7.5 cents → 8.
        $this->assertSame(8, $this->estimate('gpt-4o-mini', 100_000, 100_000));

        // Claude : 0.3 + 1.5 = 180 cents.
        $this->assertSame(180, $this->estimate('claude-sonnet-4-20250514', 100_000, 100_000));
    }

    public function test_unknown_model_falls_back_to_the_default_rate(): void
    {
        // Défaut : 0.1 + 0.3 = 0.4 USD pour 100k + 100k = 40 cents.
        $this->assertSame(40, $this->estimate('modele-inconnu', 100_000, 100_000));
    }

    public function test_overriding_the_config_changes_the_estimated_cost(): void
    {
        config(['ai.costs.rates.gpt-4o' => ['input' => 1.0, 'output' => 1.0]]);

        $this->assertSame(200, $this->estimate('gpt-4o', 100_000, 100_000));
    }

    public function test_overriding_the_default_rate_changes_unknown_models_cost(): void
    {
        config(['ai.costs.default_rate' => ['input' => 0.5, 'output' => 0.5]]);

        $this->assertSame(100, $this->estimate('modele-inconnu', 100_000, 100_000));
    }
}
