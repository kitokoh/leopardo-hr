<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

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
