<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Enums;

/**
 * Types de modèles IA couverts par le contrat commun d'inférence
 * (AI-001, #6770).
 *
 * Chaque type déclare un schéma de sortie attendu (cf. ModelOutputValidator) :
 * la validation est appliquée avant que les sorties n'entrent dans le domaine.
 */
enum ModelType: string
{
    /** Vérification faciale 1:1 (BIO-001, #6762). */
    case FaceVerification = 'face_verification';

    /** Détection de vivacité (anti-spoofing). */
    case Liveness = 'liveness';

    /** OCR — lecture de compteur (FuelStation, AI-002 #6771). */
    case OcrReading = 'ocr_reading';

    /**
     * Clés de payload requises (schéma de sortie) pour ce type.
     *
     * @return list<string>
     */
    public function requiredPayloadKeys(): array
    {
        return match ($this) {
            self::FaceVerification => ['verified'],
            self::Liveness => ['live'],
            self::OcrReading => ['value', 'unit'],
        };
    }
}
