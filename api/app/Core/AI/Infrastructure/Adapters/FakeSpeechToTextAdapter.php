<?php

declare(strict_types=1);

namespace App\Core\AI\Infrastructure\Adapters;

use App\Core\AI\Domain\Contracts\SpeechToTextPort;

/**
 * STT factice pour les tests — issue #6849 (BC-23).
 *
 * Ne fait aucun appel réseau ; retourne un texte déterministe (optionnellement
 * configurable) pour tester les pipelines voix→texte hors fournisseur réel.
 */
class FakeSpeechToTextAdapter implements SpeechToTextPort
{
    public function __construct(private readonly string $text = 'Transcription factice de test.') {}

    public function transcribe(string $audioContents, string $fileName, string $mimeType, string $language): string
    {
        return $this->text;
    }
}
