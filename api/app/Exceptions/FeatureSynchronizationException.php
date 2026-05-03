<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception levée lors d'erreurs de synchronisation des fonctionnalités
 * 
 * Cette exception est utilisée pour signaler les erreurs qui se produisent
 * lors de la détection, l'enregistrement ou la synchronisation des fonctionnalités
 * entre l'API et le registre des fonctionnalités.
 */
class FeatureSynchronizationException extends Exception
{
    /**
     * Crée une exception pour un échec de détection de fonctionnalité
     * 
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function detectionFailed(string $reason): self
    {
        return new self("Feature detection failed: {$reason}");
    }

    /**
     * Crée une exception pour un échec de génération de manifeste
     * 
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function manifestGenerationFailed(string $reason): self
    {
        return new self("Manifest generation failed: {$reason}");
    }

    /**
     * Crée une exception pour une version incompatible
     * 
     * @param string $feature Nom de la fonctionnalité
     * @param string $version Version mobile incompatible
     * @return static
     */
    public static function incompatibleVersion(string $feature, string $version): self
    {
        return new self("Feature {$feature} incompatible with mobile version {$version}");
    }

    /**
     * Crée une exception pour un échec d'enregistrement de fonctionnalité
     * 
     * @param string $featureKey Clé de la fonctionnalité
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function registrationFailed(string $featureKey, string $reason): self
    {
        return new self("Failed to register feature {$featureKey}: {$reason}");
    }

    /**
     * Crée une exception pour un échec de mise à jour de fonctionnalité
     * 
     * @param string $featureKey Clé de la fonctionnalité
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function updateFailed(string $featureKey, string $reason): self
    {
        return new self("Failed to update feature {$featureKey}: {$reason}");
    }

    /**
     * Crée une exception pour une fonctionnalité non trouvée
     * 
     * @param string $featureKey Clé de la fonctionnalité
     * @return static
     */
    public static function featureNotFound(string $featureKey): self
    {
        return new self("Feature {$featureKey} not found in registry");
    }

    /**
     * Crée une exception pour un échec de synchronisation
     * 
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function synchronizationFailed(string $reason): self
    {
        return new self("Synchronization failed: {$reason}");
    }

    /**
     * Crée une exception pour un échec de validation
     * 
     * @param string $field Champ qui a échoué à la validation
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function validationFailed(string $field, string $reason): self
    {
        return new self("Validation failed for {$field}: {$reason}");
    }

    /**
     * Crée une exception pour un échec de cache
     * 
     * @param string $operation Opération de cache qui a échoué
     * @param string $reason Raison de l'échec
     * @return static
     */
    public static function cacheFailed(string $operation, string $reason): self
    {
        return new self("Cache operation {$operation} failed: {$reason}");
    }
}