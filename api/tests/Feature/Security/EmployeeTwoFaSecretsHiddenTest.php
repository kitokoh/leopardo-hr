<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use Tests\TestCase;

/**
 * Issue #5588 (audit sécurité 2026-08-26) — les secrets 2FA d'un Employee
 * (`two_fa_secret`, codes de récupération) ne doivent JAMAIS apparaître dans
 * une sérialisation (toArray / Resource / log), au même titre que le
 * password_hash. SuperAdmin masquait déjà two_fa_secret ; Employee non.
 */
class EmployeeTwoFaSecretsHiddenTest extends TestCase
{
    public function test_two_fa_secret_and_recovery_codes_are_hidden_from_serialization(): void
    {
        $employee = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => 'karim@twofa-hidden.test',
            'password_hash' => 'bcrypt-hash',
            'two_fa_secret' => 'JBSWY3DPEHPK3PXP',
            'two_fa_recovery_codes' => ['AAAA-BBBB-CCCC', 'DDDD-EEEE-FFFF'],
        ]);

        $serialized = $employee->toArray();

        $this->assertArrayNotHasKey('password_hash', $serialized);
        $this->assertArrayNotHasKey('two_fa_secret', $serialized);
        $this->assertArrayNotHasKey('two_fa_recovery_codes', $serialized);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) json_encode($serialized));
        $this->assertStringNotContainsString('AAAA-BBBB-CCCC', (string) json_encode($serialized));
    }

    public function test_attributes_remain_readable_on_the_model(): void
    {
        $employee = new Employee([
            'two_fa_secret' => 'JBSWY3DPEHPK3PXP',
            'two_fa_recovery_codes' => ['AAAA-BBBB-CCCC'],
        ]);

        // Le masquage ne concerne que la sérialisation : le service 2FA doit
        // continuer à lire le secret depuis le modèle.
        $this->assertSame('JBSWY3DPEHPK3PXP', $employee->two_fa_secret);
        $this->assertSame(['AAAA-BBBB-CCCC'], $employee->two_fa_recovery_codes);
    }
}
