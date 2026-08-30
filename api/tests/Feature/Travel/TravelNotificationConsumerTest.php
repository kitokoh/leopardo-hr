<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\CommunicationMail;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Infrastructure\Services\TravelNotificationConsumer;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-415 (#6067) — Notifications voyageur.
 *
 * Aucune notification sans canal configuré et consentement explicite :
 *  - contact absent → rien ;
 *  - contact sans consentement email → rien ;
 *  - contact avec consentement → email transactionnel ;
 *  - réservation créée par un employé du tenant → in-app BC-13.
 */
class TravelNotificationConsumerTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TravelNotificationConsumer $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;

        $this->consumer = app(TravelNotificationConsumer::class);
        Mail::fake();
    }

    private function makeBooking(array $overrides = []): TravelBooking
    {
        return app(TenantManager::class)->withinTenant($this->company, function () use ($overrides): TravelBooking {
            /** @var TravelBooking $booking */
            $booking = TravelBooking::factory()->create(array_merge([
                'status' => BookingStatus::CONFIRMED->value,
                'customer_contact_id' => null,
                'booked_by_user_id' => null,
            ], $overrides));

            return $booking;
        });
    }

    public function test_supports_only_known_event_types(): void
    {
        $this->assertTrue($this->consumer->supports('travel.booking.confirmed.v1'));
        $this->assertTrue($this->consumer->supports('travel.booking.cancelled.v1'));
        $this->assertTrue($this->consumer->supports('travel.payment.confirmed.v1'));
        $this->assertTrue($this->consumer->supports('travel.ticket.issued.v1'));
        $this->assertFalse($this->consumer->supports('travel.trip.published.v1'));
        $this->assertFalse($this->consumer->supports('travel.unknown.event'));
    }

    public function test_no_notification_without_contact(): void
    {
        $booking = $this->makeBooking();

        $this->consumer->handle(['booking_reference' => $booking->reference]);

        Mail::assertNothingSent();
    }

    public function test_no_email_without_consent(): void
    {
        $contact = app(TenantManager::class)->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create());
        $booking = $this->makeBooking(['customer_contact_id' => $contact->id]);

        $this->consumer->handle(['booking_reference' => $booking->reference]);

        Mail::assertNothingSent();
    }

    public function test_email_sent_when_consent_given(): void
    {
        $contact = app(TenantManager::class)->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create());
        $booking = $this->makeBooking(['customer_contact_id' => $contact->id]);

        $this->consumer->handle(['booking_reference' => $booking->reference]);

        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->hasTo($contact->email));
    }

    public function test_inapp_notification_to_booker_when_present(): void
    {
        $employee = app(TenantManager::class)->withinTenant($this->company, function (): Employee {
            /** @var Employee $employee */
            $employee = Employee::factory()->create([
                'company_id' => $this->company->id,
                'role' => 'manager',
                'manager_role' => 'principal',
            ]);

            return $employee;
        });

        $booking = $this->makeBooking(['booked_by_user_id' => $employee->id]);

        $this->consumer->handle(['booking_reference' => $booking->reference]);

        $this->assertTrue(
            AppNotification::query()->where('user_id', $employee->id)->exists(),
            'notification in-app créée via BC-13 pour l\'employé ayant créé la réservation',
        );
    }

    public function test_unknown_booking_is_permanent(): void
    {
        $this->expectException(PermanentOutboxException::class);

        $this->consumer->handle(['booking_reference' => 'GV-INCONNU0001']);
    }

    public function test_missing_reference_is_permanent(): void
    {
        $this->expectException(PermanentOutboxException::class);

        $this->consumer->handle([]);
    }
}
