<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\Employee;
use Tests\TestCase;

/**
 * Issue #5588 (durcissement) : `two_fa_secret` et `two_fa_recovery_codes`
 * ne doivent JAMAIS apparaître dans une sérialisation d'Employee
 * (toArray/toJson) — une fuite exposerait le secret TOTP et les codes de
 * récupération (SuperAdmin les masquait déjà ; Employee non).
 */
class EmployeeHiddenAttributesTest extends TestCase
{
    public function test_two_fa_secret_and_recovery_codes_are_hidden_from_serialization(): void
    {
        $employee = new Employee();
        $employee->forceFill([
            'id' => 1,
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'password_hash' => 'hash-password',
            'two_fa_secret' => 'SECRET-TOTP',
            'two_fa_recovery_codes' => ['AAAA-1111', 'BBBB-2222'],
        ]);

        $serialized = $employee->toArray();

        $this->assertArrayNotHasKey('password_hash', $serialized);
        $this->assertArrayNotHasKey('two_fa_secret', $serialized, 'le secret TOTP ne doit pas être sérialisé');
        $this->assertArrayNotHasKey('two_fa_recovery_codes', $serialized, 'les codes de récupération ne doivent pas être sérialisés');
        $this->assertArrayHasKey('email', $serialized);
    }
}
