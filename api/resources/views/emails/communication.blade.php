<div style="font-family: Arial, sans-serif; color: #0f172a; white-space: pre-line;">
    <p>{{ $bodyText }}</p>
    @if($unsubscribeUrl)
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0 12px;">
        <p style="font-size: 12px; color: #64748b;">
            <a href="{{ $unsubscribeUrl }}">{{ __('emails.communication_unsubscribe_link') }}</a>
        </p>
    @endif
</div>
