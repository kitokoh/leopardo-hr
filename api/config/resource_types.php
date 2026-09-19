<?php

declare(strict_types=1);

/**
 * Issue #7598 (R1 de l'épique #7597) — registre des TYPES de ressource qu'un
 * responsable peut donner à un collaborateur.
 *
 * Chaque entrée décrit une ressource d'un module par sa **clé stable** (celle
 * stockée dans `employee_resource_assignments.resource_type`), le modèle
 * Eloquent qui la porte, et la colonne qui sert de libellé dans les sélecteurs.
 *
 * Pourquoi un fichier de configuration et pas une classe de registre : la clé
 * est une donnée de contrat (elle vit en base et dans l'API), et une liste
 * blanche déclarative évite qu'un module en importe un autre pour s'y
 * inscrire. R3 (généralisation Travel/Fleet/Fuel/Edu/Cameras) étend cette
 * liste ; en son absence, l'API refuse le type avec `RESOURCE_TYPE_UNKNOWN`
 * (fail-closed : on n'ouvre jamais un type par défaut).
 *
 * `label_column` : colonne lue pour l'affichage. `scope_company` : la ressource
 * porte-t-elle `company_id` (toutes celles d'ici oui — c'est la garde
 * d'isolation tenant appliquée à la lecture du catalogue).
 */
return [
    'camera' => [
        'model' => App\Modules\Cameras\Domain\Models\Camera::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    // #7686 (Communication R1) — boite mail connectee d'un employe : declaree
    // des R1 pour que le RBAC ressource-scope (R2-R4 de l'epique #7597) reste
    // fail-closed (« aucun acces inter-boites sans assignation »). Le libelle
    // est l'adresse de la boite (seule donnee de profil conservee —
    // minimisation) ; les tokens sont chiffres et caches ($hidden).
    'communication_mailbox' => [
        'model' => App\Modules\Communication\Domain\Models\CommunicationIntegration::class,
        'label_column' => 'email',
        'scope_company' => true,
    ],
    // #7600 (R3) — généralisation aux autres verticales.
    'edu_campus' => [
        'model' => App\Modules\EduManager\Domain\Models\EduCampus::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    'fuel_station' => [
        'model' => App\Modules\FuelStation\Domain\Models\FuelStation::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    'restaurant_branch' => [
        'model' => App\Modules\RestaurantManager\Domain\Models\RestaurantBranch::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    'site' => [
        'model' => App\Core\Tenant\Domain\Models\Site::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    'travel_office' => [
        'model' => App\Modules\TravelAgency\Domain\Models\TravelOffice::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    'travel_station' => [
        'model' => App\Modules\TravelAgency\Domain\Models\TravelStation::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
    'vehicle' => [
        'model' => App\Modules\Fleet\Domain\Models\Vehicle::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
];
