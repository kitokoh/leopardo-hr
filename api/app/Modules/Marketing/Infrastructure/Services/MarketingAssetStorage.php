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

    /**
     * #9347 (audit sécurité 2026-11-10, F5) — types MIME acceptés pour chaque
     * extension autorisée par `MarketingAssetKind::allowedExtensions()`.
     *
     * Volontairement permissif sur les variantes légitimes (un `.jpg` peut
     * être détecté `image/jpeg` ou `image/pjpeg`), fermé sur tout le reste.
     * Une extension absente de cette table est REFUSÉE (fail-closed) : mieux
     * vaut rejeter un cas non couvert que d'accepter un contenu arbitraire.
     *
     * @var array<string, list<string>>
     */
    private const EXPECTED_MIMES = [
        // Vidéo
        'mp4' => ['video/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'mov' => ['video/quicktime'],
        'webm' => ['video/webm'],
        // Image
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        // SVG : finfo hésite entre `image/svg+xml`, `text/xml` et
        // `text/plain` selon la version de libmagic — on accepte ces trois
        // détections, le fichier restant sur le disque `local` (hors webroot).
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'],
        // Audio
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'wav' => ['audio/x-wav', 'audio/wav', 'audio/wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        // Document
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        // Texte
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown', 'text/x-markdown'],
        'json' => ['application/json', 'text/plain'],
    ];

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

        // #9347 (audit sécurité 2026-11-10, F5) — l'extension déclarée par le
        // client ne prouve RIEN sur le contenu : un `.png` peut contenir du
        // PHP ou un exécutable. On confronte donc l'extension retenue au type
        // réellement détecté par finfo, comme le fait déjà
        // `ConversationService::ensureAttachmentAllowed()`.
        $this->assertDetectedTypeMatches($extension, $file);

        $kilobytes = (int) ceil(((int) $file->getSize()) / 1024);
        $max = self::MAX_KILOBYTES[$kind->value];

        if ($kilobytes > $max) {
            throw ValidationException::withMessages([
                'file' => 'Fichier trop volumineux ('.$kilobytes.' Ko) : maximum '.$max.' Ko pour ce type de ressource.',
            ]);
        }

        return $extension;
    }

    /**
     * #9347 — refuse un fichier dont le contenu ne correspond pas à
     * l'extension annoncée.
     *
     * La table est volontairement permissive sur les variantes MIME
     * légitimes (un `.jpg` peut être détecté `image/jpeg` ou
     * `image/pjpeg`), mais fermée sur tout le reste : un contenu non
     * reconnu (`text/x-php`, `application/x-executable`, `text/plain` pour
     * une image) est rejeté.
     */
    private function assertDetectedTypeMatches(string $extension, UploadedFile $file): void
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'Fichier illisible : impossible de vérifier son type réel.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);

        if (! is_string($detected) || $detected === '') {
            throw ValidationException::withMessages([
                'file' => 'Type de fichier indéterminé : envoi refusé.',
            ]);
        }

        $detected = strtolower($detected);
        $expected = self::EXPECTED_MIMES[$extension] ?? null;

        if ($expected === null) {
            // Extension autorisée mais sans correspondance MIME connue : on
            // refuse plutôt que d'accepter à l'aveugle (fail-closed).
            throw ValidationException::withMessages([
                'file' => 'Extension « '.$extension.' » non vérifiable : envoi refusé.',
            ]);
        }

        if (! in_array($detected, $expected, true)) {
            throw ValidationException::withMessages([
                'file' => 'Le contenu du fichier ('.$detected.') ne correspond pas à son extension « '.$extension.' ».',
            ]);
        }
    }
}
