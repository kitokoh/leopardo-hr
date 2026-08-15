<x-mail::message>
# Réinitialisation de votre mot de passe

Vous recevez cet email suite à une demande de réinitialisation du mot de passe
de votre compte Leopardo.

Votre code de réinitialisation (valable **60 minutes**, usage unique) :

# {{ $token }}

Si vous n'êtes pas à l'origine de cette demande, ignorez cet email — votre
mot de passe actuel reste inchangé.

<x-mail::button :url="config('app.url')">
Accéder à Leopardo
</x-mail::button>

Cordialement,<br>
L'équipe Leopardo RH
</x-mail::message>
