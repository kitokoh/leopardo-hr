<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GlobalEmailUnique implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreEmployeeId = null) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $table = DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';

        // Security check: ensure lookup table exists before querying
        if (DB::getDriverName() === 'pgsql') {
            $exists = DB::selectOne("select to_regclass('public.user_lookups') as table_name");
            if ($exists?->table_name === null) {
                return;
            }
        } elseif (! Schema::hasTable('user_lookups')) {
            return;
        }

        $query = DB::table($table)->where('email', $value);

        if ($this->ignoreEmployeeId) {
            $query->where('employee_id', '!=', $this->ignoreEmployeeId);
        }

        if ($query->exists()) {
            $fail('Cet email est déjà utilisé par un utilisateur sur la plateforme (GLOBAL_COLLISION).');
        }
    }
}
