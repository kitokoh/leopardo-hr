<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Actions\SendChannelMessage;
use App\Modules\CRM\Application\DTOs\SendChannelMessageDTO;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Domain\Exceptions\CrmChannelException;
use App\Modules\CRM\Domain\Exceptions\CrmChannelNotFoundException;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelConversation;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use App\Modules\CRM\Infrastructure\Services\CrmWebhookLookupService;
use App\Modules\CRM\Interfaces\Api\V1\Requests\ConfigureChannelRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\SendChannelMessageRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmChannelConversationResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmChannelMessageResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmChannelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Canaux de communication CRM (issues #5725/#5727).
 *
 * RBAC : groupe api.manager:principal,rh (routes/modules/crm.php) — les
 * membres du tenant avec rôle CRM peuvent configurer les canaux et consulter
 * les messages/conversations. Toute donnée est tenant-scopée
 * (BelongsToCompany + recherche explicite dans le tenant courant).
 */
class CrmChannelController extends Controller
{
    public function __construct(
        private readonly SendChannelMessage $sendChannelMessage,
        private readonly CrmWebhookLookupService $lookupService,
    ) {}

    /** @return AnonymousResourceCollection<int, CrmChannelResource> */
    public function index(): AnonymousResourceCollection
    {
        return CrmChannelResource::collection(
            CrmChannel::query()->orderBy('created_at')->paginate(50),
        );
    }

    public function store(ConfigureChannelRequest $request): JsonResponse
    {
        $type = (string) $request->validated('type');
        $provider = (string) $request->validated('provider');

        if (! CrmChannelType::isValid($type)) {
            return $this->error('CRM_CHANNEL_TYPE_INVALID', 422);
        }

        $channel = CrmChannel::query()->firstOrNew([
            'company_id' => currentCompany()->id,
            'type' => $type,
            'provider' => $provider,
        ]);

        $channel->is_configured = (bool) $request->input('is_configured', false);
        $channel->monthly_quota = $request->input('monthly_quota');
        if ($request->has('settings')) {
            $channel->settings = $request->input('settings');
        }
        if ($channel->status === 'archived') {
            $channel->status = 'inactive';
        }
        $channel->save();

        $this->syncLookup($channel);

        return (new CrmChannelResource($channel))->response()->setStatusCode(201);
    }

    public function update(ConfigureChannelRequest $request, string $channel): JsonResponse
    {
        $channelModel = $this->channelOrFail($channel);

        if ($request->has('is_configured')) {
            $channelModel->is_configured = (bool) $request->input('is_configured');
        }
        if ($request->has('monthly_quota')) {
            $channelModel->monthly_quota = $request->input('monthly_quota');
        }
        if ($request->has('settings')) {
            $channelModel->settings = $request->input('settings');
        }
        $channelModel->save();

        $this->syncLookup($channelModel);

        return (new CrmChannelResource($channelModel))->response();
    }

    public function send(SendChannelMessageRequest $request, string $channel): JsonResponse
    {
        $dto = SendChannelMessageDTO::fromArray([
            'channel_id' => $channel,
            ...$request->validated(),
        ]);

        try {
            $message = $this->sendChannelMessage->execute($dto);
        } catch (CrmChannelException $e) {
            Log::warning('CRM channel send refused', [
                'channel_id' => $channel,
                'code' => $e->errorCode(),
            ]);

            return $this->error($e->errorCode(), $e->httpStatus());
        }

        return (new CrmChannelMessageResource($message))->response()->setStatusCode(201);
    }

    /** @return AnonymousResourceCollection<int, CrmChannelMessageResource> */
    public function messages(Request $request, string $channel): AnonymousResourceCollection
    {
        $this->channelOrFail($channel);

        $query = CrmChannelMessage::query()->where('channel_id', $channel);

        if ($request->has('status')) {
            $status = (string) $request->input('status');
            if (CrmChannelMessage::isValidStatus($status)) {
                $query->where('status', $status);
            }
        }

        return CrmChannelMessageResource::collection(
            $query->orderByDesc('created_at')->paginate((int) $request->input('per_page', 25)),
        );
    }

    /** @return AnonymousResourceCollection<int, CrmChannelConversationResource> */
    public function conversations(string $channel): AnonymousResourceCollection
    {
        $this->channelOrFail($channel);

        return CrmChannelConversationResource::collection(
            CrmChannelConversation::query()
                ->where('channel_id', $channel)
                ->orderByDesc('last_message_at')
                ->paginate(50),
        );
    }

    private function channelOrFail(string $channelId): CrmChannel
    {
        $channel = CrmChannel::query()->where('id', $channelId)->first();
        if ($channel === null) {
            throw new CrmChannelNotFoundException();
        }

        return $channel;
    }

    private function syncLookup(CrmChannel $channel): void
    {
        $phoneNumberId = is_string($channel->settings['phone_number_id'] ?? null)
            ? $channel->settings['phone_number_id']
            : null;

        if ($channel->type === CrmChannelType::WHATSAPP && $phoneNumberId !== null && $phoneNumberId !== '') {
            $this->lookupService->upsert('whatsapp', $phoneNumberId, (string) $channel->company_id, (string) $channel->id);

            return;
        }

        $this->lookupService->deleteForChannel((string) $channel->id);
    }

    private function error(string $code, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code], $status);
    }
}
