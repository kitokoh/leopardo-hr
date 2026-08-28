<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\PiiRedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * MAT-009 (#5867) — aucune PII/secret n'apparaît dans les logs structurés.
 */
final class PiiRedactionProcessorTest extends TestCase
{
    private PiiRedactionProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new PiiRedactionProcessor();
    }

    public function test_it_redacts_values_under_sensitive_keys(): void
    {
        $record = $this->record([
            'password' => 'hunter2',
            'request_id' => 'keep-me',
        ]);

        $out = ($this->processor)($record);

        $this->assertSame(PiiRedactionProcessor::REDACTED, $out->context['password']);
        $this->assertSame('keep-me', $out->context['request_id']);
    }

    public function test_it_redacts_nested_sensitive_keys(): void
    {
        $record = $this->record([
            'context' => [
                'employee' => ['national_id' => '1990990123456', 'name' => 'Ada'],
                'api_key' => 'sk-live-123456',
            ],
        ]);

        $out = ($this->processor)($record);

        $this->assertSame(PiiRedactionProcessor::REDACTED, $out->context['context']['employee']['national_id']);
        $this->assertSame('Ada', $out->context['context']['employee']['name']);
        $this->assertSame(PiiRedactionProcessor::REDACTED, $out->context['context']['api_key']);
    }

    public function test_it_redacts_bearer_and_basic_authorization_values(): void
    {
        $record = $this->record([
            'authorization' => 'Bearer abc.def.ghi',
            'headers' => ['Authorization' => 'Basic dXNlcjpwYXNz'],
        ]);

        $out = ($this->processor)($record);

        $this->assertSame(PiiRedactionProcessor::REDACTED, $out->context['authorization']);
        $this->assertSame(PiiRedactionProcessor::REDACTED, $out->context['headers']['Authorization']);
    }

    public function test_it_redacts_key_value_patterns_in_the_message(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-08-28T00:00:00+00:00'),
            channel: 'structured',
            level: Level::Error,
            message: 'Login failed password=hunter2 for user=ada',
            context: [],
        );

        $out = ($this->processor)($record);

        $this->assertStringContainsString('password='.PiiRedactionProcessor::REDACTED, $out->message);
        $this->assertStringNotContainsString('hunter2', $out->message);
        $this->assertStringContainsString('user=ada', $out->message);
    }

    public function test_it_leaves_non_sensitive_context_untouched(): void
    {
        $record = $this->record([
            'method' => 'POST',
            'uri' => '/api/v1/attendance/check-in',
            'status' => 200,
            'duration_ms' => 42,
            'company_id' => '00000000-0000-0000-0000-000000000001',
        ]);

        $out = ($this->processor)($record);

        $this->assertSame('POST', $out->context['method']);
        $this->assertSame('/api/v1/attendance/check-in', $out->context['uri']);
        $this->assertSame(200, $out->context['status']);
        $this->assertSame(42, $out->context['duration_ms']);
    }

    public function test_it_handles_scalar_and_null_context_values(): void
    {
        $record = $this->record(['note' => null, 'count' => 3, 'flag' => true]);

        $out = ($this->processor)($record);

        $this->assertNull($out->context['note']);
        $this->assertSame(3, $out->context['count']);
        $this->assertTrue($out->context['flag']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2026-08-28T00:00:00+00:00'),
            channel: 'structured',
            level: Level::Info,
            message: 'http_request',
            context: $context,
        );
    }
}
