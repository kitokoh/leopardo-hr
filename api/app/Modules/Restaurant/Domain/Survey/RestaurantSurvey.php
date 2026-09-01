<?php

declare(strict_types=1);

namespace App\Modules\Restaurant\Domain\Survey;

use App\Core\Solutions\Survey\Contracts\SolutionSurvey;

/**
 * Questionnaire de pré-qualification de la solution Restaurant.
 *
 * Les libellés (label_key / reason_key) sont des clés i18n localisées par
 * le front — le backend reste la source de vérité des QUESTIONS et des
 * RÈGLES, le front ne fait qu'afficher.
 *
 * Conventions :
 *  - une règle = une ligne déclarative {package, priority, when, reason_key} ;
 *  - `when` reçoit les réponses brutes (clés de questions) ;
 *  - les packages marqués `required: true` sont toujours suggérés (le pack
 *    minimal, ex. l'app employé) ;
 *  - les modules « futur » (stock, delivery, pos, reservations) sont déjà
 *    dans le catalogue pour que le front puisse les afficher « bientôt »,
 *    mais le manifest ne les active pas encore côté serveur.
 *
 * @see docs/architecture/RESTAURANT_SOLUTION_SURVEY.md
 */
final class RestaurantSurvey implements SolutionSurvey
{
    public function code(): string
    {
        return 'restaurant';
    }

    public function questions(): array
    {
        return [
            [
                'key' => 'service_type',
                'type' => 'select',
                'label_key' => 'solutions.restaurant.question.service_type',
                'options' => [
                    ['value' => 'sur_place', 'label_key' => 'solutions.restaurant.question.service_type.sur_place'],
                    ['value' => 'a_emporter', 'label_key' => 'solutions.restaurant.question.service_type.a_emporter'],
                    ['value' => 'mixte', 'label_key' => 'solutions.restaurant.question.service_type.mixte'],
                ],
                'default' => 'mixte',
            ],
            [
                'key' => 'employee_count',
                'type' => 'select',
                'label_key' => 'solutions.restaurant.question.employee_count',
                'options' => [
                    ['value' => '1_5', 'label_key' => 'solutions.restaurant.question.employee_count.1_5'],
                    ['value' => '6_20', 'label_key' => 'solutions.restaurant.question.employee_count.6_20'],
                    ['value' => '21_50', 'label_key' => 'solutions.restaurant.question.employee_count.21_50'],
                    ['value' => '50_plus', 'label_key' => 'solutions.restaurant.question.employee_count.50_plus'],
                ],
                'default' => '1_5',
            ],
            [
                'key' => 'attendance_device',
                'type' => 'select',
                'label_key' => 'solutions.restaurant.question.attendance_device',
                'options' => [
                    ['value' => 'none', 'label_key' => 'solutions.restaurant.question.attendance_device.none'],
                    ['value' => 'mobile', 'label_key' => 'solutions.restaurant.question.attendance_device.mobile'],
                    ['value' => 'kiosk', 'label_key' => 'solutions.restaurant.question.attendance_device.kiosk'],
                    ['value' => 'biometric', 'label_key' => 'solutions.restaurant.question.attendance_device.biometric'],
                ],
                'default' => 'none',
            ],
            [
                'key' => 'scheduling',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.scheduling',
                'default' => false,
            ],
            [
                'key' => 'payroll',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.payroll',
                'default' => false,
            ],
            [
                'key' => 'accounting',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.accounting',
                'default' => false,
            ],
            [
                'key' => 'delivery',
                'type' => 'select',
                'label_key' => 'solutions.restaurant.question.delivery',
                'options' => [
                    ['value' => 'none', 'label_key' => 'solutions.restaurant.question.delivery.none'],
                    ['value' => 'platforms', 'label_key' => 'solutions.restaurant.question.delivery.platforms'],
                    ['value' => 'own', 'label_key' => 'solutions.restaurant.question.delivery.own'],
                ],
                'default' => 'none',
            ],
            [
                'key' => 'reservations',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.reservations',
                'default' => false,
            ],
            [
                'key' => 'inventory',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.inventory',
                'default' => false,
            ],
            [
                'key' => 'loyalty',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.loyalty',
                'default' => false,
            ],
            [
                'key' => 'pos',
                'type' => 'bool',
                'label_key' => 'solutions.restaurant.question.pos',
                'default' => false,
            ],
        ];
    }

    public function packages(): array
    {
        return [
            'mobile_employee' => [
                'key' => 'mobile_employee',
                'type' => 'mobile',
                'label_key' => 'solutions.restaurant.package.mobile_employee',
                'app' => 'employee',
                'download' => 'apk',
                'required' => true,
            ],
            'mobile_manager' => [
                'key' => 'mobile_manager',
                'type' => 'mobile',
                'label_key' => 'solutions.restaurant.package.mobile_manager',
                'app' => 'manager',
                'download' => 'apk',
            ],
            'attendance_mobile' => [
                'key' => 'attendance_mobile',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.attendance_mobile',
                'download' => null,
            ],
            'kiosk' => [
                'key' => 'kiosk',
                'type' => 'device',
                'label_key' => 'solutions.restaurant.package.kiosk',
                'download' => 'guide',
            ],
            'edge' => [
                'key' => 'edge',
                'type' => 'edge',
                'label_key' => 'solutions.restaurant.package.edge',
                'download' => 'edge_install',
            ],
            'planning' => [
                'key' => 'planning',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.planning',
                'download' => null,
            ],
            'payroll' => [
                'key' => 'payroll',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.payroll',
                'download' => null,
            ],
            'accounting' => [
                'key' => 'accounting',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.accounting',
                'download' => null,
            ],
            'delivery' => [
                'key' => 'delivery',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.delivery',
                'download' => null,
            ],
            'reservations' => [
                'key' => 'reservations',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.reservations',
                'download' => null,
            ],
            'inventory' => [
                'key' => 'inventory',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.inventory',
                'download' => null,
            ],
            'loyalty' => [
                'key' => 'loyalty',
                'type' => 'module',
                'label_key' => 'solutions.restaurant.package.loyalty',
                'download' => null,
            ],
            'pos' => [
                'key' => 'pos',
                'type' => 'device',
                'label_key' => 'solutions.restaurant.package.pos',
                'download' => 'guide',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            // Pack minimal : l'app employé est toujours nécessaire.
            [
                'package' => 'mobile_employee',
                'priority' => 100,
                'when' => static fn (array $answers): bool => true,
                'reason_key' => 'solutions.restaurant.reason.base',
            ],
            // Une équipe > 5 implique un manager qui pilote depuis son mobile.
            [
                'package' => 'mobile_manager',
                'priority' => 90,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['employee_count'] ?? ''),
                    ['6_20', '21_50', '50_plus'],
                    true
                ),
                'reason_key' => 'solutions.restaurant.reason.manager',
            ],
            // Pointage depuis l'app mobile.
            [
                'package' => 'attendance_mobile',
                'priority' => 80,
                'when' => static fn (array $answers): bool => (string) ($answers['attendance_device'] ?? '') === 'mobile',
                'reason_key' => 'solutions.restaurant.reason.attendance_mobile',
            ],
            // Pointage sur borne : kiosque ZKTeco + nœud Edge on-prem.
            [
                'package' => 'kiosk',
                'priority' => 80,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['attendance_device'] ?? ''),
                    ['kiosk', 'biometric'],
                    true
                ),
                'reason_key' => 'solutions.restaurant.reason.kiosk',
            ],
            [
                'package' => 'edge',
                'priority' => 75,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['attendance_device'] ?? ''),
                    ['kiosk', 'biometric'],
                    true
                ),
                'reason_key' => 'solutions.restaurant.reason.edge',
            ],
            // Planning d'équipe.
            [
                'package' => 'planning',
                'priority' => 70,
                'when' => static fn (array $answers): bool => (bool) ($answers['scheduling'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.scheduling',
            ],
            // Paie internalisée (≥ 6 salariés c'est vite rentable).
            [
                'package' => 'payroll',
                'priority' => 70,
                'when' => static fn (array $answers): bool => (bool) ($answers['payroll'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.payroll',
            ],
            [
                'package' => 'accounting',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['accounting'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.accounting',
            ],
            [
                'package' => 'delivery',
                'priority' => 60,
                // Réponse absente = 'none' (défaut de la question) — ne jamais
                // suggérer la livraison sans réponse explicite.
                'when' => static fn (array $answers): bool => (string) ($answers['delivery'] ?? 'none') !== 'none',
                'reason_key' => 'solutions.restaurant.reason.delivery',
            ],
            [
                'package' => 'reservations',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['reservations'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.reservations',
            ],
            [
                'package' => 'inventory',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['inventory'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.inventory',
            ],
            [
                'package' => 'loyalty',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['loyalty'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.loyalty',
            ],
            [
                'package' => 'pos',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['pos'] ?? false),
                'reason_key' => 'solutions.restaurant.reason.pos',
            ],
        ];
    }
}
