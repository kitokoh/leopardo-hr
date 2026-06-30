<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception levée lors d'erreurs de synchronisation des fonctionnalités
 */
class FeatureSynchronizationException extends Exception
{
    public static function detectionFailed(string $reason): static
    {
        return new static("Feature detection failed: {$reason}");
    }

    public static function manifestGenerationFailed(string $reason): static
    {
        return new static("Manifest generation failed: {$reason}");
    }

    public static function incompatibleVersion(string $feature, string $version): static
    {
        return new static("Feature {$feature} incompatible with mobile version {$version}");
    }

    public static function registrationFailed(string $featureKey, string $reason): static
    {
        return new static("Failed to register feature {$featureKey}: {$reason}");
    }

    public static function updateFailed(string $featureKey, string $reason): static
    {
        return new static("Failed to update feature {$featureKey}: {$reason}");
    }

    public static function featureNotFound(string $featureKey): static
    {
        return new static("Feature {$featureKey} not found in registry");
    }

    public static function synchronizationFailed(string $reason): static
    {
        return new static("Synchronization failed: {$reason}");
    }

    public static function validationFailed(string $field, string $reason): static
    {
        return new static("Validation failed for {$field}: {$reason}");
    }

    public static function cacheFailed(string $reason): static
    {
        return new static("Cache operation failed: {$reason}");
    }
}
