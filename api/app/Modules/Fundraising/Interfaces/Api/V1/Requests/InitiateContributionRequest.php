<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Requests;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'initiation d'une contribution publique (verticale
 * FUNDRAISING — spec §5.1).
 *
 * - `website` : honeypot anti-bot — DOIT rester vide (rejet 422 s'il est
 *   rempli ; le champ est invisible pour les humains côté front) ;
 * - coordonnées minimales : téléphone requis pour mobile money (rappel
 *   opérateur), email optionnel (reçu phase 2) ;
 * - aucun montant négatif ni exubérant (le plafond fin est appliqué par
 *   l'action, via config/cagnotte).
 */
final class InitiateContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route publique isolée (throttle:shop-public)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'payment_method' => ['required', Rule::enum(ContributionMethod::class)],
            'contributor_name' => ['nullable', 'string', 'max:190'],
            'contributor_email' => ['nullable', 'email', 'max:190'],
            'contributor_phone' => ['nullable', 'string', 'max:40', 'required_if:payment_method,mobile_money'],
            'is_anonymous' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
            // Honeypot : toujours absent/vide pour un humain.
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }
}
