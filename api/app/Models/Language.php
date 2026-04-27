<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public static function isSupported(string $code): bool
    {
        return in_array($code, self::SUPPORTED, true);
    }

    public static function isRtl(string $code): bool
    {
        return $code === 'ar';
    }
}
