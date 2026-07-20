<x-mail::message>
# Bienvenue sur Leopardo RH, {{ $managerName }} ! 🦁

Votre essai gratuit pour **{{ $company->name }}** est maintenant actif.

Voici vos premières étapes pour démarrer en moins de 10 minutes :

<x-mail::panel>
**1. Ajoutez vos employés**
Importez votre équipe via CSV ou ajoutez-les un par un.

**2. Activez le pointage mobile**
Téléchargez l'app Leopardo et faites pointer votre équipe dès aujourd'hui.

**3. Configurez la paie**
Définissez les salaires de base et les règles de calcul.
</x-mail::panel>

<x-mail::button :url="$loginUrl" color="success">
Accéder à mon espace
</x-mail::button>

Besoin d'aide ? Consultez notre [documentation complète]( {{ $docsUrl }} ).

Cordialement,  
L'équipe Leopardo RH
</x-mail::message>
