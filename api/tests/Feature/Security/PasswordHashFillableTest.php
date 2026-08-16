<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #4695 (audit 360° 2026-08-16) — password_hash n'est plus
 * mass-assignable sur User et SuperAdmin (aligné sur Employee #4496) :
 * un update($request->all()) ne peut plus surécrire le mot de passe.
 */
class PasswordHashFillableTest extends TestCase
{
    public function test_user_password_hash_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $user->update(['first_name' => 'Changed', 'password_hash' => Hash::make('evil')]);
        $user->refresh();

        $this->assertSame('Changed', $user->first_name);
        $this->assertFalse(Hash::check('evil', $user->password_hash), 'password_hash ne doit pas être rempli par mass-assignment.');
    }

    public function test_super_admin_password_hash_is_not_mass_assignable(): void
    {
        $admin = new SuperAdmin([
            'name' => 'Admin',
            'email' => 'mass-admin-'.uniqid().'@example.com',
        ]);
        // password_hash n'est pas fillable : assignation directe (hors
        // mass-assignment) pour initialiser la ligne.
        $admin->password_hash = Hash::make('original-password');
        $admin->save();

        $admin->update(['name' => 'Changed', 'password_hash' => Hash::make('evil')]);
        $admin->refresh();

        $this->assertSame('Changed', $admin->name);
        $this->assertFalse(Hash::check('evil', $admin->password_hash), 'password_hash ne doit pas être rempli par mass-assignment.');
        $this->assertTrue(Hash::check('original-password', $admin->password_hash));
    }
}
