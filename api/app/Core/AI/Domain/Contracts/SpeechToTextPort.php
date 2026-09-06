<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Contracts;

use App\Core\AI\Domain\Exceptions\SpeechToTextUnavailableException;

/**
 * Port Speech-To-Text — issue #6849 (BC-23, EPIC #6846).
 *
 * Suit le pattern des ports Core/AI existants (FaceVerificationPort,
 * ModelInferencePort) : contrat côté domaine, implémentations en
 * Infrastructure/Adapters. Fail-closed : toute défaillance (clé absente,
 * échec HTTP, transcription vide) lève SpeechToTextUnavailableException —
 * jamais de texte vide silencieux.
 */
interface SpeechToTextPort
{
    /**
     * Transcrit un fichier audio en texte.
     *
     * @param  string  $audioContents  Contenu binaire du fichier audio.
     * @param  string  $fileName  Nom original du fichier (multipart).
     * @param  string  $mimeType  Type MIME du fichier.
     * @param  string  $language  Code langue (fr, en, ar, tr…).
     *
     * @throws SpeechToTextUnavailableException
     */
    public function transcribe(string $audioContents, string $fileName, string $mimeType, string $language): string;
}
