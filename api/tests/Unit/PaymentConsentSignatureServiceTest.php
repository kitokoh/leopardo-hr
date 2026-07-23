<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Domain\Models\PaymentItem;
use App\Modules\Payroll\Infrastructure\Services\PaymentConsentSignatureService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PA2-PAY-016 - Simple digital signature (timestamped consent + document
 * hash), covered independently of the HTTP layer.
 */
class PaymentConsentSignatureServiceTest extends TestCase
{
    public function test_hash_is_deterministic_for_identical_payloads(): void
    {
        $service = new PaymentConsentSignatureService;
        $item = $this->makeItem();
        $confirmedAt = Carbon::parse('2026-07-23T10:15:00Z');

        $hashA = $service->hash($service->buildPayload($item, $confirmedAt, 'v1'));
        $hashB = $service->hash($service->buildPayload($item, $confirmedAt, 'v1'));

        $this->assertSame($hashA, $hashB);
        $this->assertSame(64, strlen($hashA));
    }

    public function test_hash_changes_when_amount_is_tampered_with(): void
    {
        $service = new PaymentConsentSignatureService;
        $confirmedAt = Carbon::parse('2026-07-23T10:15:00Z');

        $original = $this->makeItem(['amount' => 1000.00]);
        $tampered = $this->makeItem(['amount' => 1000.01]);

        $originalHash = $service->hash($service->buildPayload($original, $confirmedAt, 'v1'));
        $tamperedHash = $service->hash($service->buildPayload($tampered, $confirmedAt, 'v1'));

        $this->assertNotSame($originalHash, $tamperedHash);
    }

    public function test_verify_detects_hash_mismatch(): void
    {
        $service = new PaymentConsentSignatureService;
        $item = $this->makeItem();
        $confirmedAt = Carbon::parse('2026-07-23T10:15:00Z');

        $this->assertFalse($service->verify($item, $confirmedAt, 'v1', null));
        $this->assertFalse($service->verify($item, $confirmedAt, 'v1', 'not-a-valid-hash'));

        $validHash = $service->hash($service->buildPayload($item, $confirmedAt, 'v1'));
        $this->assertTrue($service->verify($item, $confirmedAt, 'v1', $validHash));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeItem(array $overrides = []): PaymentItem
    {
        $item = new PaymentItem(array_merge([
            'company_id' => 'company-uuid',
            'payment_batch_id' => 1,
            'employee_id' => 42,
            'amount' => 1000.00,
            'currency' => 'DZD',
        ], $overrides));

        $item->id = 7;

        return $item;
    }
}
