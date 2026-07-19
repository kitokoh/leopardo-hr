<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\NotPrivateUrl;
use PHPUnit\Framework\TestCase;

class NotPrivateUrlTest extends TestCase
{
    public function test_rejects_private_and_reserved_ip_literals(): void
    {
        $this->assertFalse(NotPrivateUrl::isPublicHost('127.0.0.1'));
        $this->assertFalse(NotPrivateUrl::isPublicHost('10.1.2.3'));
        $this->assertFalse(NotPrivateUrl::isPublicHost('172.16.0.1'));
        $this->assertFalse(NotPrivateUrl::isPublicHost('192.168.1.1'));
        $this->assertFalse(NotPrivateUrl::isPublicHost('169.254.169.254'));
        $this->assertFalse(NotPrivateUrl::isPublicHost('::1'));
    }

    public function test_rejects_localhost_hostnames(): void
    {
        $this->assertFalse(NotPrivateUrl::isPublicHost('localhost'));
        $this->assertFalse(NotPrivateUrl::isPublicHost('foo.localhost'));
    }

    public function test_accepts_public_ip_literal(): void
    {
        $this->assertTrue(NotPrivateUrl::isPublicHost('8.8.8.8'));
    }

    public function test_fails_closed_for_unresolvable_hostname(): void
    {
        $this->assertFalse(NotPrivateUrl::isPublicHost('this-host-does-not-exist.invalid'));
    }

    public function test_validate_rejects_non_https_scheme(): void
    {
        $rule = new NotPrivateUrl;
        $failed = null;

        $rule->validate('url', 'http://example.com', function (string $message) use (&$failed) {
            $failed = $message;

            return new class
            {
                public function translate(): void {}
            };
        });

        $this->assertNotNull($failed);
    }
}
