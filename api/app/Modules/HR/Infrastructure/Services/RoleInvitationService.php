<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

class RoleInvitationService
{
    /**
     * Sous-roles manager acceptes par l'API (#9334).
     *
     * `principal` en est volontairement ABSENT : c'est le proprietaire du
     * tenant, non assignable depuis l'ecran Equipe. Les FormRequests
     * l'ajoutent explicitement (`principal` + cette liste), afin qu'un seul
     * endroit porte la liste des roles attribuables — avant #9334, la meme
     * enumeration etait recopiee en dur dans DEUX FormRequests, si bien que
     * les clients web et mobile avaient chacun derive de leur cote (web 5
     * roles, mobile 4, base 10).
     *
     * @var list<string>
     */
    public const ASSIGNABLE_MANAGER_ROLES = ['rh', 'dept', 'comptable', 'superviseur', 'marketing'];

    /**
     * Valeurs acceptees par la validation `manager_role` (#9334).
     *
     * `principal` est ajoute ici : la liste des roles ATTRIBUABLES l'exclut
     * (proprietaire du tenant) mais l'API doit continuer de l'accepter pour
     * les transitions existantes.
     *
     * @return list<string>
     */
    public static function allowedManagerRoles(): array
    {
        return array_merge(['principal'], self::ASSIGNABLE_MANAGER_ROLES);
    }

    /**
     * Returns the deep link URL for downloading the app based on manager_role.
     * Links use universal links / app store links with a fallback.
     */
    public static function getAppDownloadLink(string $role, string $managerRole, string $platform = 'both'): array
    {
        // #4180 : le lien iOS provient de la config (env LEOPARDO_IOS_APP_LINKS) ;
        // null tant que l'app n'est pas publiée sur l'App Store. Ne jamais
        // réintroduire d'identifiant placeholder id000000000*.
        /** @var array<string, string|null> $iosLinks */
        $iosLinks = config('services.mobile_app_links.ios', []);

        // #6942 : mapping aligné sur le catalogue réel des apps (android
        // applicationId) et sur MobileExperienceService::appContextFor (T120) —
        // un manager sans app dédiée distribuée (principal, dept, superviseur,
        // comptable, marketing…) reçoit l'app Manager ; seuls `rh` et l'employé
        // sans rôle manager ont leurs apps dédiées. Les packages fantômes
        // com.leopardo.admin / com.leopardo.comptable n'existent dans aucune app.
        return match (true) {
            $managerRole === 'rh' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.rh',
                'ios' => $iosLinks['rh'] ?? null,
                'name' => 'Leopardo RH',
                'deep_link_scheme' => 'leopardo-rh',
            ],
            $managerRole === 'employee' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.employee',
                'ios' => $iosLinks['employee'] ?? null,
                'name' => 'Leopardo Employee',
                'deep_link_scheme' => 'leopardo-employee',
            ],
            default => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.manager',
                'ios' => $iosLinks['manager'] ?? $iosLinks['principal'] ?? null,
                'name' => 'Leopardo Manager',
                'deep_link_scheme' => 'leopardo-manager',
            ],
        };
    }

    /**
     * Returns the label for the manager_role, translated according to the
     * currently active application locale (see App::setLocale() callers).
     */
    public static function getRoleLabel(string $managerRole): string
    {
        $key = match ($managerRole) {
            'principal' => 'role_label_principal',
            'rh' => 'role_label_rh',
            'comptable' => 'role_label_comptable',
            'marketing' => 'role_label_marketing',
            'dept' => 'role_label_dept',
            default => 'role_label_default',
        };

        return __("emails.{$key}");
    }
}
