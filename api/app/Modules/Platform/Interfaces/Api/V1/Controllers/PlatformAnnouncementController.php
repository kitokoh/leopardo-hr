<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlatformAnnouncementResource;
use App\Modules\Platform\Domain\Models\PlatformAnnouncement;
use App\Modules\Platform\Infrastructure\Services\PlatformAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PA2-COMM-005 — Super-admin broadcasts a platform-wide announcement
 * (maintenance, new feature, incident, action required) to every company,
 * or an explicit subset of companies.
 */
class PlatformAnnouncementController extends Controller
{
    public function __construct(
        private readonly PlatformAnnouncementService $announcements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $announcements = PlatformAnnouncement::query()
            ->with('author', 'companies:companies.id')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return PlatformAnnouncementResource::collection($announcements)->response();
    }

    public function show(PlatformAnnouncement $announcement): JsonResponse
    {
        return (new PlatformAnnouncementResource($announcement->load('author', 'companies:companies.id')))
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var SuperAdmin $author */
        $author = $request->user('super_admin_api');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(PlatformAnnouncement::categories())],
            'severity' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'audience_type' => ['nullable', Rule::in(PlatformAnnouncement::audienceTypes())],
            'company_ids' => ['required_if:audience_type,companies', 'array', 'min:1'],
            'company_ids.*' => ['string', 'uuid', 'exists:companies,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if (($data['audience_type'] ?? PlatformAnnouncement::AUDIENCE_ALL) === PlatformAnnouncement::AUDIENCE_COMPANIES
            && empty($data['company_ids'])
        ) {
            throw ValidationException::withMessages([
                'company_ids' => 'At least one company must be selected for a targeted announcement.',
            ]);
        }

        $announcement = $this->announcements->publish($author, $data);

        return (new PlatformAnnouncementResource($announcement->load('author', 'companies:companies.id')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(PlatformAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'Platform announcement deleted.']);
    }
}
