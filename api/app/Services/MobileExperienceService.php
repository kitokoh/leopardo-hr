<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Construit une experience mobile coherente a partir du role utilisateur
 * et des modules reellement exposes par l API.
 */
class MobileExperienceService
{
    /**
     * @return array{
     *     stage: string,
     *     modules: list<array{
     *         key: string,
     *         title: string,
     *         description: string,
     *         domain: string,
     *         route: string|null,
     *         status: string
     *     }>,
     *     quick_actions: list<array{
     *         key: string,
     *         title: string,
     *         description: string,
     *         domain: string,
     *         icon: string,
     *         route: string
     *     }>
     * }
     */
    public function for(Employee $employee): array
    {
        return [
            'stage' => $this->stageFor($employee),
            'modules' => $this->modulesFor($employee),
            'quick_actions' => $this->quickActionsFor($employee),
        ];
    }

    private function stageFor(Employee $employee): string
    {
        $count = data_get($employee->extra_data, 'app_actions_count');

        return is_numeric($count) && (int) $count < 10 ? 'new' : 'regular';
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     domain: string,
     *     route: string|null,
     *     status: string
     * }>
     */
    private function modulesFor(Employee $employee): array
    {
        $modules = [
            $this->module(
                key: 'attendance',
                title: 'Pointage',
                description: 'Pointer, suivre la journee et verifier les heures du mois.',
                domain: 'rh',
                route: '/attendance',
                status: 'active',
            ),
            $this->module(
                key: 'absences',
                title: 'Absences',
                description: 'Consulter et declarer les absences disponibles pour votre role.',
                domain: 'rh',
                route: '/absences',
                status: 'active',
            ),
            $this->module(
                key: 'salary_advances',
                title: 'Avances',
                description: 'Demander, suivre ou valider les avances sur salaire.',
                domain: 'rh',
                route: '/salary-advances',
                status: 'active',
            ),
            $this->module(
                key: 'payrolls',
                title: 'Paie',
                description: 'Retrouver vos bulletins et elements de paie.',
                domain: 'rh',
                route: '/payrolls',
                status: 'active',
            ),
            $this->module(
                key: 'evaluations',
                title: 'Evaluations',
                description: 'Suivre les retours manager et les evaluations collaborateurs.',
                domain: 'rh',
                route: '/evaluations',
                status: 'active',
            ),
            $this->module(
                key: 'notifications',
                title: 'Notifications',
                description: 'Retrouver les alertes et annonces utiles a votre activite.',
                domain: 'rh',
                route: '/notifications',
                status: 'active',
            ),
            $this->module(
                key: 'cabinet',
                title: 'Placard',
                description: 'Rangez et partagez vos documents importants : diplomes, CV et plus.',
                domain: 'rh',
                route: '/cabinet',
                status: 'active',
            ),
        ];

        if ($employee->hasManagerRole('principal', 'rh') || $employee->isPrincipal() || $employee->isHr()) {
            $modules[] = $this->module(
                key: 'team',
                title: 'Equipe',
                description: 'Piloter les employes, invitations et vues manager.',
                domain: 'rh',
                route: '/team',
                status: 'active',
            );
            $modules[] = $this->module(
                key: 'schedules',
                title: 'Horaires',
                description: 'Definir pauses, tolerances et heures supplementaires.',
                domain: 'rh',
                route: '/schedules',
                status: 'active',
            );
            $modules[] = $this->module(
                key: 'tasks',
                title: 'Taches',
                description: 'Assigner les missions du jour et suivre la performance terrain.',
                domain: 'rh',
                route: '/tasks',
                status: 'active',
            );
        }

        $modules[] = $this->module(
            key: 'finance',
            title: 'Finance',
            description: 'Module prevu par la vision produit, encore en preparation.',
            domain: 'finance',
            route: null,
            status: 'coming_soon',
        );

        $modules[] = $this->module(
            key: 'cameras',
            title: 'Securite',
            description: 'Surveillance et securite seront actives dans une phase ulterieure.',
            domain: 'security',
            route: null,
            status: 'coming_soon',
        );

        $modules[] = $this->module(
            key: 'leo_ai',
            title: 'Leo IA',
            description: 'La conversation assistee par Leo arrive dans une phase suivante.',
            domain: 'ia',
            route: null,
            status: 'coming_soon',
        );

        return $modules;
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     domain: string,
     *     icon: string,
     *     route: string
     * }>
     */
    private function quickActionsFor(Employee $employee): array
    {
        $actions = [
            $this->quickAction(
                key: 'attendance',
                title: 'Pointer',
                description: 'Demarrer ou terminer la journee.',
                domain: 'rh',
                icon: 'fingerprint',
                route: '/attendance',
            ),
            $this->quickAction(
                key: 'monthly_summary',
                title: 'Mon mois',
                description: 'Voir heures, supplementaires et estime.',
                domain: 'rh',
                icon: 'stacked_bar_chart',
                route: '/me/monthly',
            ),
            $this->quickAction(
                key: 'history',
                title: 'Historique',
                description: 'Relire les pointages precedents.',
                domain: 'rh',
                icon: 'history',
                route: '/history',
            ),
            $this->quickAction(
                key: 'modules',
                title: 'Modules RH',
                description: 'Ouvrir les modules actifs de votre entreprise.',
                domain: 'rh',
                icon: 'dashboard_customize',
                route: '/modules',
            ),
        ];

        if ($employee->hasManagerRole('principal', 'rh') || $employee->isPrincipal() || $employee->isHr()) {
            array_splice($actions, 2, 0, [
                $this->quickAction(
                    key: 'team',
                    title: 'Equipe',
                    description: 'Piloter employes et invitations.',
                    domain: 'rh',
                    icon: 'group',
                    route: '/team',
                ),
                $this->quickAction(
                    key: 'schedules',
                    title: 'Horaires',
                    description: 'Ajuster les regles de temps.',
                    domain: 'rh',
                    icon: 'schedule',
                    route: '/schedules',
                ),
                $this->quickAction(
                    key: 'tasks',
                    title: 'Taches',
                    description: 'Assigner le travail du jour.',
                    domain: 'rh',
                    icon: 'task_alt',
                    route: '/tasks',
                ),
            ]);
        }

        $actions[] = $this->quickAction(
            key: 'settings',
            title: 'Parametres',
            description: 'Profil, langue et securite.',
            domain: 'rh',
            icon: 'settings',
            route: '/settings',
        );

        return $actions;
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     domain: string,
     *     route: string|null,
     *     status: string
     * }
     */
    private function module(
        string $key,
        string $title,
        string $description,
        string $domain,
        ?string $route,
        string $status,
    ): array {
        return compact('key', 'title', 'description', 'domain', 'route', 'status');
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     domain: string,
     *     icon: string,
     *     route: string
     * }
     */
    private function quickAction(
        string $key,
        string $title,
        string $description,
        string $domain,
        string $icon,
        string $route,
    ): array {
        return compact('key', 'title', 'description', 'domain', 'icon', 'route');
    }
}
