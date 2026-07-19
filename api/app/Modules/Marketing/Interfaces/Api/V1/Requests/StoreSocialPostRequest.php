<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
{
    /**
     * Plateformes supportees par Ayrshare (cf. commentaires
     * social_accounts/social_posts migrations Phase 1 + doc Ayrshare
     * https://www.ayrshare.com/docs/apis/overview).
     *
     * @return array<int, string>
     */
    public static function supportedPlatforms(): array
    {
        return [
            'linkedin',
            'facebook_page',
            'facebook_group',
            'twitter',
            'instagram',
            'youtube',
            'tiktok',
            'pinterest',
            'reddit',
            'telegram',
            'gmb',
            'bluesky',
            'threads',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'target_platforms' => ['required', 'array', 'min:1'],
            'target_platforms.*' => ['string', Rule::in(self::supportedPlatforms())],
            'media_paths' => ['nullable', 'array'],
            'media_paths.*' => ['string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
