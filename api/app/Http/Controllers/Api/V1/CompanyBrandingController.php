<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyBrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->payload($this->freshCompany()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $validated = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:120'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'logo' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'primary_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_mode' => ['sometimes', 'nullable', 'in:default,light,dark,auto'],
        ]);

        $company = $this->freshCompany();
        $metadata = $company->metadata ?? [];
        $branding = $this->brandingFrom($company);

        foreach (['display_name', 'primary_color', 'accent_color', 'brand_mode'] as $field) {
            if (array_key_exists($field, $validated)) {
                $branding[$field] = $validated[$field];
            }
        }

        if (array_key_exists('logo_url', $validated)) {
            $branding['logo_url'] = $validated['logo_url'];
            $this->deletePreviousLogo($branding);
            $branding['logo_path'] = null;
            $branding['logo_disk'] = null;
        }

        if ($request->hasFile('logo')) {
            $this->deletePreviousLogo($branding);
            $path = $request->file('logo')->store("company-branding/{$company->id}", 'public');
            $branding['logo_path'] = $path;
            $branding['logo_disk'] = 'public';
            $branding['logo_url'] = Storage::disk('public')->url($path);
        }

        $metadata['branding'] = $this->normalizeBranding($branding, $company);
        $this->persistMetadata($company, $metadata);

        return new JsonResponse([
            'data' => $this->payload($this->freshCompany()),
            'message' => 'Identite entreprise mise a jour.',
        ]);
    }

    private function freshCompany(): Company
    {
        $company = currentCompany();

        return Company::query()
            ->from($this->companiesTable())
            ->where('id', $company->id)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(Company $company): array
    {
        return [
            'company_id' => $company->id,
            'branding' => $this->normalizeBranding($this->brandingFrom($company), $company),
        ];
    }

    /** @return array<string, mixed> */
    private function brandingFrom(Company $company): array
    {
        $metadata = $company->metadata ?? [];
        $branding = $metadata['branding'] ?? [];

        return is_array($branding) ? $branding : [];
    }

    /** @param array<string, mixed> $branding */
    private function normalizeBranding(array $branding, Company $company): array
    {
        return [
            'display_name' => $this->stringOrNull($branding['display_name'] ?? null) ?: $company->name,
            'logo_url' => $this->stringOrNull($branding['logo_url'] ?? null),
            'logo_path' => $this->stringOrNull($branding['logo_path'] ?? null),
            'logo_disk' => $this->stringOrNull($branding['logo_disk'] ?? null),
            'primary_color' => $this->hexOrDefault($branding['primary_color'] ?? null, '#10B981'),
            'accent_color' => $this->hexOrDefault($branding['accent_color'] ?? null, '#2563EB'),
            'brand_mode' => in_array($branding['brand_mode'] ?? null, ['default', 'light', 'dark', 'auto'], true)
                ? $branding['brand_mode']
                : 'default',
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hexOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtoupper($value)
            : $default;
    }

    /** @param array<string, mixed> $metadata */
    private function persistMetadata(Company $company, array $metadata): void
    {
        DB::table($this->companiesTable())
            ->where('id', $company->id)
            ->update([
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }

    /** @param array<string, mixed> $branding */
    private function deletePreviousLogo(array $branding): void
    {
        if (($branding['logo_disk'] ?? null) !== 'public') {
            return;
        }

        $path = $this->stringOrNull($branding['logo_path'] ?? null);
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
