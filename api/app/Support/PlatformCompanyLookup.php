<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PlatformCompanyLookup
{
    public static function findOrFail(string $companyId): Company
    {
        DB::statement('SET search_path TO public');

        $row = DB::table(self::table())
            ->where('id', $companyId)
            ->first();

        if (! $row) {
            throw (new ModelNotFoundException)->setModel(Company::class, [$companyId]);
        }

        return (new Company)->newFromBuilder((array) $row);
    }

    private static function table(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
