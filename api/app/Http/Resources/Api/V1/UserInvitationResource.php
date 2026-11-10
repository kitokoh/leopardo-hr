<?php

namespace App\Http\Resources\Api\V1;

use App\Modules\HR\Domain\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserInvitation
 */
class UserInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'employee_id' => $this->employee_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'last_sent_at' => $this->last_sent_at?->toIso8601String(),
            'status' => $this->status(),
            // #9322 : le dernier envoi a-t-il réellement abouti ? `null` quand
            // aucune tentative n'est connue (invitation antérieure au drapeau).
            // Lu depuis `metadata` — jamais depuis le jeton, qui n'est jamais
            // persisté en clair.
            'mail_delivered' => $this->lastMailDelivered(),
        ];
    }

    /**
     * #9322 — issue de la dernière tentative d'envoi, telle que persistée par
     * `UserInvitationService::createAndSend()`.
     */
    private function lastMailDelivered(): ?bool
    {
        $metadata = $this->metadata;

        if (! is_array($metadata) || ! array_key_exists('last_mail_delivered', $metadata)) {
            return null;
        }

        return (bool) $metadata['last_mail_delivered'];
    }

    private function status(): string
    {
        if ($this->accepted_at !== null) {
            return 'accepted';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
    }
}
