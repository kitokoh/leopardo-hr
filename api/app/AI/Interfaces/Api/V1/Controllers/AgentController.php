<?php

declare(strict_types=1);

namespace App\AI\Interfaces\Api\V1\Controllers;

use App\AI\AgentRunner;
use App\Core\Auth\Domain\Models\Employee;
use App\AI\Orchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string',
            'max_steps' => 'nullable|integer|min:1|max:20',
        ]);

        $orchestrator = app(Orchestrator::class);
        $agent = new AgentRunner($orchestrator, $validated['max_steps'] ?? 10);

        $user = $request->user();

        if (! $user instanceof Employee) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $result = $agent->execute(
            $user,
            $validated['task'],
            $validated['conversation_id'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    public function workflows(): JsonResponse
    {
        $workflows = [
            [
                'id' => 'prepare_payroll',
                'name' => 'Preparer la paie du mois',
                'description' => 'Collecte les donnees, calcule la paie, genere un resume',
                'steps' => ['Collecter les donnees de pointage', 'Verifier les absences', 'Calculer les salaires', 'Generer le resume'],
            ],
            [
                'id' => 'weekly_report',
                'name' => 'Rapport hebdomadaire',
                'description' => 'Anomalies, absences, effectifs de la semaine',
                'steps' => ['Collecter les anomalies', 'Compter les absences', 'Resume des effectifs', 'Generer le rapport'],
            ],
        ];

        return response()->json(['data' => $workflows]);
    }
}
