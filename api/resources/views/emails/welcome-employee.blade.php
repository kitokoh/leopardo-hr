@component('mail::message')
# Bienvenue chez {{ $companyName }}, {{ $employeeName }} !

Votre compte Leopardo RH a été créé. Voici vos informations de connexion :

**Email :** {{ $employeeName }}
**Mot de passe temporaire :** `{{ $temporaryPassword }}`

Veuillez changer votre mot de passe lors de votre première connexion.

@component('mail::button', ['url' => $loginUrl])
Accéder à mon espace
@endcomponent

Si vous avez des questions, contactez votre responsable RH.

Cordialement,
L'équipe Leopardo RH
@endcomponent
