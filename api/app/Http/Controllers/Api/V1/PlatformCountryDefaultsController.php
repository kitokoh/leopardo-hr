<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;

class PlatformCountryDefaultsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'data' => CountryDefaults::all(),
        ]);
    }
}
