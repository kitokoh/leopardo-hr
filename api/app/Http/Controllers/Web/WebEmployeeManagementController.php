<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class WebEmployeeManagementController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService) {}

    public function create(): View
    {
        return view('employees.create');
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $employee = $this->employeeService->create($request->validated(), $actor);

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', 'Compte cree et invitation envoyee.');
    }
}
