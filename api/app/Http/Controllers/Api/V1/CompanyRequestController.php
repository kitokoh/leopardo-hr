<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = CompanyRequest::where('employee_id', $request->user()->id)->get();
        return new JsonResponse(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'sector' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:100'],

            'manager_name' => ['required', 'string', 'max:150'],
            'manager_id_card' => ['nullable', 'string', 'max:50'],
            'manager_phone' => ['nullable', 'string', 'max:30'],

            'notes' => ['nullable', 'string'],
        ]);

        $companyRequest = CompanyRequest::create([
            'employee_id' => $request->user()->id,
            'company_name' => $validated['company_name'],
            'sector' => $validated['sector'],
            'country' => $validated['country'],
            'city' => $validated['city'],
            'manager_name' => $validated['manager_name'],
            'manager_id_card' => $validated['manager_id_card'] ?? null,
            'manager_phone' => $validated['manager_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return new JsonResponse(['data' => $companyRequest], 201);
    }
}
