<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7496 — dashboard admin des conversions du funnel d'acquisition.
 *
 * Couvre : auth super-admin requise, taux de passage par étape (comptés en
 * parcours DISTINCTS — un renvoi de code ne gonfle pas l'étape), conversion
 * par jour et par source, et l'alerte livraison OTP (ratio vérifié/envoyé du
 * jour sous le seuil).
 */
class PlatformAcquisitionFunnelStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsSuperAdmin(): void
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Funnel',
            'email' => 'sa-funnel@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    private function insertEvent(string $event, string $correlationId, ?string $source, Carbon $occurredAt): void
    {
        DB::table('acquisition_funnel_events')->insert([
            'event' => $event,
            'correlation_id' => $correlationId,
            'source' => $source,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_stats_require_super_admin_auth(): void
    {
        $this->getJson('/api/v1/admin/funnel/stats')->assertStatus(401);
    }

    public function test_stats_aggregate_steps_days_and_sources(): void
    {
        $this->actingAsSuperAdmin();

        $today = now()->startOfDay()->addHours(10);
        $yesterday = now()->subDay()->startOfDay()->addHours(10);

        // Parcours A (facebook) : complet jusqu'à l'espace prêt, hier.
        foreach ([
            'signup_view',
            'signup_email_submitted',
            'signup_otp_sent',
            'signup_otp_verified',
            'space_provisioned',
        ] as $event) {
            $this->insertEvent($event, 'journey-a', 'facebook_ads', $yesterday);
        }
        // Renvoi de code sur le même parcours : l'étape ne doit compter qu'UN parcours.
        $this->insertEvent('signup_otp_sent', 'journey-a', 'facebook_ads', $yesterday->copy()->addMinutes(2));

        // Parcours B (direct) : abandonné après la vue, aujourd'hui.
        $this->insertEvent('signup_view', 'journey-b', null, $today);

        $data = $this->getJson('/api/v1/admin/funnel/stats?days=30')
            ->assertStatus(200)
            ->json('data');

        // Totaux : 2 parcours vus, 1 espace prêt.
        $this->assertSame(2, $data['totals']['journeys']);
        $this->assertSame(1, $data['totals']['provisioned']);
        $this->assertSame(0.5, $data['totals']['conversion_rate']);

        // Étapes : signup_otp_sent = 2 événements mais 1 seul parcours.
        $steps = collect((array) $data['steps'])->keyBy('event');
        $this->assertSame(2, $steps['signup_view']['journeys']);
        $this->assertSame(2, $steps['signup_otp_sent']['events']);
        $this->assertSame(1, $steps['signup_otp_sent']['journeys']);
        // Taux de passage vue → e-mail soumis : 1 parcours sur 2.
        $this->assertSame(0.5, $steps['signup_email_submitted']['rate_from_previous']);

        // Par source : facebook convertit à 100 %, le trafic sans source est « direct ».
        $bySource = collect((array) $data['by_source'])->keyBy('source');
        $this->assertSame(1.0, $bySource['facebook_ads']['conversion_rate']);
        $this->assertSame(0.0, $bySource['direct']['conversion_rate']);

        // Par jour : les deux journées apparaissent.
        $this->assertCount(2, $data['by_day']);
    }

    public function test_otp_delivery_alert_triggers_when_todays_ratio_drops(): void
    {
        $this->actingAsSuperAdmin();

        $today = now()->startOfDay()->addHours(9);
        for ($i = 0; $i < 6; $i++) {
            $this->insertEvent('signup_otp_sent', 'journey-'.$i, null, $today);
        }
        // Un seul code vérifié sur 6 envoyés : mailer probablement en échec.
        $this->insertEvent('signup_otp_verified', 'journey-0', null, $today);

        $alert = $this->getJson('/api/v1/admin/funnel/stats')
            ->assertStatus(200)
            ->json('data.alerts.otp_delivery');

        $this->assertSame(6, $alert['sent_today']);
        $this->assertSame(1, $alert['verified_today']);
        $this->assertTrue($alert['triggered']);
    }

    public function test_otp_delivery_alert_stays_quiet_under_the_minimum_volume(): void
    {
        $this->actingAsSuperAdmin();

        // 2 envois / 0 vérifié : volume trop faible pour conclure à une panne.
        $today = now()->startOfDay()->addHours(9);
        $this->insertEvent('signup_otp_sent', 'j1', null, $today);
        $this->insertEvent('signup_otp_sent', 'j2', null, $today);

        $alert = $this->getJson('/api/v1/admin/funnel/stats')
            ->assertStatus(200)
            ->json('data.alerts.otp_delivery');

        $this->assertFalse($alert['triggered']);
    }
}
