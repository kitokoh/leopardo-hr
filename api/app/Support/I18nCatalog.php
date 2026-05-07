<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class I18nCatalog
{
    private const DEFAULT_LOCALE = 'fr';

    private const RTL_LOCALES = ['ar'];

    public static function rootPath(): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'i18n';
    }

    public static function localePath(string $locale): string
    {
        return self::rootPath().DIRECTORY_SEPARATOR.'locales'.DIRECTORY_SEPARATOR.$locale.'.json';
    }

    public static function versionsPath(): string
    {
        return self::rootPath().DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.'versions.json';
    }

    public static function normalizeLocale(?string $locale): string
    {
        $value = Str::of($locale ?? self::DEFAULT_LOCALE)
            ->replace('_', '-')
            ->lower()
            ->trim()
            ->value();

        $base = substr($value, 0, 2);

        return in_array($base, ['fr', 'ar', 'tr', 'en'], true) ? $base : self::DEFAULT_LOCALE;
    }

    public static function isRtl(string $locale): bool
    {
        return in_array(self::normalizeLocale($locale), self::RTL_LOCALES, true);
    }

    public static function readLocale(string $locale): array
    {
        $normalized = self::normalizeLocale($locale);
        $path = self::localePath($normalized);

        if (! File::exists($path)) {
            throw new RuntimeException("Locale catalog not found for [{$normalized}]");
        }

        /** @var array<string, mixed> $catalog */
        $catalog = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return $catalog;
    }

    public static function readVersions(): array
    {
        /** @var array<string, mixed> $versions */
        $versions = json_decode((string) File::get(self::versionsPath()), true, 512, JSON_THROW_ON_ERROR);

        return $versions;
    }

    public static function checksumFor(string $locale): ?string
    {
        $versions = self::readVersions();
        $normalized = self::normalizeLocale($locale);

        return Arr::get($versions, "locales.{$normalized}.checksum");
    }
}
