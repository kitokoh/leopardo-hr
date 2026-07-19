<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return response()->json([
            'data' => $user->tokens()->select('id', 'name', 'abilities', 'last_used_at', 'created_at')->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $user->createToken($validated['name'], ['api:access']);

        return response()->json([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'token' => $token->plainTextToken, // Seule fois où le token est renvoyé en clair
                'created_at' => $token->accessToken->created_at,
            ]
        ], 201);
    }

    public function destroy(Request $request, string $tokenId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $user->tokens()->where('id', $tokenId)->delete();

        return response()->json([], 204);
    }
}
