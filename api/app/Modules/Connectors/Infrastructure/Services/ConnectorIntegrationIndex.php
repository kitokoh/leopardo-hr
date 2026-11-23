<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Infrastructure\Services;

use App\Shared\Contracts\Connectors\ConnectorIntegrationRegistry;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;
use Throwable;

/**
 * #9846 (BC-14 INTEGRATION) — index des intégrations DÉJÀ existantes.
 *
 * L'onglet « Connecteurs » doit « regrouper tout ce qui existe déjà comme
 * intégrations extérieures ». Il le fait en LECTURE, pas en duplication :
 *
 *   - chaque intégration existante garde son écran natif et sa propre
 *     configuration — c'est lui qui reste la source de vérité ;
 *   - l'onglet en donne une vue d'ensemble avec un état RÉEL (nombre
 *     d'éléments configurés) et un lien vers l'écran natif.
 *
 * Dupliquer la saisie aurait créé deux vérités divergentes : un webhook modifié
 * dans son écran natif n'aurait pas été reflété ici, et l'utilisateur n'aurait
 * plus su laquelle fait foi. C'est exactement le doublon que la mission demande
 * d'éviter.
 *
 * ⚠️ Ce service n'importe AUCUN module métier. Les sources sont déclarées par
 * les modules propriétaires dans `ConnectorIntegrationRegistry` (contrat
 * partagé `Shared\Contracts\Connectors`), ce qui satisfait la garde
 * d'isolation des modules (#5584) : Connecteurs ne dépend de personne, ce sont
 * les modules qui se déclarent auprès de lui.
 *
 * Robustesse : chaque source est lue dans son propre `try/catch`. Une table
 * absente (module non activé pour ce tenant, migration non jouée) retire la
 * ligne de l'index au lieu de faire échouer tout l'écran — un onglet
 * d'inventaire qui plante parce qu'un module optionnel n'est pas installé
 * serait inutilisable.
 */
class ConnectorIntegrationIndex
{
    public function __construct(private readonly ConnectorIntegrationRegistry $registry) {}

    /**
     * Intégrations de la PLATEFORME (Superadmin).
     *
     * @return list<array<string, mixed>>
     */
    public function forPlatform(): array
    {
        return $this->build('platform');
    }

    /**
     * Intégrations d'un TENANT (espace client).
     *
     * Les modèles portent `BelongsToCompany` : les comptages sont donc
     * automatiquement bornés au tenant courant, sans filtre explicite à
     * maintenir ici.
     *
     * @return list<array<string, mixed>>
     */
    public function forTenant(): array
    {
        return $this->build('tenant');
    }

    /**
     * @param  'platform'|'tenant'  $audience
     * @return list<array<string, mixed>>
     */
    private function build(string $audience): array
    {
        $rows = [];

        foreach ($this->registry->forAudience($audience) as $source) {
            $row = $this->row($source);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Construit une ligne d'index, ou `null` si la source est indisponible.
     *
     * @return array<string, mixed>|null
     */
    private function row(ConnectorIntegrationSourceInterface $source): ?array
    {
        try {
            $count = $source->count();
        } catch (Throwable) {
            // Table absente ou module non activé : la ligne disparaît de
            // l'index plutôt que de casser l'écran entier.
            return null;
        }

        return [
            'key' => $source->key(),
            'label' => $source->label(),
            'category' => $source->category(),
            'href' => $source->href(),
            'count' => $count,
            'configured' => $count > 0,
            'detail' => $source->detail(),
        ];
    }
}
