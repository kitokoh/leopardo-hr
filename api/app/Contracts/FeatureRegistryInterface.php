<?php

namespace App\Contracts;

use App\Exceptions\FeatureSynchronizationException;
use App\Models\Feature;
use Illuminate\Support\Collection;

/**
 * Interface pour le registre centralisé des fonctionnalités API
 *
 * Cette interface définit les méthodes nécessaires pour maintenir un inventaire
 * complet de toutes les fonctionnalités API disponibles, avec support du cache
 * et du versioning.
 */
interface FeatureRegistryInterface
{
    /**
     * Enregistre une nouvelle fonctionnalité dans le registre
     *
     * @param  Feature  $feature  La fonctionnalité à enregistrer
     *
     * @throws FeatureSynchronizationException Si l'enregistrement échoue
     */
    public function registerFeature(Feature $feature): void;

    /**
     * Récupère toutes les fonctionnalités disponibles
     *
     * @param  string|null  $version  Version API spécifique (optionnel)
     * @return Collection<Feature> Collection des fonctionnalités
     */
    public function getFeatures(?string $version = null): Collection;

    /**
     * Récupère une fonctionnalité spécifique par sa clé
     *
     * @param  string  $key  Clé unique de la fonctionnalité
     * @return Feature|null La fonctionnalité trouvée ou null
     */
    public function getFeature(string $key): ?Feature;

    /**
     * Met à jour les métadonnées d'une fonctionnalité existante
     *
     * @param  string  $key  Clé de la fonctionnalité à mettre à jour
     * @param  array  $metadata  Nouvelles métadonnées
     *
     * @throws FeatureSynchronizationException Si la mise à jour échoue
     */
    public function updateFeature(string $key, array $metadata): void;

    /**
     * Supprime une fonctionnalité du registre
     *
     * @param  string  $key  Clé de la fonctionnalité à supprimer
     */
    public function removeFeature(string $key): void;

    /**
     * Génère le manifeste complet des fonctionnalités
     *
     * @param  string|null  $mobileVersion  Version mobile pour filtrer la compatibilité (optionnel)
     * @return array Manifeste JSON des fonctionnalités
     */
    public function getManifest(?string $mobileVersion = null): array;

    /**
     * Récupère les fonctionnalités compatibles avec une version mobile spécifique
     *
     * @param  string  $mobileVersion  Version mobile cible
     * @return Collection<Feature> Collection des fonctionnalités compatibles
     */
    public function getCompatibleFeatures(string $mobileVersion): Collection;

    /**
     * Récupère les fonctionnalités par version API
     *
     * @param  string  $apiVersion  Version de l'API
     * @return Collection<Feature> Collection des fonctionnalités pour cette version API
     */
    public function getFeaturesByApiVersion(string $apiVersion): Collection;

    /**
     * Vérifie si une fonctionnalité existe dans le registre
     *
     * @param  string  $key  Clé de la fonctionnalité
     * @return bool True si la fonctionnalité existe
     */
    public function hasFeature(string $key): bool;

    /**
     * Invalide le cache du registre
     *
     * @param  string|null  $key  Clé spécifique à invalider (optionnel, invalide tout si null)
     */
    public function invalidateCache(?string $key = null): void;

    /**
     * Synchronise le registre avec les fonctionnalités détectées
     *
     * @return array Résultat de la synchronisation (nouvelles, modifiées, supprimées)
     */
    public function synchronize(): array;

    /**
     * Récupère les statistiques du registre
     *
     * @return array Statistiques (nombre total, par version, par statut, etc.)
     */
    public function getStatistics(): array;
}
