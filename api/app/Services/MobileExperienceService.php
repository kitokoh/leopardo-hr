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
            'stage'         => $this->stageFor($employee),
            'app'           => $this->appContextFor($employee),
            'modules'       => $this->modulesFor($employee),
            'quick_actions' => $this->quickActionsFor($employee),
        ];
    }

    /**
     * Identify which mobile app this employee should be using.
     * This is used by the frontend to redirect to the right app on first login.
     *
     * @return array{id: string, name: string, deep_link_scheme: string}
     */
    private function appContextFor(Employee $employee): array
    {
        if (! $employee->isManager()) {
            return ['id' => 'employee', 'name' => 'Leopardo Employee', 'deep_link_scheme' => 'leopardo-employee'];
        }

        return match($employee->manager_role) {
            'principal'  => ['id' => 'manager',   'name' => 'Leopardo Manager',   'deep_link_scheme' => 'leopardo-manager'],
            'rh'         => ['id' => 'rh',         'name' => 'Leopardo RH',        'deep_link_scheme' => 'leopardo-rh'],
            'comptable'  => ['id' => 'comptable',  'name' => 'Leopardo Comptable', 'deep_link_scheme' => 'leopardo-comptable'],
            'marketing'  => ['id' => 'marketing',  'name' => 'Leopardo Marketing', 'deep_link_scheme' => 'leopardo-marketing'],
            'dept'       => ['id' => 'dept',       'name' => 'Leopardo Dept',      'deep_link_scheme' => 'leopardo-dept'],
            default      => ['id' => 'manager',    'name' => 'Leopardo Manager',   'deep_link_scheme' => 'leopardo-manager'],
        };
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

        if ($employee->isPrincipal()) {
            // Principal (Manager app) — full access + role management
            $modules[] = $this->module(
                key: 'team',
                title: 'Equipe',
                description: 'Piloter les employes, invitations et vues manager.',
                domain: 'rh',
                route: '/team',
                status: 'active',
            );
            $modules[] = $this->module(
                key: 'role_management',
                title: 'Gestion des roles',
                description: 'Nommer un RH, comptable, marketing ou chef de departement.',
                domain: 'rh',
                route: '/company/team-roles',
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
            $modules[] = $this->module(
                key: 'company_branding',
                title: 'Identite entreprise',
                description: 'Adapter le nom affiche, le logo et les couleurs de l espace client.',
                domain: 'rh',
                route: '/company/branding',
                status: 'active',
            );
            $modules[] = $this->module(
                key: 'dashboard_admin',
                title: 'Tableau de bord admin',
                description: 'Vue complete avec comptage des roles et activite.',
                domain: 'rh',
                route: '/dashboard/admin',
                status: 'active',
            );
        } elseif ($employee->isHr()) {
            // RH app — employee management, no role assignment
            $modules[] = $this->module(
                key: 'hr_employees',
                title: 'Employes',
                description: 'Ajouter, modifier et suivre les employes de l entreprise.',
                domain: 'rh',
                route: '/hr/employees',
                status: 'active',
            );
            $modules[] = $this->module(
                key: 'hr_team_overview',
                title: 'Vue equipe',
                description: 'Apercu rapide de toute l equipe et des contrats.',
                domain: 'rh',
                route: '/hr/team-overview',
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
                key: 'invitations',
                title: 'Invitations',
                description: 'Envoyer et suivre les invitations employes.',
                domain: 'rh',
                route: '/invitations',
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

        if ($employee->isPrincipal()) {
            // Manager app quick actions
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
                    key: 'role_management',
                    title: 'Roles',
                    description: 'Nommer RH, comptable, marketing.',
                    domain: 'rh',
                    icon: 'manage_accounts',
                    route: '/company/team-roles',
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
        } elseif ($employee->isHr()) {
            // RH app quick actions
            array_splice($actions, 1, 0, [
                $this->quickAction(
                    key: 'add_employee',
                    title: 'Ajouter employe',
                    description: 'Creer un nouvel employe.',
                    domain: 'rh',
                    icon: 'person_add',
                    route: '/hr/employees/new',
                ),
                $this->quickAction(
                    key: 'team_overview',
                    title: 'Equipe',
                    description: 'Vue rapide de l equipe.',
                    domain: 'rh',
                    icon: 'groups',
                    route: '/hr/team-overview',
                ),
                $this->quickAction(
                    key: 'absences',
                    title: 'Absences',
                    description: 'Valider les demandes d absence.',
                    domain: 'rh',
                    icon: 'event_busy',
                    route: '/absences',
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
