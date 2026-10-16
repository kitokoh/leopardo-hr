<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — nature d'une ressource de la médiathèque (brief §C).
 */
enum MarketingAssetKind: string
{
    case Video = 'video';
    case Image = 'image';
    case Logo = 'logo';
    case Illustration = 'illustration';
    case Script = 'script';
    case Prompt = 'prompt';
    case Copy = 'copy';
    case Document = 'document';
    case Audio = 'audio';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Vidéo',
            self::Image => 'Image',
            self::Logo => 'Logo',
            self::Illustration => 'Illustration',
            self::Script => 'Script',
            self::Prompt => 'Prompt',
            self::Copy => 'Texte / légende',
            self::Document => 'Document',
            self::Audio => 'Audio',
            self::Other => 'Autre',
        };
    }

    /**
     * Extensions acceptées en upload. Une ressource `script`/`copy`/
     * `prompt` est un contenu TEXTE : elle n'a pas de binaire associé et
     * se crée sans fichier.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::Video => ['mp4', 'mov', 'webm', 'm4v'],
            self::Image, self::Illustration => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
            self::Logo => ['png', 'svg', 'webp'],
            self::Audio => ['mp3', 'wav', 'm4a', 'ogg'],
            self::Document => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
            self::Script, self::Prompt, self::Copy => ['txt', 'md', 'json'],
            self::Other => ['txt', 'pdf', 'json', 'md'],
        };
    }

    /** Un fichier est-il exigé pour ce type de ressource ? */
    public function requiresFile(): bool
    {
        return ! in_array($this, [self::Script, self::Prompt, self::Copy], true);
    }
}
