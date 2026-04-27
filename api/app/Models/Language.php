<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Language extends Model
{
    protected $table = 'languages';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name_fr',
        'name_native',
        'is_rtl',
        'is_active',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const SUPPORTED = ['fr', 'ar', 'tr', 'en'];

    public const DEFAULT = 'fr';

    private const ACTIVE_CODES_CACHE_KEY = 'languages.active_codes';

    public static function isSupported(string $code): bool
    {
        return in_array(strtolower($code), self::activeCodes(), true);
    }

    public static function isRtl(string $code): bool
    {
        return static::query()
            ->where('code', strtolower($code))
            ->where('is_rtl', true)
            ->exists();
    }

    public static function activeCodes(): array
    {
        return Cache::remember(self::ACTIVE_CODES_CACHE_KEY, now()->addMinutes(10), function (): array {
            if (! Schema::hasTable('languages')) {
                return self::SUPPORTED;
            }

            $codes = static::query()
                ->where('is_active', true)
                ->pluck('code')
                ->map(fn (string $code) => strtolower($code))
                ->values()
                ->all();

            return $codes !== [] ? $codes : self::SUPPORTED;
        });
    }

    protected static function booted(): void
    {
        $flush = static function (): void {
            Cache::forget(self::ACTIVE_CODES_CACHE_KEY);
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
