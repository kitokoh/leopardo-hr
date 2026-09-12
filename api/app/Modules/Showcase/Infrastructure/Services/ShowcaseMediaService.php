<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Modules\Showcase\Domain\Enums\ShowcaseMediaKind;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Stockage des médias de vitrine (BC-27 SHOWCASE, V-MEDIA #6872).
 *
 * Réutilise le disk existant `local` (config/filesystems.php, racine
 * `storage/app/private`, HORS webroot) : un média de brouillon n'est donc
 * jamais joignable statiquement — il n'est servi que par la route publique
 * dédiée, après contrôle du statut de publication.
 *
 * Garde-fous appliqués à chaque upload :
 *  - extension whitelistée par type ({@see ShowcaseMediaKind::allowedExtensions()}) ;
 *  - poids borné par type ({@see ShowcaseMediaKind::maxKilobytes()}) ;
 *  - SVG (logo uniquement) : refus du contenu actif (script, gestionnaire
 *    d'événement, entité XML, référence externe/data:) — défense anti-XSS
 *    en complément du CSP posé par le contrôleur public ;
 *  - nom d'origine sanitizé (traversée de chemin neutralisée) et nom de
 *    stockage généré aléatoirement (jamais le nom client sur le disk).
 *
 * Aucun redimensionnement n'est effectué : `api/composer.json` ne contient
 * ni intervention/image ni dépendance GD/Imagick. La maîtrise du stockage
 * passe donc par les limites de poids ci-dessus (WebP accepté tel quel).
 */
final class ShowcaseMediaService
{
    /** Disk de stockage des médias (existant, privé, hors webroot). */
    public const DISK = 'local';

    /** Longueur maximale du nom d'origine sanitizé. */
    private const MAX_ORIGINAL_NAME_LENGTH = 180;

    /**
     * Motifs de contenu actif interdits dans un SVG (heuristique de refus :
     * le SVG est un document exécutable dans un navigateur).
     *
     * @var list<string>
     */
    private const SVG_FORBIDDEN_PATTERNS = [
        '/<\s*script\b/i',
        '/<\s*foreignObject\b/i',
        '/<\s*(iframe|embed|object|audio|video)\b/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
        '/<!ENTITY\b/i',
        '/<\?php/i',
        '/(?:xlink:href|href)\s*=\s*["\']?\s*(?:https?:)?\/\//i',
        '/(?:xlink:href|href)\s*=\s*["\']?\s*data:/i',
    ];

    /**
     * Stocke le fichier et crée la ligne `showcase_media` correspondante.
     */
    public function store(
        CompanyShowcase $showcase,
        ShowcaseMediaKind $kind,
        UploadedFile $file,
        ?CompanyShowcaseSection $section = null,
    ): ShowcaseMedia {
        $extension = $this->validatedExtension($kind, $file);

        $this->assertContentIsSafe($kind, $extension, $file);

        $storedName = $this->randomStoredName($extension);
        $directory = sprintf('showcase/%s/%d/%s', $showcase->company_id, $showcase->id, $kind->value);

        $path = $file->storeAs($directory, $storedName, self::DISK);

        if (! is_string($path)) {
            throw new RuntimeException(__('showcase.media_store_failed'));
        }

        /** @var ShowcaseMedia $media */
        $media = ShowcaseMedia::query()->create([
            'company_id' => $showcase->company_id,
            'showcase_id' => $showcase->id,
            'section_id' => $section?->id,
            'kind' => $kind->value,
            'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName(), $extension),
            'file_name' => $storedName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension,
            'size' => (int) $file->getSize(),
            'disk' => self::DISK,
            'path' => $path,
        ]);

        return $media;
    }

    /**
     * Supprime le binaire puis la ligne (le contrôleur garantit l'appartenance).
     */
    public function delete(ShowcaseMedia $media): void
    {
        Storage::disk($media->disk)->delete($media->path);

        $media->delete();
    }

    /**
     * Extrait le nom d'origine sanitizé (base `[A-Za-z0-9._-]`, extension
     * whitelistée) — jamais de séparateur de chemin ni de caractère de
     * contrôle dans le nom conservé.
     */
    public function sanitizeOriginalName(string $name, string $extension): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
        $base = trim($base, '-._');
        $base = substr($base, 0, self::MAX_ORIGINAL_NAME_LENGTH);

        if ($base === '') {
            $base = 'media';
        }

        return $base.'.'.$extension;
    }

    /**
     * Extension whitelistée pour le type demandé (ou 422).
     */
    private function validatedExtension(ShowcaseMediaKind $kind, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === '' || ! in_array($extension, $kind->allowedExtensions(), true)) {
            throw ValidationException::withMessages([
                'file' => __('showcase.media_type_not_allowed', [
                    'kind' => $kind->value,
                    'types' => implode(', ', $kind->allowedExtensions()),
                ]),
            ]);
        }

        $this->assertSize($kind, $file);

        return $extension;
    }

    /**
     * Poids borné (kb) — double garde avec la règle `max` du FormRequest.
     */
    private function assertSize(ShowcaseMediaKind $kind, UploadedFile $file): void
    {
        $kilobytes = (int) ceil(((int) $file->getSize()) / 1024);

        if ($kilobytes > $kind->maxKilobytes()) {
            throw ValidationException::withMessages([
                'file' => __('showcase.media_too_large', ['max' => (string) $kind->maxKilobytes()]),
            ]);
        }
    }

    /**
     * Refuse un SVG porteur de contenu actif (logo uniquement — un SVG de
     * section est déjà écarté par la whitelist d'extension).
     */
    private function assertContentIsSafe(ShowcaseMediaKind $kind, string $extension, UploadedFile $file): void
    {
        if ($extension !== 'svg') {
            return;
        }

        if (! $kind->allowsSvg()) {
            throw ValidationException::withMessages([
                'file' => __('showcase.media_type_not_allowed', [
                    'kind' => $kind->value,
                    'types' => implode(', ', $kind->allowedExtensions()),
                ]),
            ]);
        }

        $realPath = $file->getRealPath();
        $contents = $realPath !== false ? file_get_contents($realPath) : false;

        if (! is_string($contents)) {
            throw new RuntimeException(__('showcase.media_store_failed'));
        }

        foreach (self::SVG_FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                throw ValidationException::withMessages([
                    'file' => __('showcase.media_svg_unsafe'),
                ]);
            }
        }
    }

    /**
     * Nom de stockage non devinable (jamais le nom d'origine du client).
     */
    private function randomStoredName(string $extension): string
    {
        return bin2hex(random_bytes(16)).'.'.$extension;
    }
}
