<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5272 — Initiation d'un checkout de paiement en ligne. Les URLs de retour
 * sont optionnelles : en leur absence, le serveur dérive des URLs par défaut
 * (portail client) depuis APP_URL.
 */
final class CheckoutRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'success_url' => ['nullable', 'url', 'max:2048'],
            'cancel_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
