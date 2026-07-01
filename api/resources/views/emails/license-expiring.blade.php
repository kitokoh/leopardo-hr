@component('mail::message')
# ⚠️ Votre licence Edge expire dans {{ $daysRemaining }} jours

Bonjour,

La licence Edge de **{{ $companyName }}** expire le **{{ $expiresAt }}**.

Sans renouvellement, vos nœuds Edge ne pourront plus synchroniser les données RH.

@component('mail::button', ['url' => $renewUrl, 'color' => 'error'])
Renouveler maintenant
@endcomponent

Cordialement,
L'équipe Leopardo RH
@endcomponent
