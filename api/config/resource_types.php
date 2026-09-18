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
    'vehicle' => [
        'model' => App\Modules\Fleet\Domain\Models\Vehicle::class,
        'label_column' => 'name',
        'scope_company' => true,
    ],
];
