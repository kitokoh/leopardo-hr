<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Exceptions\EduSolutionInactiveException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profil rôle-aware de l'acteur EduManager — EDU-011 (issue #5827).
 *
 * Expose le rôle scolaire (direction / enseignant / employé) et le périmètre
 * (classes enseignées) pour piloter la navigation côté client sans dupliquer
 * la logique RBAC : l'API reste la seule source de vérité.
 */
class EduMeController extends Controller
{
    /**
     * GET /edu-manager/me — profil + rôle + périmètre (fail-closed 403 si la
     * solution est inactive).
     */
    public function show(Request $request): JsonResponse
    {
        if (! FeatureFlag::enabled('edumanager', currentCompany())) {
            throw new EduSolutionInactiveException;
        }

        /** @var Employee $actor */
        $actor = $request->user();

        $isAdmin = EduAccess::isAdmin($actor);
        $isTeacher = EduAccess::isTeacher($actor);

        return response()->json([
            'data' => [
                'employee_id' => $actor->id,
                'role' => $isAdmin ? 'admin' : ($isTeacher ? 'teacher' : 'employee'),
                'permissions' => [
                    'manage_all' => $isAdmin,
                    'manage_own_classes' => $isTeacher,
                    'view_own_children' => false,
                ],
                'teacher' => $isTeacher ? [
                    'class_ids' => EduAccess::teacherClassIds($actor)->values(),
                ] : null,
            ],
        ]);
    }
}
