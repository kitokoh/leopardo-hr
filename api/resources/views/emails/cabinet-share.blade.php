<div style="font-family: Arial, sans-serif; color: #0f172a; max-width: 600px; margin: 0 auto;">
    <h2 style="color: #1e40af;">{{ __('cabinet.share_email_heading') }}</h2>
    <p>{{ __('cabinet.share_email_body', ['name' => $ownerName, 'type' => __('cabinet.type_'.$shareableType), 'item' => $shareableName]) }}</p>
    <p style="text-align: center; margin: 32px 0;">
        <a href="{{ $shareUrl }}"
           style="display: inline-block; background: #1e40af; color: #ffffff; padding: 12px 32px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            {{ __('cabinet.share_email_button') }}
        </a>
    </p>
    @if($share->expires_at)
    <p style="color: #64748b; font-size: 14px;">{{ __('cabinet.share_email_expires', ['date' => $share->expires_at->format('d/m/Y H:i')]) }}</p>
    @endif
    <p style="color: #94a3b8; font-size: 12px; margin-top: 32px;">{{ __('cabinet.share_email_footer') }}</p>
</div>
