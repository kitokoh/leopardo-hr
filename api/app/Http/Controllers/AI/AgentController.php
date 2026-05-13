<?php

namespace App\Http\Controllers\AI;

use App\AI\AgentRunner;
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

        $result = $agent->execute(
            $request->user(),
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
            [
                'id' => 'new_employee_onboarding',
                'name' => 'Onboarding nouvel employe',
                'description' => 'Creer le profil, affecter le departement, envoyer les accreditations',
                'steps' => ['Creer le profil employe', 'Affecter au departement', 'Configurer les acces', 'Envoyer notification'],
            ],
        ];

        return response()->json(['data' => $workflows]);
    }
}
