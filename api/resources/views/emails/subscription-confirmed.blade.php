@component('mail::message')
# Abonnement confirmé — {{ $companyName }}

Merci pour votre abonnement ! Votre plan **{{ strtoupper($plan) }}** est maintenant actif.

**Prochaine facture :** {{ \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') }}

@component('mail::button', ['url' => $dashboardUrl])
Accéder au tableau de bord
@endcomponent

@component('mail::button', ['url' => $invoiceUrl, 'color' => 'success'])
Télécharger la facture
@endcomponent

Merci de faire confiance à Leopardo RH.
@endcomponent
