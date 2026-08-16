<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Planning\Domain\Models\Task;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #3597 — défense en profondeur mass-assignment.
 *
 * Les champs sensibles (rôle, statut, company_id, secrets, verrouillage de
 * compte…) ne doivent JAMAIS réapparaître dans un $fillable : un futur
 * `->update($request->all())` permettrait élévation de rôle, changement de
 * statut ou déverrouillage de compte.
 *
 * Ce test échoue si l'un de ces champs est de nouveau mass-assignable.
 */
final class SensitiveFillableGuardTest extends TestCase
{
    /**
     * @return array<string, array{class-string, list<string>}>
     */
    public static function sensitiveFillableProvider(): array
    {
        return [
            'Employee' => [Employee::class, ['company_id', 'salary_base', 'role', 'manager_role', 'status', 'failed_login_attempts', 'locked_until', 'password_hash', 'biometric_face_reference_path', 'biometric_fingerprint_reference_path', 'email_bounced_at']],
            'User' => [User::class, ['status', 'email_verified_at', 'failed_login_attempts', 'locked_until']],
            'SuperAdmin' => [SuperAdmin::class, ['status', 'two_fa_secret']],
            'Department' => [Department::class, ['company_id']],
            'UserInvitation' => [UserInvitation::class, ['company_id', 'role', 'manager_role']],
            'SalaryAdvance' => [SalaryAdvance::class, ['status']],
            'Planning Task' => [Task::class, ['status', 'performance_score']],
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, string>  $forbidden
     */
    #[DataProvider('sensitiveFillableProvider')]
    public function test_sensitive_fields_are_not_mass_assignable(string $modelClass, array $forbidden): void
    {
        $fillable = (new $modelClass)->getFillable();
        $leaked = array_values(array_intersect($forbidden, $fillable));

        $this->assertSame(
            [],
            $leaked,
            sprintf(
                '[%s] champs sensibles mass-assignables : %s. Retirez-les de $fillable et assignez-les explicitement.',
                $modelClass,
                implode(', ', $leaked)
            )
        );
    }
}
