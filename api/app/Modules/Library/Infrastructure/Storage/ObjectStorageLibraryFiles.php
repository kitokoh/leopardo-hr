<?php

declare(strict_types=1);

namespace App\Modules\Library\Infrastructure\Storage;

use App\Modules\Library\Domain\Contracts\LibraryFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Adaptateur de stockage objet de la bibliothèque (BC-40 LIBRARY).
 *
 * S'appuie sur le disque Laravel configuré (`library.storage_disk`, défaut
 * `library`). Le disque `library` est déclaré dans `config/filesystems.php` :
 *  - driver `s3` si `LIBRARY_STORAGE_DRIVER=s3` (S3-compatible : AWS, MinIO,
 *    Scaleway, OVH…) → URLs présignées natives ;
 *  - driver `local` sinon, racine `storage/app/library` HORS webroot →
 *    jamais servi statiquement, lecture via route interne signée.
 *
 * Aucune dépendance nouvelle : `league/flysystem-aws-s3-v3` est déjà requis
 * par le disque `s3` du dépôt (Laravel), et le driver local est natif.
 *
 * ⚠️ `isConfigured()` est volontairement strict : un disque `s3` sans bucket
 * ou sans credentials est considéré NON configuré (on renvoie 501 plutôt que
 * d'échouer à l'exécution sur un upload).
 */
final class ObjectStorageLibraryFiles implements LibraryFileStorage
{
    public function __construct(
        private readonly string $disk = 'library',
    ) {}

    public function isConfigured(): bool
    {
        $driver = (string) config('library.storage_driver', 'local');

        if ($driver === 's3') {
            $bucket = (string) config('filesystems.disks.'.$this->disk.'.bucket', '');
            $key = (string) config('filesystems.disks.'.$this->disk.'.key', '');

            return $bucket !== '' && $key !== '';
        }

        // Disque local : toujours disponible (racine hors webroot).
        return true;
    }

    public function diskName(): string
    {
        return $this->disk;
    }

    public function put(string $key, UploadedFile $file): int
    {
        $path = $file->storeAs(
            dirname($key) === '.' ? '' : dirname($key),
            basename($key),
            ['disk' => $this->disk, 'visibility' => 'private'],
        );

        if ($path === false) {
            throw new \RuntimeException('library_storage_write_failed');
        }

        return (int) Storage::disk($this->disk)->size($path);
    }

    public function exists(string $key): bool
    {
        return Storage::disk($this->disk)->exists($key);
    }

    public function size(string $key): ?int
    {
        try {
            if (! $this->exists($key)) {
                return null;
            }

            return (int) Storage::disk($this->disk)->size($key);
        } catch (Throwable) {
            return null;
        }
    }

    public function temporaryUrl(string $key, int $ttlSeconds): ?string
    {
        $driver = (string) config('library.storage_driver', 'local');

        // Seul un driver objet sait présigner. Le disque local n'a pas d'URL
        // publique : on renvoie null → route interne signée.
        if ($driver !== 's3') {
            return null;
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl($key, now()->addSeconds($ttlSeconds));
        } catch (Throwable) {
            // Driver incapable de présigner (endpoint mal configuré) : repli
            // sur la route interne signée, jamais d'URL publique permanente.
            return null;
        }
    }

    /**
     * @return resource|null
     */
    public function readStream(string $key)
    {
        if (! $this->exists($key)) {
            return null;
        }

        $stream = Storage::disk($this->disk)->readStream($key);

        return is_resource($stream) ? $stream : null;
    }

    public function delete(string $key): void
    {
        try {
            Storage::disk($this->disk)->delete($key);
        } catch (Throwable) {
            // Suppression best-effort : un fichier orphelin ne doit pas faire
            // échouer la suppression de la ressource métier.
        }
    }
}
