# PERSONAS ET USER STORIES — LEOPARDO RH
# Version 2.0 | Mars 2026

---

## PERSONA 1 — KARIM, DIRECTEUR RH (Utilisateur gestionnaire)

**Profil :**
- Karim, 38 ans, DRH d'une entreprise de construction de 85 employés à Alger
- Utilise Excel + WhatsApp pour gérer les présences et les congés
- Perd 2 journées par mois à calculer la paie manuellement
- Son patron lui reproche les erreurs de paie récurrentes

**Objectifs :**
- Calculer la paie sans erreur en moins d'une heure
- Savoir en temps réel qui est présent/absent sur les chantiers
- Archiver les justificatifs de congé sans papier

**Douleurs :**
- Employés qui ne pointent pas → il ne sait pas qui est là
- Calcul manuel des heures sup → erreurs et réclamations
- Pas de trace en cas de contrôle social

**Citation :** *"Je passe mes lundis à collecter les feuilles de présence des 4 chefs de chantier. C'est une honte en 2026."*

---

## PERSONA 2 — SARA, EMPLOYÉE (Utilisatrice mobile)

**Profil :**
- Sara, 27 ans, comptable dans une PME industrielle de 40 personnes à Casablanca
- Utilise son smartphone Android toute la journée
- Doit envoyer un email à son RH pour demander un congé, puis relancer 3 fois
- Ne sait jamais combien de jours de congé il lui reste

**Objectifs :**
- Pointer son arrivée en 2 secondes depuis son téléphone
- Soumettre une demande de congé sans email ni papier
- Voir son bulletin de paie directement sur l'app

**Douleurs :**
- Incertitude sur son solde de congés
- Attente pour les approbations (3 à 5 jours)
- Pas d'accès à ses fiches de paie depuis chez elle

**Citation :** *"Pour demander un congé, j'envoie un mail, je WhatsApp ma chef, et j'attends. Parfois ça prend une semaine."*

---

## PERSONA 3 — MEHMET, DIRIGEANT PME (Utilisateur décisionnel)

**Profil :**
- Mehmet, 45 ans, PDG d'une chaîne de 3 magasins de textile à Istanbul, 60 employés
- Délègue les RH à un assistant RH mais veut de la visibilité
- Voyage souvent, accède à tout depuis son iPhone
- Veut contrôler les coûts salariaux sans être dans les détails

**Objectifs :**
- Voir en un coup d'œil le taux de présence du jour
- Recevoir une alerte si la masse salariale dépasse le budget
- Valider les paies sur mobile avant virement

**Douleurs :**
- Découvre les absences à posteriori
- Ne sait pas combien coûtent les heures sup ce mois
- Peur d'un contrôle URSSAF / Inspection du travail

**Citation :** *"Mon comptable me prépare les fiches de paie le 28 de chaque mois. Si y'a une erreur, c'est trop tard."*

---

## USER STORIES PAR MODULE

### MODULE AUTH
```
US-A01 : En tant qu'employé, je veux me connecter avec mon email et mot de passe
          afin d'accéder à mon espace personnel sur mobile.

US-A02 : En tant que gestionnaire, je veux réinitialiser mon mot de passe par email
          afin de ne pas être bloqué en cas d'oubli.

US-A03 : En tant qu'employé, je veux rester connecté 90 jours sur mon téléphone
          afin de ne pas avoir à me reconnecter chaque jour.
```

### MODULE POINTAGE
```
US-P01 : En tant qu'employé, je veux pointer mon arrivée en un tap
          afin de ne pas perdre de temps le matin.

US-P02 : En tant qu'employé, je veux voir l'heure à laquelle j'ai pointé ce matin
          afin de vérifier que mon pointage est bien enregistré.

US-P03 : En tant que gestionnaire, je veux voir en temps réel qui est présent aujourd'hui
          afin de gérer les absences imprévues immédiatement.

US-P04 : En tant que gestionnaire, je veux corriger un pointage oublié d'un employé
          afin d'éviter une anomalie dans la paie du mois.

US-P05 : En tant que gestionnaire, je veux recevoir une alerte si un employé n'a pas
          pointé 1h après son heure de début afin d'agir rapidement.
```

### MODULE ABSENCES
```
US-AB01 : En tant qu'employé, je veux voir mon solde de congés en temps réel
           afin de planifier mes vacances sereinement.

US-AB02 : En tant qu'employé, je veux soumettre une demande de congé depuis mon téléphone
           afin d'éviter emails et papiers.

US-AB03 : En tant que gestionnaire, je veux approuver ou refuser une demande en un tap
           afin de répondre rapidement à mes collaborateurs.

US-AB04 : En tant qu'employé, je veux être notifié immédiatement de l'approbation
           afin de pouvoir organiser mon absence.
```

### MODULE PAIE
```
US-PA01 : En tant que gestionnaire, je veux calculer automatiquement la paie du mois
           afin de réduire les erreurs et le temps de traitement.

US-PA02 : En tant qu'employé, je veux accéder à mon bulletin de paie depuis l'app
           afin de le consulter sans déranger les RH.

US-PA03 : En tant que gestionnaire, je veux exporter les données de virement bancaire
           afin d'éviter la ressaisie dans le logiciel de la banque.

US-PA04 : En tant que dirigeant, je veux voir le récapitulatif de la masse salariale du mois
           afin de contrôler les coûts avant validation.
```

### MODULE TÂCHES
```
US-T01 : En tant que gestionnaire, je veux assigner une tâche à un employé avec une échéance
          afin de suivre son avancement sans réunion.

US-T02 : En tant qu'employé, je veux voir mes tâches du jour sur l'app mobile
          afin de savoir sur quoi me concentrer.

US-T03 : En tant qu'employé, je veux changer le statut d'une tâche (en cours / terminé)
          afin de tenir mon manager informé sans l'appeler.
```

### MODULE AVANCES
```
US-AV01 : En tant qu'employé, je veux soumettre une demande d'avance sur salaire depuis l'app
           afin de ne pas avoir à en parler directement à mon responsable.

US-AV02 : En tant que gestionnaire, je veux voir le plan de remboursement d'une avance
           afin de m'assurer qu'il est correctement déduit sur les prochaines paies.
```

---

## CRITÈRES D'ACCEPTANCE (Definition of Done par module)

| Module | Critère principal |
|--------|-------------------|
| Pointage | Un employé peut pointer et voir son log en < 3 secondes |
| Absences | Une demande soumise est notifiée au gestionnaire en < 30 secondes |
| Paie | La paie d'un employé est calculée en < 500ms (hors PDF) |
| Tâches | Un changement de statut est visible par le manager en temps réel |
| Auth | Token stocké sécurisé, session maintenue 90 jours mobile |

---

## PERSONA 4 - MURAT, PETIT PATRON (Usage informel)

**Profil :**
- Murat, 42 ans, proprietaire d'un restaurant de 8 employes a Bursa.
- Gere seul depuis son telephone, sans assistant RH.
- Salaries payes au jour ou a l'heure selon les postes.

**Objectifs :**
- Savoir en quelques secondes ce qu'il doit a chaque employe.
- Produire une trace simple en cas de litige ou depart brusque.
- Demarrer sans parametrage RH complexe.

**Douleurs :**
- Desaccords frequents sur heures sup et montants dus.
- Pas de calcul instantane sur une periode partielle.
- Trop de temps perdu en fin de semaine pour consolider les heures.

**Citation :** *"Je veux juste savoir ce que je dois payer aujourd'hui, sans Excel."*

---

## USER STORIES SUPPLEMENTAIRES (Persona Murat)

```
US-M01 : En tant que patron, je veux voir ce que je dois aujourd'hui a chaque employe
         afin d'eviter les surprises de fin de journee.

US-M02 : En tant que patron, je veux simuler ce que je dois sur une periode libre
         afin de regler rapidement un depart en cours de mois.

US-M03 : En tant que patron, je veux generer un recu simple de periode
         afin d'avoir une preuve partageable en cas de litige.

US-M04 : En tant qu'employe journalier/horaire, je veux voir mon gain estime du jour
         afin de verifier immediatement mes heures et ma paie.

US-M05 : En tant que patron, je veux ajouter un employe en moins de 2 minutes
         afin de commencer a pointer sans configuration avancee.
```
