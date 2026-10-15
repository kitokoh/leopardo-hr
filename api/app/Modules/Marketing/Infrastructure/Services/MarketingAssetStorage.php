<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Enums\MarketingAssetKind;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Marketing OS — stockage des binaires de la médiathèque (brief §C).
 *
 * Décision d'architecture : on RÉUTILISE le même parti pris que
 * `ShowcaseMedia\(\ShowcaseMediaService)` — disk `local` (privé, hors
 * webroot), nom de stockage aléatoire, extension whitelistée par type,
 * plafond de poids, nom d'origine sanitizé. L'implémentation est reproduite
 * ici plutôt qu'importée : la garde d'isolation #5584 interdit à un module
 * d'importer un autre module, et ce service appartient au domaine
 * Marketing (médiathèque marketing ≠ médias de vitrine).
 *
 * Aucun binaire n'est jamais écrit en base : la base ne porte que `disk` +
 * `path` (cf. migration des tables Marketing OS).
 */
class MarketingAssetStorage
{
    /** Disk de stockage (existant, privé, hors webroot). */
    public const DISK = 'local';

    /** Longueur maximale du nom d'origine conservé. */
    private const MAX_ORIGINAL_NAME_LENGTH = 180;

    /** Plafond de poids en kilo-octets, par type de ressource. */
    private const MAX_KILOBYTES = [
        'video' => 204800,   // 200 Mo — un master vidéo n'a pas sa place en base
        'image' => 20480,    // 20 Mo
        'illustration' => 20480,
        'logo' => 5120,
        'audio' => 51200,    // 50 Mo
        'document' => 20480,
        'script' => 2048,
        'prompt' => 2048,
        'copy' => 2048,
        'other' => 20480,
    ];

    /**
     * Stocke le fichier et retourne ses métadonnées de stockage.
     *
     * @return array{disk: string, path: string, mime_type: string, extension: string, size: int, original_name: string}
     */
    public function store(MarketingAsset $asset, MarketingAssetKind $kind, UploadedFile $file): array
    {
        $extension = $this->validatedExtension($kind, $file);
        $storedName = bin2hex(random_bytes(16)).'.'.$extension;

        $directory = sprintf('marketing/%s/%d', $asset->company_id, $asset->id);

        $path = $file->storeAs($directory, $storedName, self::DISK);

        if (! is_string($path)) {
            throw new RuntimeException('Marketing OS : échec du stockage du média.');
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension,
            'size' => (int) $file->getSize(),
            'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName(), $extension),
        ];
    }

    /** Supprime le binaire (le contrôleur garantit l'appartenance au tenant). */
    public function delete(MarketingAsset $asset): void
    {
        if ($asset->hasFile()) {
            Storage::disk((string) $asset->disk)->delete((string) $asset->path);
        }
    }

    /** Nom d'origine sanitizé — jamais de séparateur de chemin conservé. */
    public function sanitizeOriginalName(string $name, string $extension): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
        $base = trim($base, '-._');
        $base = substr($base, 0, self::MAX_ORIGINAL_NAME_LENGTH);

        if ($base === '') {
            $base = 'asset';
        }

        return $base.'.'.$extension;
    }

    private function validatedExtension(MarketingAssetKind $kind, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $kind->allowedExtensions();

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'Type de fichier non autorisé pour une ressource « '.$kind->label().' ». Extensions acceptées : '.implode(', ', $allowed).'.',
            ]);
        }

        $kilobytes = (int) ceil(((int) $file->getSize()) / 1024);
        $max = self::MAX_KILOBYTES[$kind->value] ?? 20480;

        if ($kilobytes > $max) {
            throw ValidationException::withMessages([
                'file' => 'Fichier trop volumineux ('.$kilobytes.' Ko) : maximum '.$max.' Ko pour ce type de ressource.',
            ]);
        }

        return $extension;
    }
}
