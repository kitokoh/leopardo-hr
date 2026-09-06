<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Privacy;

use App\AI\Privacy\AiCloudPolicy;
use Tests\TestCase;

/**
 * Issue #6853 — classification cloud / refus fail-closed.
 */
class AiCloudPolicyTest extends TestCase
{
    public function test_cloud_drivers_are_detected(): void
    {
        $policy = new AiCloudPolicy;

        $this->assertTrue($policy->isCloudDriver('groq'));
        $this->assertTrue($policy->isCloudDriver('openai'));
        $this->assertTrue($policy->isCloudDriver('claude'));
        $this->assertFalse($policy->isCloudDriver('fake'));
        $this->assertFalse($policy->isCloudDriver('ollama'));
    }

    public function test_cloud_allowed_fails_closed_without_company(): void
    {
        $this->assertFalse((new AiCloudPolicy)->cloudAllowed(null));
    }

    public function test_refusal_message_is_explicit(): void
    {
        $this->assertStringContainsString('ai_cloud_allowed', (new AiCloudPolicy)->refusalMessage());
    }
}
