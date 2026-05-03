<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface pour la dÃ©tection automatique des fonctionnalitÃ©s API
 *
 * Cette interface dÃ©finit les mÃ©thodes nÃ©cessaires pour dÃ©tecter automatiquement
 * les nouvelles fonctionnalitÃ©s ajoutÃ©es Ã  l'API Laravel et extraire leurs mÃ©tadonnÃ©es.
 */
interface FeatureDetectorInterface
{
    /**
     * DÃ©tecte toutes les nouvelles fonctionnalitÃ©s API disponibles
     *
     * Scanne les routes et contrÃ´leurs pour identifier les fonctionnalitÃ©s
     * qui ne sont pas encore enregistrÃ©es dans le Feature Registry.
     *
     * @return Collection<array> Collection des nouvelles fonctionnalitÃ©s dÃ©tectÃ©es
     */
    public function detectNewFeatures(): Collection;

    /**
     * Extrait les mÃ©tadonnÃ©es d'une mÃ©thode de contrÃ´leur
     *
     * Utilise la reflection PHP pour analyser une mÃ©thode de contrÃ´leur
     * et extraire ses mÃ©tadonnÃ©es (annotations, attributs, paramÃ¨tres, etc.).
     *
     * @param string $controllerClass Nom complet de la classe du contrÃ´leur
     * @param string $method Nom de la mÃ©thode Ã  analyser
     *
     * @return array MÃ©tadonnÃ©es extraites de la mÃ©thode
     */
    public function extractMetadata(string $controllerClass, string $method): array;

    /**
     * Scanne toutes les routes Laravel enregistrÃ©es
     *
     * Analyse le router Laravel pour identifier toutes les routes API
     * et leurs informations associÃ©es (URI, mÃ©thodes HTTP, contrÃ´leur, etc.).
     *
     * @return Collection<array> Collection des routes avec leurs informations
     */
    public function scanRoutes(): Collection;

    /**
     * DÃ©tecte les changements dans les fonctionnalitÃ©s existantes
     *
     * Compare les signatures actuelles des mÃ©thodes avec celles enregistrÃ©es
     * pour identifier les modifications de fonctionnalitÃ©s.
     *
     * @return Collection<array> Collection des changements dÃ©tectÃ©s
     */
    public function detectChanges(): Collection;
}
