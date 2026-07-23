<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\TrialDayOneMail;
use App\Mail\TrialDaySevenMail;
use App\Mail\TrialDayThreeMail;
use App\Mail\TrialDripMail;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-I18N-006 — Localiser les emails transactionnels.
 *
 * Regression coverage for the trial drip Mailables that are actually
 * dispatched in production (SendTrialDripEmailJob + app:send-drip-emails):
 * the rendered subject must follow the recipient's locale (fr/en/ar/tr),
 * not the server default locale.
 */
class TrialDripEmailLocalizationTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function localeProvider(): array
    {
        return [
            'french' => ['fr'],
            'english' => ['en'],
            'arabic' => ['ar'],
            'turkish' => ['tr'],
        ];
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_trial_day_one_mail_subject_is_localized(string $locale): void
    {
        Mail::fake();

        $company = Company::factory()->create(['language' => 'fr']);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'preferred_language' => $locale,
        ]);

        $mailable = new TrialDayOneMail($company, trim($manager->first_name.' '.$manager->last_name), $manager->email, $locale);

        Mail::to($manager->email)->send($mailable);

        Mail::assertSent(TrialDayOneMail::class, function (TrialDayOneMail $mail) use ($locale) {
            $expected = __('emails.trial_day1_subject', [], $locale);

            return str_contains($mail->envelope()->subject, $expected);
        });
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_trial_day_three_mail_subject_is_localized(string $locale): void
    {
        Mail::fake();

        $company = Company::factory()->create(['language' => 'fr']);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'preferred_language' => $locale,
        ]);

        $mailable = new TrialDayThreeMail($company, trim($manager->first_name.' '.$manager->last_name), $locale);

        Mail::to($manager->email)->send($mailable);

        Mail::assertSent(TrialDayThreeMail::class, function (TrialDayThreeMail $mail) use ($locale) {
            $expected = __('emails.trial_day3_mail_subject', [], $locale);

            return str_contains($mail->envelope()->subject, $expected);
        });
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_trial_day_seven_mail_subject_is_localized(string $locale): void
    {
        Mail::fake();

        $company = Company::factory()->create(['language' => 'fr']);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'preferred_language' => $locale,
        ]);

        $mailable = new TrialDaySevenMail($company, trim($manager->first_name.' '.$manager->last_name), 5, $locale);

        Mail::to($manager->email)->send($mailable);

        Mail::assertSent(TrialDaySevenMail::class, function (TrialDaySevenMail $mail) use ($locale) {
            $expected = __('emails.trial_day7_subject', [], $locale);

            return str_contains($mail->envelope()->subject, $expected);
        });
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_trial_drip_mail_expiring_subject_is_localized(string $locale): void
    {
        Mail::fake();

        $company = Company::factory()->create(['language' => 'fr']);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'preferred_language' => $locale,
        ]);

        Mail::to($company->email)->send(new TrialDripMail($company, $manager, 'expiring'));

        Mail::assertSent(TrialDripMail::class, function (TrialDripMail $mail) use ($locale) {
            $expected = __('emails.trial_expiring_subject', ['appName' => config('app.name')], $locale);

            return $mail->build()->subject === $expected;
        });
    }
}
