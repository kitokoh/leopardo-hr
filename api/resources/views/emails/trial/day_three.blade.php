@component('mail::message')
# Avez-vous essayé le pointage mobile, {{ $managerName }} ?

Vous êtes sur Leopardo RH depuis 3 jours. Voici une astuce pour tirer le meilleur parti de la plateforme :

Le **pointage mobile** permet à vos employés de badger directement depuis leur téléphone, avec géolocalisation optionnelle.

@component('mail::button', ['url' => $checkInUrl])
Configurer le pointage
@endcomponent

Vous pouvez aussi télécharger nos applications mobiles pour Android et iOS :

@component('mail::button', ['url' => $mobileAppsUrl])
Télécharger les applications
@endcomponent

Besoin d'aide ? Répondez directement à cet email.

Cordialement,
L'équipe Leopardo RH
@endcomponent
