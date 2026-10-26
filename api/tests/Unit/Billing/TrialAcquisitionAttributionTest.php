<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Modules\Billing\Application\Services\TrialAcquisitionAttribution;
use PHPUnit\Framework\TestCase;

/**
 * #8523 (point 2) — l'attribution d'acquisition d'un essai doit être
 * conservée, et non seulement journalisée.
 *
 * Ces tests sont volontairement unitaires (aucune base, aucun tenant) : la
 * normalisation est la partie qui peut régresser silencieusement, et elle doit
 * rester vérifiable sans infra.
 */
final class TrialAcquisitionAttributionTest extends TestCase
{
    public function test_it_keeps_every_declared_attribution_field(): void
    {
        $attribution = (new TrialAcquisitionAttribution)->fromPayload([
            'source' => 'campaign_oct_2026',
            'landing_path' => '/p/tarifs',
            'referrer' => 'https://news.example.com/article',
            'utm_source' => 'linkedin',
            'utm_medium' => 'paid',
            'utm_campaign' => 'q4-hr',
        ]);

        $this->assertSame('campaign_oct_2026', $attribution['source']);
        $this->assertSame('/p/tarifs', $attribution['landing_path']);
        $this->assertSame('https://news.example.com/article', $attribution['referrer']);
        $this->assertSame([
            'utm_source' => 'linkedin',
            'utm_medium' => 'paid',
            'utm_campaign' => 'q4-hr',
        ], $attribution['utm']);
    }

    public function test_it_falls_back_to_self_service_trial_when_source_is_absent(): void
    {
        $attribution = (new TrialAcquisitionAttribution)->fromPayload([]);

        $this->assertSame(TrialAcquisitionAttribution::DEFAULT_SOURCE, $attribution['source']);
        $this->assertNull($attribution['referrer']);
        $this->assertNull($attribution['landing_path']);
        $this->assertSame([], $attribution['utm']);
    }

    public function test_it_ignores_blank_values_instead_of_storing_empties(): void
    {
        $attribution = (new TrialAcquisitionAttribution)->fromPayload([
            'source' => '   ',
            'landing_path' => '',
            'utm_source' => '  ',
        ]);

        $this->assertSame(TrialAcquisitionAttribution::DEFAULT_SOURCE, $attribution['source']);
        $this->assertNull($attribution['landing_path']);
        $this->assertArrayNotHasKey('utm_source', $attribution['utm']);
    }

    public function test_it_accepts_alternative_landing_and_referrer_keys(): void
    {
        $attribution = (new TrialAcquisitionAttribution)->fromPayload([
            'landing_page' => '/p/trial',
            'referrer_url' => 'https://partner.example.com',
        ]);

        $this->assertSame('/p/trial', $attribution['landing_path']);
        $this->assertSame('https://partner.example.com', $attribution['referrer']);
    }

    public function test_it_rejects_non_scalar_values(): void
    {
        $attribution = (new TrialAcquisitionAttribution)->fromPayload([
            'source' => ['nested' => 'array'],
            'landing_path' => 42,
            'utm_medium' => null,
        ]);

        $this->assertSame(TrialAcquisitionAttribution::DEFAULT_SOURCE, $attribution['source']);
        $this->assertNull($attribution['landing_path']);
        $this->assertSame([], $attribution['utm']);
    }

    public function test_it_bounds_value_length(): void
    {
        $attribution = (new TrialAcquisitionAttribution)->fromPayload([
            'source' => str_repeat('a', 500),
        ]);

        $this->assertSame(120, mb_strlen($attribution['source']));
    }
}
