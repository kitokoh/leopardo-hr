<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Models\CrmWebhookChannelLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenue du lookup public des webhooks CRM (issue #5725).
 *
 * Upsert/delete conditionnel (transaction + relecture) : la table public est
 * partagée entre tenants, jamais de suppression destructive en aveugle.
 */
final class CrmWebhookLookupService
{
    public function upsert(string $provider, string $providerKey, string $companyId, string $channelId): void
    {
        try {
            DB::transaction(function () use ($provider, $providerKey, $companyId, $channelId): void {
                CrmWebhookChannelLookup::query()->updateOrCreate(
                    ['provider' => $provider, 'provider_key' => $providerKey],
                    ['company_id' => $companyId, 'channel_id' => $channelId],
                );
            });
        } catch (\Throwable $e) {
            Log::error('CRM webhook lookup: upsert échoué', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function findByProviderKey(string $provider, string $providerKey): ?CrmWebhookChannelLookup
    {
        return CrmWebhookChannelLookup::query()
            ->where('provider', $provider)
            ->where('provider_key', $providerKey)
            ->first();
    }

    public function deleteForChannel(string $channelId): void
    {
        CrmWebhookChannelLookup::query()
            ->where('channel_id', $channelId)
            ->delete();
    }
}
