<?php

namespace App\Providers;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
    }
}

