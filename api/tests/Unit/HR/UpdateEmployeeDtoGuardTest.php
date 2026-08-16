<?php

declare(strict_types=1);

namespace Tests\Unit\HR;

use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * #4609 — le DTO de mise à jour employé ne doit accepter que des payloads
 * validés par un FormRequest. Le fallback `$request->all()` acceptait un
 * `Request` brut et réactivait silencieusement le mass-assignment de
 * role/status/manager_role (contournement #3677/#4496).
 */
class UpdateEmployeeDtoGuardTest extends BaseTestCase
{
    public function test_from_request_rejects_raw_request(): void
    {
        // Un Request brut (sans validation) ne doit plus être accepté :
        // c'était le vecteur de mass-assignation silencieuse.
        $raw = new Request([], ['role' => 'manager', 'status' => 'active']);

        $this->expectException(\TypeError::class);

        // Violation de type INTENTIONNELLE (garde structurelle #4609) — le
        // TypeError est le comportement attendu.
        // @phpstan-ignore-next-line argument.type
        UpdateEmployeeDTO::fromRequest($raw);
    }

    public function test_from_request_static_type_does_not_allow_request(): void
    {
        // Garde structurelle : la signature interdit le `Request` générique.
        $reflection = new \ReflectionMethod(UpdateEmployeeDTO::class, 'fromRequest');
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $type = (string) $params[0]->getType();
        $this->assertStringNotContainsString('Illuminate\\Http\\Request', $type);
        $this->assertStringContainsString('UpdateEmployeeRequest', $type);
        $this->assertStringContainsString('UpdateProfileRequest', $type);
    }
}
