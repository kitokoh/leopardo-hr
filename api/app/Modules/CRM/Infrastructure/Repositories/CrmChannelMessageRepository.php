<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Repositories;

use App\Modules\CRM\Domain\Contracts\CrmChannelMessageRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmMessageStatus;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use Illuminate\Support\Carbon;

final class CrmChannelMessageRepository implements CrmChannelMessageRepositoryInterface
{
    public function findByProviderMessageId(string $companyId, string $providerMessageId): ?CrmChannelMessage
    {
        return CrmChannelMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('provider_message_id', $providerMessageId)
            ->first();
    }

    public function createOutbound(string $channelId, string $provider, ?string $toAddress, ?string $body, ?string $templateName, int $maxAttempts): CrmChannelMessage
    {
        return CrmChannelMessage::query()->create([
            'channel_id' => $channelId,
            'provider' => $provider,
            'direction' => 'outbound',
            'to_address' => $toAddress,
            'body' => $body,
            'template_name' => $templateName,
            'status' => CrmMessageStatus::QUEUED,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
        ]);
    }

    public function createInbound(string $channelId, ?string $conversationId, string $provider, string $providerMessageId, ?string $fromAddress, ?string $body): CrmChannelMessage
    {
        return CrmChannelMessage::query()->create([
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'direction' => 'inbound',
            'from_address' => $fromAddress,
            'body' => $body,
            'status' => CrmMessageStatus::DELIVERED,
        ]);
    }

    public function markSent(string $messageId, string $providerMessageId, ?float $cost): void
    {
        CrmChannelMessage::query()
            ->whereKey($messageId)
            ->update([
                'provider_message_id' => $providerMessageId,
                'status' => CrmMessageStatus::SENT,
                'cost' => $cost,
                'sent_at' => Carbon::now(),
            ]);
    }

    public function applyDeliveryStatus(string $companyId, string $providerMessageId, string $status, ?Carbon $at): ?CrmChannelMessage
    {
        $message = $this->findByProviderMessageId($companyId, $providerMessageId);
        if ($message === null) {
            return null;
        }

        $update = ['status' => $status];
        if ($at !== null) {
            $update = match ($status) {
                CrmMessageStatus::DELIVERED => ['delivered_at' => $at],
                CrmMessageStatus::READ => ['read_at' => $at],
                CrmMessageStatus::FAILED => ['failed_at' => $at, 'status' => CrmMessageStatus::FAILED],
                default => ['status' => $status],
            };
        }

        $message->forceFill($update)->save();

        return $message->refresh();
    }

    public function incrementAttempts(string $messageId): CrmChannelMessage
    {
        $message = CrmChannelMessage::query()->findOrFail($messageId);
        $message->forceFill(['attempts' => $message->attempts + 1])->save();

        return $message->refresh();
    }

    public function deadLetter(string $messageId, string $errorCode, string $errorMessage): void
    {
        CrmChannelMessage::query()
            ->whereKey($messageId)
            ->update([
                'status' => CrmMessageStatus::DEAD_LETTERED,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'failed_at' => Carbon::now(),
            ]);
    }

    public function markFailedRetryable(string $messageId, string $errorCode, string $errorMessage): void
    {
        CrmChannelMessage::query()
            ->whereKey($messageId)
            ->update([
                'status' => CrmMessageStatus::FAILED,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);
    }
}
