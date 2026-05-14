<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $address
 * @property float $gps_lat
 * @property float $gps_lng
 * @property int $gps_radius_m
 * @property Carbon|null $created_at
 */
class Site extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'sites';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'address', 'gps_lat', 'gps_lng', 'gps_radius_m'];

    protected $casts = ['gps_lat' => 'float', 'gps_lng' => 'float', 'created_at' => 'datetime'];
}
