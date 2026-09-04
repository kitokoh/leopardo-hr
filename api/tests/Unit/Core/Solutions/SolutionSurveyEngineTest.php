<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Solutions;

use App\Core\Solutions\Survey\SolutionSurveyEngine;
use App\Modules\Restaurant\Domain\Survey\RestaurantSurvey;
use Tests\TestCase;

/**
 * Moteur de suggestion des solutions sectorielles — tests purs (aucune DB).
 */
class SolutionSurveyEngineTest extends TestCase
{
    private SolutionSurveyEngine $engine;

    private RestaurantSurvey $survey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SolutionSurveyEngine;
        $this->survey = new RestaurantSurvey;
    }

    public function test_empty_answers_always_suggest_the_base_mobile_pack(): void
    {
        $result = $this->engine->suggest($this->survey, []);

        $this->assertSame(1, $result['total']);
        $this->assertSame('mobile_employee', $result['packages'][0]['key']);
        $this->assertSame('solutions.restaurant.reason.base', $result['packages'][0]['reason_key']);

        // Réponse absente = défaut de la question ('none') : ne jamais
        // suggérer un module optionnel sans réponse explicite (régression
        // détectée au smoke test 2026-09-01 : delivery était suggéré à tort).
        $keys = array_column($result['packages'], 'key');
        $this->assertNotContains('delivery', $keys);
        $this->assertNotContains('kiosk', $keys);
        $this->assertNotContains('payroll', $keys);
    }

    public function test_kiosk_profile_suggests_kiosk_and_edge(): void
    {
        $result = $this->engine->suggest($this->survey, [
            'attendance_device' => 'kiosk',
            'employee_count' => '1_5',
        ]);

        $keys = array_column($result['packages'], 'key');

        $this->assertContains('kiosk', $keys);
        $this->assertContains('edge', $keys);
        $this->assertNotContains('attendance_mobile', $keys);
    }

    public function test_full_restaurant_profile_builds_a_coherent_pack(): void
    {
        $result = $this->engine->suggest($this->survey, [
            'service_type' => 'mixte',
            'employee_count' => '21_50',
            'attendance_device' => 'biometric',
            'scheduling' => true,
            'payroll' => true,
            'accounting' => false,
            'delivery' => 'own',
            'reservations' => true,
            'inventory' => false,
            'loyalty' => true,
            'pos' => true,
        ]);

        $keys = array_column($result['packages'], 'key');

        foreach (['mobile_employee', 'mobile_manager', 'kiosk', 'edge', 'planning', 'payroll', 'delivery', 'reservations', 'loyalty', 'pos'] as $expected) {
            $this->assertContains($expected, $keys, "Package [$expected] manquant dans le pack suggéré.");
        }

        $this->assertNotContains('accounting', $keys);
        $this->assertNotContains('attendance_mobile', $keys);

        // Tri par priorité : le pack minimal (mobile_employee) doit rester en tête.
        $this->assertSame('mobile_employee', $result['packages'][0]['key']);
        $this->assertSame(count($keys), $result['total']);
    }

    public function test_every_reason_key_is_localized(): void
    {
        $result = $this->engine->suggest($this->survey, [
            'employee_count' => '50_plus',
            'attendance_device' => 'mobile',
            'scheduling' => true,
            'payroll' => true,
            'delivery' => 'platforms',
        ]);

        foreach ($result['packages'] as $package) {
            $this->assertStringStartsWith('solutions.restaurant.', $package['reason_key']);
        }
    }
}
