<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'sites';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'address', 'gps_lat', 'gps_lng', 'gps_radius_m'];
    protected $casts = ['gps_lat' => 'float', 'gps_lng' => 'float', 'created_at' => 'datetime'];
}
