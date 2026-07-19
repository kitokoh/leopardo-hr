@component('mail::message')
# Bienvenue sur Leopardo RH, {{ $managerName }} 👋

Votre espace **{{ $company->name }}** est prêt. Voici vos premières étapes pour bien démarrer :

@component('mail::panel')
1. Connectez-vous à votre tableau de bord
2. Ajoutez vos premiers employés
3. Configurez vos horaires et sites
@endcomponent

@component('mail::button', ['url' => $loginUrl])
Ouvrir mon tableau de bord
@endcomponent

Besoin d'aide pour démarrer ? Consultez notre [documentation]({{ $docsUrl }}) ou répondez directement à cet email.

Cordialement,
L'équipe Leopardo RH
@endcomponent
