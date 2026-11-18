<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * feat(tenant): PATCH /company/profile — issue #9332.
 *
 * Permet au `principal` de modifier les champs structurants du tenant :
 * raison sociale, fuseau horaire, devise de référence, langue, coordonnées.
 *
 * Champs explicitement HORS périmètre (réservés plateforme, ADR-0028) :
 *   slug, schema_name, tenancy_type, status, plan_id, features, metadata.
 *
 * Les changements sensibles (timezone, currency) sont journalisés dans
 * audit_logs pour répondre à l'obligation RGPD (qui/quoi/avant/après).
 */
class CompanyProfileController extends Controller
{
    /**
     * GET /api/v1/company/profile
     * Lecture du profil courant.
     */
    public function show(): JsonResponse
    {
        $company = $this->freshCompany();

        return response()->json(['data' => $this->payload($company)]);
    }

    /**
     * PATCH /api/v1/company/profile
     * Mise à jour partielle — uniquement les champs de l'allowlist.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal'), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:200'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:200'],
            'timezone' => ['sometimes', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in($this->isoCurrencies())],
            'language' => ['sometimes', 'string', Rule::in(['fr', 'en', 'ar', 'tr'])],
        ]);

        // Champs refusés explicitement (plateforme uniquement).
        $forbidden = array_intersect(
            array_keys($request->all()),
            ['slug', 'schema_name', 'tenancy_type', 'status', 'plan_id', 'features', 'metadata']
        );
        if ($forbidden !== []) {
            abort(422, __('errors.COMPANY_PROFILE_RESERVED_FIELDS', ['fields' => implode(', ', $forbidden)]));
        }

        if ($validated === []) {
            return response()->json(['data' => $this->payload($this->freshCompany())]);
        }

        $company = $this->freshCompany();
        $before = $this->sensitiveSnapshot($company);

        // Mise à jour dans la table public.companies.
        DB::table('public.companies')
            ->where('id', $company->id)
            ->update($validated);

        $company = $this->freshCompany();
        $after = $this->sensitiveSnapshot($company);

        // Journalisation des champs sensibles (RGPD #9333).
        if ($before !== $after) {
            \App\Core\Auth\Domain\Models\AuditLog::query()->create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => 'company.profile.updated',
                'auditable_type' => 'Company',
                'auditable_id' => $company->id,
                'old_values' => $before,
                'new_values' => $after,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'metadata' => ['category' => 'company_profile'],
            ]);
        }

        return response()->json(['data' => $this->payload($company)]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function freshCompany(): Company
    {
        return Company::query()
            ->from('public.companies')
            ->where('id', (string) currentCompany()->id)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'address' => $company->address,
            'city' => $company->city,
            'country' => $company->country,
            'phone' => $company->phone,
            'email' => $company->email,
            'timezone' => $company->timezone,
            'currency' => $company->currency,
            'language' => $company->language,
        ];
    }

    /**
     * Instantané des champs sensibles pour audit (timezone / currency).
     *
     * @return array<string, string|null>
     */
    private function sensitiveSnapshot(Company $company): array
    {
        return [
            'timezone' => $company->timezone,
            'currency' => $company->currency,
        ];
    }

    /**
     * Liste des codes ISO-4217 couramment utilisés.
     * Fail-closed : tout code absent est rejeté.
     *
     * @return list<string>
     */
    private function isoCurrencies(): array
    {
        return [
            'DZD', 'MAD', 'TND', 'EUR', 'USD', 'GBP', 'CAD',
            'XAF', 'XOF', 'TRY', 'NGN', 'GHS', 'ZAR', 'KES',
            'EGP', 'SAR', 'AED', 'QAR', 'KWD', 'BHD', 'OMR',
            'JPY', 'CNY', 'INR', 'BRL', 'ARS', 'MXN', 'CHF',
            'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON',
        ];
    }
}
