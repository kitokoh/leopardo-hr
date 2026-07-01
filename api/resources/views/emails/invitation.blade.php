@component('mail::message')
# Invitation à rejoindre {{ $companyName }}

**{{ $inviterName }}** vous invite à rejoindre **{{ $companyName }}** sur Leopardo RH.

@if($role)
Vous serez invité(e) en tant que : **{{ $role }}**
@endif

@component('mail::button', ['url' => $invitationUrl])
Accepter l'invitation
@endcomponent

Ce lien expire dans 48h. Si vous n'attendiez pas cet email, ignorez-le.

Cordialement,
L'équipe Leopardo RH
@endcomponent
