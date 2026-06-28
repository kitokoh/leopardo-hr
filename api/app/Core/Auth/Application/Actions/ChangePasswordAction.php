<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Use Case : Changement de mot de passe.
 *
 * @throws DomainException  When the current password is incorrect.
 */
final class ChangePasswordAction
{
    public function execute(Employee $employee, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $employee->password_hash)) {
            throw new class extends DomainException {
                public function errorCode(): string { return 'INVALID_CURRENT_PASSWORD'; }
                public function statusCode(): int   { return 422; }
            };
        }

        $employee->password_hash = Hash::make($newPassword);
        $employee->save();
    }
}
