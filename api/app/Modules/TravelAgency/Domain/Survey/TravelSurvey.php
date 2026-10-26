<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Survey;

use App\Core\Solutions\Survey\Contracts\SolutionSurvey;

/**
 * Questionnaire de pre-qualification de la solution Agence de voyage.
 *
 * BOS-009 (#8227) — TravelAgency etait le seul vertical pilote de
 * l'onboarding SANS survey : `GET /api/v1/solutions/travelagency/survey`
 * repondait 404 `SOLUTION_SURVEY_NOT_FOUND`, donc le parcours « je decris
 * mon activite -> workspace pret » ne pouvait pas demarrer pour une agence.
 *
 * Conventions identiques a {@see \App\Modules\Restaurant\Domain\Survey\RestaurantSurvey}
 * (et a `TradeSurvey`) : le backend est la source de verite des QUESTIONS et
 * des REGLES, le front ne fait qu'afficher les cles i18n.
 *
 * Specificites agence de voyage retenues :
 *  - `agency_type` : une agence de billetterie et un tour-operateur n'ont pas
 *    le meme besoin (le second vend des forfaits, donc du reporting et du
 *    multi-devise) ;
 *  - `offices_count` : le reseau multi-bureaux est la norme du metier, il
 *    conditionne le manager mobile et le planning ;
 *  - `online_sales` : la vente en ligne (billetterie publique) est le coeur
 *    de la verticale, pas une option marginale.
 *
 * @see docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md
 */
final class TravelSurvey implements SolutionSurvey
{
    public function code(): string
    {
        return 'travelagency';
    }

    public function name(): string
    {
        return 'Agence de voyage';
    }

    public function questions(): array
    {
        return [
            [
                'key' => 'agency_type',
                'type' => 'select',
                'label_key' => 'solutions.travelagency.question.agency_type',
                'options' => [
                    ['value' => 'ticketing', 'label_key' => 'solutions.travelagency.question.agency_type.ticketing'],
                    ['value' => 'tour_operator', 'label_key' => 'solutions.travelagency.question.agency_type.tour_operator'],
                    ['value' => 'mixte', 'label_key' => 'solutions.travelagency.question.agency_type.mixte'],
                ],
                'default' => 'ticketing',
            ],
            [
                'key' => 'employee_count',
                'type' => 'select',
                'label_key' => 'solutions.travelagency.question.employee_count',
                'options' => [
                    ['value' => '1_5', 'label_key' => 'solutions.travelagency.question.employee_count.1_5'],
                    ['value' => '6_20', 'label_key' => 'solutions.travelagency.question.employee_count.6_20'],
                    ['value' => '21_50', 'label_key' => 'solutions.travelagency.question.employee_count.21_50'],
                    ['value' => '50_plus', 'label_key' => 'solutions.travelagency.question.employee_count.50_plus'],
                ],
                'default' => '1_5',
            ],
            [
                'key' => 'offices_count',
                'type' => 'select',
                'label_key' => 'solutions.travelagency.question.offices_count',
                'options' => [
                    ['value' => '1', 'label_key' => 'solutions.travelagency.question.offices_count.1'],
                    ['value' => '2_5', 'label_key' => 'solutions.travelagency.question.offices_count.2_5'],
                    ['value' => '6_plus', 'label_key' => 'solutions.travelagency.question.offices_count.6_plus'],
                ],
                'default' => '1',
            ],
            [
                'key' => 'attendance_device',
                'type' => 'select',
                'label_key' => 'solutions.travelagency.question.attendance_device',
                'options' => [
                    ['value' => 'none', 'label_key' => 'solutions.travelagency.question.attendance_device.none'],
                    ['value' => 'mobile', 'label_key' => 'solutions.travelagency.question.attendance_device.mobile'],
                    ['value' => 'kiosk', 'label_key' => 'solutions.travelagency.question.attendance_device.kiosk'],
                    ['value' => 'biometric', 'label_key' => 'solutions.travelagency.question.attendance_device.biometric'],
                ],
                'default' => 'none',
            ],
            [
                'key' => 'scheduling',
                'type' => 'bool',
                'label_key' => 'solutions.travelagency.question.scheduling',
                'default' => false,
            ],
            [
                'key' => 'payroll',
                'type' => 'bool',
                'label_key' => 'solutions.travelagency.question.payroll',
                'default' => false,
            ],
            [
                'key' => 'accounting',
                'type' => 'bool',
                'label_key' => 'solutions.travelagency.question.accounting',
                'default' => false,
            ],
            [
                'key' => 'online_sales',
                'type' => 'bool',
                'label_key' => 'solutions.travelagency.question.online_sales',
                'default' => true,
            ],
            [
                'key' => 'multi_currency',
                'type' => 'bool',
                'label_key' => 'solutions.travelagency.question.multi_currency',
                'default' => false,
            ],
            [
                'key' => 'reporting',
                'type' => 'bool',
                'label_key' => 'solutions.travelagency.question.reporting',
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
                'label_key' => 'solutions.travelagency.package.mobile_employee',
                'app' => 'employee',
                'download' => 'apk',
                'required' => true,
            ],
            'mobile_manager' => [
                'key' => 'mobile_manager',
                'type' => 'mobile',
                'label_key' => 'solutions.travelagency.package.mobile_manager',
                'app' => 'manager',
                'download' => 'apk',
            ],
            'attendance_mobile' => [
                'key' => 'attendance_mobile',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.attendance_mobile',
                'download' => null,
            ],
            'kiosk' => [
                'key' => 'kiosk',
                'type' => 'device',
                'label_key' => 'solutions.travelagency.package.kiosk',
                'download' => 'guide',
            ],
            'edge' => [
                'key' => 'edge',
                'type' => 'edge',
                'label_key' => 'solutions.travelagency.package.edge',
                'download' => 'edge_install',
            ],
            'planning' => [
                'key' => 'planning',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.planning',
                'download' => null,
            ],
            'payroll' => [
                'key' => 'payroll',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.payroll',
                'download' => null,
            ],
            'accounting' => [
                'key' => 'accounting',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.accounting',
                'download' => null,
            ],
            'online_shop' => [
                'key' => 'online_shop',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.online_shop',
                'download' => null,
            ],
            'multi_currency' => [
                'key' => 'multi_currency',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.multi_currency',
                'download' => null,
            ],
            'reporting' => [
                'key' => 'reporting',
                'type' => 'module',
                'label_key' => 'solutions.travelagency.package.reporting',
                'download' => null,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            // Pack minimal : l'app employe est toujours necessaire.
            [
                'package' => 'mobile_employee',
                'priority' => 100,
                'when' => static fn (array $answers): bool => true,
                'reason_key' => 'solutions.travelagency.reason.base',
            ],
            // Une equipe > 5 implique un manager qui pilote depuis son mobile.
            [
                'package' => 'mobile_manager',
                'priority' => 90,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['employee_count'] ?? ''),
                    ['6_20', '21_50', '50_plus'],
                    true
                ),
                'reason_key' => 'solutions.travelagency.reason.manager',
            ],
            // Reseau multi-bureaux : le suivi par bureau devient necessaire.
            [
                'package' => 'reporting',
                'priority' => 85,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['offices_count'] ?? '1'),
                    ['2_5', '6_plus'],
                    true
                ),
                'reason_key' => 'solutions.travelagency.reason.multi_office',
            ],
            // Pointage depuis l'app mobile.
            [
                'package' => 'attendance_mobile',
                'priority' => 80,
                'when' => static fn (array $answers): bool => (string) ($answers['attendance_device'] ?? '') === 'mobile',
                'reason_key' => 'solutions.travelagency.reason.attendance_mobile',
            ],
            // Pointage sur borne : kiosque + noeud Edge on-prem.
            [
                'package' => 'kiosk',
                'priority' => 80,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['attendance_device'] ?? ''),
                    ['kiosk', 'biometric'],
                    true
                ),
                'reason_key' => 'solutions.travelagency.reason.kiosk',
            ],
            [
                'package' => 'edge',
                'priority' => 75,
                'when' => static fn (array $answers): bool => in_array(
                    (string) ($answers['attendance_device'] ?? ''),
                    ['kiosk', 'biometric'],
                    true
                ),
                'reason_key' => 'solutions.travelagency.reason.edge',
            ],
            // Planning d'equipe (comptoirs a couvrir).
            [
                'package' => 'planning',
                'priority' => 70,
                'when' => static fn (array $answers): bool => (bool) ($answers['scheduling'] ?? false),
                'reason_key' => 'solutions.travelagency.reason.scheduling',
            ],
            [
                'package' => 'payroll',
                'priority' => 70,
                'when' => static fn (array $answers): bool => (bool) ($answers['payroll'] ?? false),
                'reason_key' => 'solutions.travelagency.reason.payroll',
            ],
            [
                'package' => 'accounting',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['accounting'] ?? false),
                'reason_key' => 'solutions.travelagency.reason.accounting',
            ],
            // Billetterie publique : le coeur de la verticale.
            [
                'package' => 'online_shop',
                'priority' => 65,
                'when' => static fn (array $answers): bool => (bool) ($answers['online_sales'] ?? false),
                'reason_key' => 'solutions.travelagency.reason.online_sales',
            ],
            // Multi-devise : pertinent des qu'on vend hors zone monetaire locale.
            [
                'package' => 'multi_currency',
                'priority' => 60,
                'when' => static fn (array $answers): bool => (bool) ($answers['multi_currency'] ?? false),
                'reason_key' => 'solutions.travelagency.reason.multi_currency',
            ],
            // Un tour-operateur vend des forfaits : le reporting est structurel.
            [
                'package' => 'reporting',
                'priority' => 55,
                'when' => static fn (array $answers): bool => (string) ($answers['agency_type'] ?? '') === 'tour_operator',
                'reason_key' => 'solutions.travelagency.reason.tour_operator',
            ],
        ];
    }
}
