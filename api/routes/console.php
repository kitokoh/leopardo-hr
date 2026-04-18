<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('super-admin:reset-password {email} {password}', function (string $email, string $password) {
    DB::statement("SET search_path TO public");

    $affected = DB::table('super_admins')
        ->where('email', $email)
        ->update([
            'password_hash' => Hash::make($password),
        ]);

    if ($affected === 0) {
        $this->error("Aucun super admin trouvé pour {$email}");
        return 1;
    }

    $this->info("Mot de passe super admin mis à jour pour {$email}");
    return 0;
})->purpose('Reset a super admin password safely');
