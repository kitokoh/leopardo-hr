<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreEmployeeRequest;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\EmployeeService;
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
