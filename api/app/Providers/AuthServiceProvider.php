<?php

namespace App\Providers;

use App\Models\AttendanceLog;
use App\Models\Cameras\Camera;
use App\Models\Cameras\CameraAccessToken;
use App\Models\Employee;
use App\Policies\AttendancePolicy;
use App\Policies\Cameras\CameraPolicy;
use App\Policies\EmployeePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(AttendanceLog::class, AttendancePolicy::class);
        Gate::policy(Camera::class, CameraPolicy::class);
        Gate::policy(CameraAccessToken::class, CameraPolicy::class);
    }
}
