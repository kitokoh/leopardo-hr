<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Connectors;

/**
 * #9846 (BC-14 INTEGRATION) — contrat PARTAGÉ d'une intégration extérieure
 * déjà existante.
 *
 * Pourquoi dans `Shared\Contracts` : l'onglet « Connecteurs » doit regrouper
 * les intégrations qui vivent dans d'autres modules (Billing, Paiements,
 * Attendance, Recruitment, SearchIntelligence). La garde d'isolation des
 * modules (#5584, `check-module-isolation.sh`) interdit un import
 * `Modules/Connectors -> Modules/Billing` : le contrat vit donc dans l'espace
 * partagé, et chaque module propriétaire fournit son implémentation.
 *
 * Le sens de la dépendance est ainsi inversé : Connecteurs ne connaît AUCUN
 * module métier, ce sont les modules qui se déclarent auprès de lui. Ajouter
 * une intégration à l'index ne demande donc aucune modification de Connecteurs.
 *
 * ⚠️ `count()` est la seule méthode qui touche la base. Elle est appelée dans
 * un `try/catch` par le consommateur : une table absente (module non activé
 * pour ce tenant, migration non jouée) doit retirer la ligne de l'index, pas
 * casser l'écran.
 */
interface ConnectorIntegrationSourceInterface
{
    /** Clé stable, utilisée comme identifiant dans l'index et les tests. */
    public function key(): string;

    /** Libellé affiché. */
    public function label(): string;

    /** Catégorie de connecteur (`payment`, `marketplace`, `analytics`, `other`…). */
    public function category(): string;

    /** Route de l'écran natif — c'est lui qui reste la source de vérité. */
    public function href(): string;

    /** Une phrase expliquant ce que fait l'intégration. */
    public function detail(): string;

    /**
     * Surfaces où cette intégration doit apparaître.
     *
     * @return list<'platform'|'tenant'>
     */
    public function audiences(): array;

    /** Nombre d'éléments réellement configurés (borné au tenant courant si applicable). */
    public function count(): int;
}
