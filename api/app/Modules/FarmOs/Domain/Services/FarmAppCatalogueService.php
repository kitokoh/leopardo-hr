<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;

/**
 * Farm OS — T5.1 Centre « Mes logiciels » : catalogue applicatif.
 *
 * PRINCIPE DIRECTEUR : AUCUN FAUX LIEN DE TELECHARGEMENT. Ce service ne
 * fabrique jamais d'URL. Il decrit uniquement :
 *   - les OUTILS Farm OS actives pour l'entreprise (lus depuis les capacites
 *     reellement activees, pas depuis une liste souhaitee) ;
 *   - les PLATEFORMES reellement disponibles pour chaque outil ;
 *   - le MOYEN D'ACCES reel (application web de l'espace professionnel, ou
 *     application mobile existante si elle est declaree disponible).
 *
 * Si aucune application mobile dediee n'est declaree disponible, le catalogue
 * le DIT explicitement (`mobile_available: false`) au lieu de proposer un lien
 * mort. C'est l'exigence d'honnetete de la mission appliquee au catalogue.
 */
final class FarmAppCatalogueService
{
    /**
     * Plateforme web : toujours disponible, c'est l'espace professionnel
     * Leopardo lui-meme. Aucune URL n'est inventee ici : le front connait sa
     * propre route.
     */
    public const PLATFORM_WEB = 'web';

    /**
     * Plateforme mobile : disponible UNIQUEMENT si une application mobile
     * reellement publiee est declaree. Par defaut, Farm OS n'en declare aucune
     * et le catalogue l'indique honnetement.
     */
    public const PLATFORM_MOBILE = 'mobile';

    /**
     * Profils d'utilisateurs agricoles et les outils dont chacun a besoin.
     *
     * Sert a CONFIGURER l'acces par role : on n'affecte pas a un ouvrier
     * agricole les outils financiers dont il n'a pas l'usage.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_TOOL_MAP = [
        'owner' => ['parcels_crops', 'work_planning', 'workers', 'equipment', 'stocks_inputs', 'irrigation', 'livestock', 'purchases_suppliers', 'harvest_storage', 'sales_customers', 'finance_expenses', 'reports_kpis', 'notifications_assistant'],
        'farm_manager' => ['parcels_crops', 'work_planning', 'workers', 'equipment', 'stocks_inputs', 'irrigation', 'livestock', 'harvest_storage', 'reports_kpis', 'notifications_assistant'],
        'team_leader' => ['parcels_crops', 'work_planning', 'workers', 'equipment', 'stocks_inputs', 'harvest_storage', 'notifications_assistant'],
        'farm_worker' => ['parcels_crops', 'work_planning', 'notifications_assistant'],
        'stock_manager' => ['stocks_inputs', 'purchases_suppliers', 'harvest_storage', 'notifications_assistant'],
        'sales_manager' => ['sales_customers', 'harvest_storage', 'reports_kpis', 'notifications_assistant'],
        'accountant' => ['finance_expenses', 'reports_kpis', 'notifications_assistant'],
    ];

    /**
     * Catalogue des outils actives pour une entreprise.
     *
     * @return array{
     *     tools: list<array{code:string, label_key:string, platforms:list<string>, access:string, mobile_available:bool}>,
     *     mobile_app: array{available:bool, reason:string},
     *     roles: array<string, list<string>>
     * }
     */
    public function catalogue(Company $company): array
    {
        $active = $this->activeCapabilities($company);
        $tools = [];

        foreach (FarmCapability::cases() as $capability) {
            if (! in_array($capability->value, $active, true)) {
                continue;
            }

            $tools[] = [
                'code' => $capability->value,
                'label_key' => 'farm.capability.'.$capability->value,
                'platforms' => [self::PLATFORM_WEB],
                // Le moyen d'acces est DECRIT, jamais fabrique : l'espace
                // professionnel est la seule voie reelle tant qu'aucune
                // application mobile n'est declaree.
                'access' => 'workspace',
                'mobile_available' => false,
            ];
        }

        return [
            'tools' => $tools,
            'mobile_app' => $this->mobileAppStatus(),
            'roles' => $this->roleToolMap(),
        ];
    }

    /**
     * Statut de l'application mobile agricole dediee.
     *
     * Farm OS n'en declare AUCUNE : le catalogue le dit explicitement plutot que
     * de proposer un lien de telechargement inexistant. Une application mobile
     * ne pourra etre declaree ici qu'une fois reellement publiee et testee.
     *
     * @return array{available:bool, reason:string}
     */
    public function mobileAppStatus(): array
    {
        return [
            'available' => false,
            'reason' => 'FARM_MOBILE_APP_NOT_PUBLISHED',
        ];
    }

    /**
     * Outils recommandes pour un profil donne, restreints a ceux REELLEMENT
     * actives : on ne recommande jamais un outil que l'entreprise n'a pas.
     *
     * @return list<string>
     */
    public function toolsForRole(Company $company, string $role): array
    {
        $wanted = self::ROLE_TOOL_MAP[$role] ?? [];
        $active = $this->activeCapabilities($company);

        return array_values(array_filter(
            $wanted,
            static fn (string $code): bool => in_array($code, $active, true),
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    public function roleToolMap(): array
    {
        return self::ROLE_TOOL_MAP;
    }

    /**
     * Capacites REELLEMENT activees pour l'entreprise.
     *
     * Reutilise la porte serveur EXISTANTE ({@see FarmCapabilityGate}) : Farm OS
     * n'introduit pas une seconde lecture des capacites, et le catalogue ne peut
     * donc pas afficher un outil que la porte refuserait.
     *
     * @return list<string>
     */
    private function activeCapabilities(Company $company): array
    {
        return app(FarmCapabilityGate::class)->enabled($company);
    }
}
