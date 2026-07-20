<x-mail::message>
# {{ $managerName }}, votre essai se termine bientôt ⏰

Vous avez utilisé Leopardo RH pendant 7 jours avec **{{ $employeeCount }} employé(s)** dans {{ $company->name }}.

Il est temps de passer à la version complète pour ne pas perdre vos données.

<x-mail::panel>
**Ce que vous gardez en passant à la version payante :**
- ✅ Tous vos employés et leurs données
- ✅ Historique de pointage complet
- ✅ Bulletins de paie générés
- ✅ Documents dans le cabinet numérique
</x-mail::panel>

<x-mail::button :url="$upgradeUrl" color="success">
Activer mon abonnement
</x-mail::button>

Consultez nos [tarifs]( {{ $pricingUrl }} ) — à partir de **29€/mois** pour une équipe jusqu'à 10 personnes.

Cordialement,  
L'équipe Leopardo RH
</x-mail::message>
