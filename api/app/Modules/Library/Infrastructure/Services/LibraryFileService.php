<?php

declare(strict_types=1);

namespace App\Modules\Library\Infrastructure\Services;

use App\Modules\Library\Domain\Contracts\LibraryFileStorage;
use App\Modules\Library\Domain\Models\LibraryResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Téléversement et résolution des fichiers de la bibliothèque (BC-40 LIBRARY).
 *
 * Responsabilités :
 *  - valider le type MIME réel (pas seulement l'extension) et la taille ;
 *  - construire une clé de stockage SÉCURISÉE et ISOLÉE PAR TENANT :
 *      library/{company_id|public}/{resource_id}/{uuid}.{ext}
 *    → aucun chemin fourni par le client n'est utilisé (anti path-traversal),
 *      et un tenant ne peut jamais écrire dans l'espace d'un autre ;
 *  - enregistrer les métadonnées (`file_key`, `file_format`, `file_size`) sur
 *    la ressource.
 *
 * Les formats autorisés sont volontairement restreints aux formats de lecture
 * réellement pris en charge par le lecteur (PDF, EPUB) et aux documents
 * consultables dans le navigateur (TXT, HTML). Aucun exécutable, aucune image
 * n'est acceptée comme document de bibliothèque.
 */
final class LibraryFileService
{
    /** Types MIME autorisés → extension canonique. */
    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'application/epub+zip' => 'epub',
        'text/plain' => 'txt',
        'text/html' => 'html',
    ];

    /** Taille maximale par défaut (Mo) si la config est absente. */
    private const DEFAULT_MAX_MB = 50;

    public function __construct(
        private readonly LibraryFileStorage $storage,
    ) {}

    public function isConfigured(): bool
    {
        return $this->storage->isConfigured();
    }

    /**
     * Téléverse un fichier pour une ressource et met à jour ses métadonnées.
     *
     * @throws RuntimeException si le stockage n'est pas configuré, si le type
     *                          MIME n'est pas autorisé ou si la taille dépasse
     *                          la limite.
     */
    public function attach(LibraryResource $resource, UploadedFile $file): LibraryResource
    {
        if (! $this->storage->isConfigured()) {
            throw new RuntimeException('library_storage_not_configured');
        }

        $mime = (string) $file->getMimeType();
        $extension = self::ALLOWED_MIME[$mime] ?? null;

        if ($extension === null) {
            throw new RuntimeException('library_file_type_not_allowed');
        }

        $maxBytes = $this->maxBytes();
        if ((int) $file->getSize() > $maxBytes) {
            throw new RuntimeException('library_file_too_large');
        }

        $key = $this->buildKey($resource, $extension);
        $size = $this->storage->put($key, $file);

        // Remplace un éventuel fichier précédent (nouvelle version du document).
        $previous = $resource->file_key;
        if (is_string($previous) && $previous !== '' && $previous !== $key) {
            $this->storage->delete($previous);
        }

        $resource->update([
            'file_key' => $key,
            'file_format' => $extension,
            'file_size' => $size > 0 ? $size : (int) $file->getSize(),
        ]);

        return $resource->refresh();
    }

    /**
     * Résout l'accès au fichier d'une ressource.
     *
     * @return array{url: string|null, stream: resource|null, format: string|null, size: int|null}
     *                                                                                             `url` = URL présignée (S3) ; `stream` = flux pour la route interne
     *                                                                                             signée (disque local). L'un des deux est non-null si le fichier
     *                                                                                             existe.
     */
    public function resolve(LibraryResource $resource, int $ttlSeconds): array
    {
        $key = $resource->file_key;

        if (! is_string($key) || $key === '') {
            return ['url' => null, 'stream' => null, 'format' => null, 'size' => null];
        }

        $url = $this->storage->temporaryUrl($key, $ttlSeconds);
        $stream = $url === null ? $this->storage->readStream($key) : null;

        return [
            'url' => $url,
            'stream' => $stream,
            'format' => $resource->file_format,
            'size' => $this->storage->size($key),
        ];
    }

    /** Supprime le fichier d'une ressource (appelé à la suppression). */
    public function detach(LibraryResource $resource): void
    {
        $key = $resource->file_key;

        if (is_string($key) && $key !== '') {
            $this->storage->delete($key);
        }
    }

    /**
     * Clé de stockage isolée par tenant, non devinable.
     *
     * `library/{tenant}/{resource_id}/{uuid}.{ext}` — le segment tenant vaut
     * `public` pour le catalogue Leopardo (`company_id` NULL) et l'UUID du
     * tenant sinon. Aucune donnée fournie par le client n'entre dans la clé.
     */
    private function buildKey(LibraryResource $resource, string $extension): string
    {
        $tenant = $resource->company_id === null ? 'public' : (string) $resource->company_id;

        return sprintf(
            'library/%s/%d/%s.%s',
            $tenant,
            (int) $resource->id,
            Str::uuid()->toString(),
            $extension,
        );
    }

    private function maxBytes(): int
    {
        $mb = (int) config('library.max_upload_mb', self::DEFAULT_MAX_MB);

        return max(1, $mb) * 1024 * 1024;
    }
}
