<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5719 — Résultat de recherche CRM normalisé.
 *
 * Le resource est volontairement discriminant (type account|contact) pour que
 * le client web (#5715) affiche une liste homogène. Les champs PII (email,
 * téléphone) restent exposés aux seuls rôles autorisés (RBAC route).
 */
class CrmSearchResultResource extends JsonResource
{
    /**
     * @param  array{type: string, model: object}  $resource
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{type: string, model: object} $row */
        $row = $this->resource;

        if ($row['type'] === 'account') {
            /** @var \App\Modules\CRM\Domain\Models\CrmAccount $account */
            $account = $row['model'];

            return [
                'type' => 'account',
                'id' => $account->id,
                'name' => $account->name,
                'legal_name' => $account->legal_name,
                'status' => $account->status,
                'owner' => $account->owner ? [
                    'id' => $account->owner->id,
                    'first_name' => $account->owner->first_name,
                    'last_name' => $account->owner->last_name,
                ] : null,
                'created_at' => $account->created_at?->toIso8601String(),
            ];
        }

        /** @var \App\Modules\CRM\Domain\Models\CrmContact $contact */
        $contact = $row['model'];

        return [
            'type' => 'contact',
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'job_title' => $contact->job_title,
            'account' => $contact->account ? [
                'id' => $contact->account->id,
                'name' => $contact->account->name,
            ] : null,
            'owner' => $contact->owner ? [
                'id' => $contact->owner->id,
                'first_name' => $contact->owner->first_name,
                'last_name' => $contact->owner->last_name,
            ] : null,
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }
}
