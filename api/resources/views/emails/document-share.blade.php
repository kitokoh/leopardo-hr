<div style="font-family: Arial, sans-serif; color: #0f172a; max-width: 600px; margin: 0 auto;">
    <h2 style="color: #0f766e;">{{ __('accounting.email_heading') }}</h2>
    <p>{{ __('accounting.email_body', ['number' => $documentName]) }}</p>
    <p style="text-align: center; margin: 32px 0;">
        <a href="{{ $portalUrl }}"
           style="display: inline-block; background: #0f766e; color: #ffffff; padding: 12px 32px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            {{ __('accounting.email_button') }}
        </a>
    </p>
    @if($share->expires_at)
    <p style="color: #64748b; font-size: 14px;">{{ __('accounting.email_expires', ['date' => $share->expires_at->format('d/m/Y H:i')]) }}</p>
    @endif
    <p style="color: #94a3b8; font-size: 12px; margin-top: 32px;">{{ __('accounting.email_footer') }}</p>
</div>
