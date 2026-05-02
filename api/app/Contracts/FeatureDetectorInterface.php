<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface pour la détection automatique des fonctionnalités API
 * 
 * Cette interface définit les méthodes nécessaires pour détecter automatiquement
 * les nouvelles fonctionnalités ajoutées à l'API Laravel et extraire leurs métadonnées.
 */
interface FeatureDetectorInterface
{
    /**
     * Détecte toutes les nouvelles fonctionnalités API disponibles
     * 
     * Scanne les routes et contrôleurs pour identifier les fonctionnalités
     * qui ne sont pas encore enregistrées dans le Feature Registry.
     * 
     * @return Collection<array> Collection des nouvelles fonctionnalités détectées
     */
    public function detectNewFeatures(): Collection;

    /**
     * Extrait les métadonnées d'une méthode de contrôleur
     * 
     * Utilise la reflection PHP pour analyser une méthode de contrôleur
     * et extraire ses métadonnées (annotations, attributs, paramètres, etc.).
     * 
     * @param string $controllerClass Nom complet de la classe du contrôleur
     * @param string $method Nom de la méthode à analyser
     * @return array Métadonnées extraites de la méthode
     */
    public function extractMetadata(string $controllerClass, string $method): array;

    /**
     * Scanne toutes les routes Laravel enregistrées
     * 
     * Analyse le router Laravel pour identifier toutes les routes API
     * et leurs informations associées (URI, méthodes HTTP, contrôleur, etc.).
     * 
     * @return Collection<array> Collection des routes avec leurs informations
     */
    public function scanRoutes(): Collection;

    /**
     * Détecte les changements dans les fonctionnalités existantes
     * 
     * Compare les signatures actuelles des méthodes avec celles enregistrées
     * pour identifier les modifications de fonctionnalités.
     * 
     * @return Collection<array> Collection des changements détectés
     */
    public function detectChanges(): Collection;
}