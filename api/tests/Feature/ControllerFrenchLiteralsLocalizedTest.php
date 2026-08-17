<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #4690 — Littéraux FR dans les contrôleurs API :
 * AttendanceController (heure de départ), IslamicCalendarController
 * (administrateur plateforme), PlatformCompanyRequestController (plan actif,
 * email de contact) et Company (mode schema Enterprise). Tous les messages
 * passent désormais par lang/errors.php ×4 locales — un tenant EN reçoit
 * de l'anglais, un tenant TR du turc, etc.
 */
class ControllerFrenchLiteralsLocalizedTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;
    }

    public function test_attendance_correction_check_out_localized(): void
    {
        $expected = [
            'fr' => "L'heure de départ doit être postérieure à l'heure d'arrivée.",
            'en' => 'Check-out time must be after check-in time.',
            'tr' => 'Çıkış saati, giriş saatinden sonra olmalıdır.',
            'ar' => 'يجب أن يكون وقت الخروج بعد وقت الدخول.',
        ];

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        foreach ($expected as $locale => $message) {
            $employee->forceFill(['preferred_language' => $locale])->save();

            $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/attendance/corrections', [
                    'date' => now()->toDateString(),
                    'reason' => 'Oubli de pointage (test #4690)',
                    'requested_check_in' => now()->subHour()->toIso8601String(),
                    // check-out antérieur au check-in → erreur dédiée #4690.
                    'requested_check_out' => now()->subHours(2)->toIso8601String(),
                ])
                ->assertStatus(422)
                ->assertJsonPath('errors.requested_check_out.0', $message);
        }
    }

    public function test_islamic_calendar_platform_admin_only_localized(): void
    {
        // La garde assertPlatformAdmin (abort 403) est atteignable par des
        // requêtes non super-admin ; le message doit suivre la locale.
        $expected = [
            'fr' => 'Seul un administrateur plateforme peut modifier le calendrier islamique.',
            'en' => 'Only a platform administrator can modify the Islamic calendar.',
            'tr' => 'İslami takvimi yalnızca platform yöneticisi değiştirebilir.',
            'ar' => 'يمكن لمسؤول المنصة فقط تعديل التقويم الإسلامي.',
        ];

        foreach ($expected as $locale => $message) {
            $this->assertSame($message, __(
                'errors.PLATFORM_ADMIN_ONLY',
                [],
                $locale
            ));
        }
    }

    public function test_company_request_approve_no_plan_localized(): void
    {
        $expected = [
            'fr' => 'Aucun plan actif disponible pour approuver cette demande.',
            'en' => 'No active plan is available to approve this request.',
            'tr' => 'Bu talebi onaylamak için uygun aktif bir plan yok.',
            'ar' => 'لا توجد خطة نشطة متاحة للموافقة على هذا الطلب.',
        ];

        foreach ($expected as $locale => $message) {
            $this->assertSame($message, __(
                'errors.NO_ACTIVE_PLAN_AVAILABLE',
                [],
                $locale
            ));
        }
    }

    public function test_company_request_approve_contact_email_localized(): void
    {
        $expected = [
            'fr' => 'Un email de contact est requis pour approuver cette demande.',
            'en' => 'A contact email is required to approve this request.',
            'tr' => 'Bu talebi onaylamak için bir iletişim e-postası gereklidir.',
            'ar' => 'البريد الإلكتروني للتواصل مطلوب للموافقة على هذا الطلب.',
        ];

        foreach ($expected as $locale => $message) {
            $this->assertSame($message, __(
                'errors.CONTACT_EMAIL_REQUIRED',
                [],
                $locale
            ));
        }
    }

    public function test_company_schema_enterprise_disabled_localized(): void
    {
        $expected = [
            'fr' => 'Mode schema Enterprise gelé. Contactez le support.',
            'en' => 'Enterprise schema mode is frozen. Contact support.',
            'tr' => 'Kurumsal şema modu donduruldu. Destek ile iletişime geçin.',
            'ar' => 'وضع مخطط المؤسسة مجمد. اتصل بالدعم.',
        ];

        foreach ($expected as $locale => $message) {
            $this->assertSame($message, __(
                'errors.ENTERPRISE_SCHEMA_FROZEN',
                [],
                $locale
            ));
        }
    }
}
