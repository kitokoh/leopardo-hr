<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    public function create(array $payload): Employee
    {
        $password = Arr::pull($payload, 'password');
        $payload['password_hash'] = Hash::make($password);

        if (empty($payload['role'])) {
            $payload['role'] = 'employee';
        }

        $payload['status'] = 'active';

        return Employee::query()->create($payload);
    }

    public function update(Employee $actor, Employee $employee, array $payload): Employee
    {
        $isManager = $actor->isManager();

        if (! $isManager) {
            $payload = Arr::only($payload, ['first_name', 'last_name', 'email', 'password']);
        }

        if (array_key_exists('password', $payload) && $payload['password']) {
            $employee->password_hash = Hash::make($payload['password']);
        }
        unset($payload['password']);

        if (! $isManager) {
            unset($payload['role'], $payload['status'], $payload['matricule']);
        }

        $employee->fill($payload);
        $employee->save();

        return $employee;
    }

    public function archive(Employee $employee): Employee
    {
        $employee->status = 'archived';
        $employee->save();

        return $employee;
    }
}

