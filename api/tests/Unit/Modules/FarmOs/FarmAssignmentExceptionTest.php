<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Exceptions\FarmAssignmentException;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T1.3 : contrat du refus d'affectation.
 *
 * Ces tests sont VOLONTAIREMENT sans base de données : ils verrouillent le
 * contrat d'erreur (code stable, jamais un libellé français en dur) que le
 * contrôleur traduit en 422. Le comportement complet (appartenance à
 * l'entreprise, notification des membres actifs) est couvert par les tests
 * Feature, qui exigent PostgreSQL et ne tournent donc qu'en CI.
 */
final class FarmAssignmentExceptionTest extends TestCase
{
    public function test_employee_not_in_company_exposes_a_stable_error_code(): void
    {
        $exception = FarmAssignmentException::employeeNotInCompany('assigned_to', 42);

        $this->assertSame('FARM_ASSIGNMENT_EMPLOYEE_NOT_IN_COMPANY', $exception->errorCode());
        $this->assertStringContainsString('assigned_to', $exception->getMessage());
        $this->assertStringContainsString('42', $exception->getMessage());
    }

    public function test_team_not_in_company_exposes_a_stable_error_code(): void
    {
        $exception = FarmAssignmentException::teamNotInCompany(7);

        $this->assertSame('FARM_ASSIGNMENT_TEAM_NOT_IN_COMPANY', $exception->errorCode());
        $this->assertStringContainsString('7', $exception->getMessage());
    }

    public function test_error_codes_are_not_user_facing_labels(): void
    {
        // Garde i18n (PA2-I18N-007 / #5432) : un code d'erreur ne doit jamais
        // contenir de caractère accentué (donc jamais de français en dur).
        foreach ([
            FarmAssignmentException::employeeNotInCompany('assigned_to', 1),
            FarmAssignmentException::teamNotInCompany(1),
        ] as $exception) {
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
