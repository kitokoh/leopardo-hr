<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Contrat de stockage des fichiers de la bibliothèque (BC-40 LIBRARY).
 *
 * Le module ne connaît PAS le driver réel (S3-compatible, disque local privé,
 * autre) : il ne dépend que de cette interface. L'implémentation est choisie
 * par configuration (`library.storage_disk`) et liée dans
 * `LibraryServiceProvider`.
 *
 * ⚠️ RÈGLE DE SÉCURITÉ (droits d'auteur / contenus payants) : aucun fichier
 * n'est jamais exposé par une URL publique permanente. Deux voies seulement :
 *  - `temporaryUrl()` : URL présignée à durée de vie courte (S3-compatible) ;
 *  - `null` : le driver ne sait pas présigner → l'appelant sert le fichier via
 *    une route interne protégée par `LibraryAccessPolicy` + signature HMAC.
 *
 * `isConfigured()` permet de distinguer « stockage prêt » de « stockage non
 * configuré » : dans le second cas on renvoie un 501 explicite plutôt que de
 * prétendre servir un fichier inexistant.
 */
interface LibraryFileStorage
{
    /** Le stockage est-il réellement configuré (credentials/disque présents) ? */
    public function isConfigured(): bool;

    /** Nom du disque utilisé (diagnostic, jamais exposé au public). */
    public function diskName(): string;

    /**
     * Enregistre un fichier téléversé sous la clé donnée.
     *
     * @return int Taille en octets effectivement écrite.
     */
    public function put(string $key, UploadedFile $file): int;

    /** Le fichier existe-t-il sous cette clé ? */
    public function exists(string $key): bool;

    /** Taille en octets, ou `null` si le fichier est absent. */
    public function size(string $key): ?int;

    /**
     * URL présignée à durée de vie courte, ou `null` si le driver ne sait pas
     * présigner (l'appelant bascule alors sur la route interne signée).
     */
    public function temporaryUrl(string $key, int $ttlSeconds): ?string;

    /**
     * Flux de lecture du fichier (pour la route interne protégée).
     *
     * @return resource|null
     */
    public function readStream(string $key);

    /** Supprime le fichier (idempotent). */
    public function delete(string $key): void;
}
