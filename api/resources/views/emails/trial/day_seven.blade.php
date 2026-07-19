@component('mail::message')
# Votre essai se termine bientôt, {{ $managerName }}

Vous gérez actuellement **{{ $employeeCount }}** employé(s) sur **{{ $company->name }}** avec Leopardo RH.

Pour continuer à profiter de toutes les fonctionnalités sans interruption, passez à un plan payant dès maintenant.

@component('mail::button', ['url' => $upgradeUrl])
Passer à un plan payant
@endcomponent

Vous voulez comparer nos offres avant de vous décider ?

@component('mail::button', ['url' => $pricingUrl])
Voir les tarifs
@endcomponent

Une question ? Répondez directement à cet email, notre équipe est là pour vous aider.

Cordialement,
L'équipe Leopardo RH
@endcomponent
