<x-mail::message>
# {{ $managerName }}, avez-vous essayé le pointage ? 📍

Vos employés peuvent pointer à l'arrivée et au départ directement depuis leur téléphone — avec géolocalisation optionnelle.

<x-mail::panel>
**Ce que vous pouvez faire dès maintenant :**
- ✅ Télécharger l'app Leopardo Employee
- ✅ Configurer les horaires de travail
- ✅ Consulter les anomalies de pointage
- ✅ Exporter le rapport mensuel
</x-mail::panel>

<x-mail::button :url="$mobileAppsUrl" color="success">
Télécharger les apps mobiles
</x-mail::button>

Ou connectez-vous directement sur [votre espace web]( {{ $checkInUrl }} ).

Cordialement,  
L'équipe Leopardo RH
</x-mail::message>
