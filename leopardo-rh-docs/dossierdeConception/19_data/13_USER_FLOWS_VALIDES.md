# USER FLOWS VALIDÉS — LEOPARDO RH
# Version 1.0 | Mars 2026
# Remplace : users_flows_complet.md (supprimé — contradictions avec les décisions validées)

---

## RAPPEL DES DÉCISIONS QUI IMPACTENT LES FLOWS

Avant de lire les flows, garder ces décisions en tête :
- Photo et GPS au pointage : **DÉSACTIVÉS PAR DÉFAUT** — option à activer
- Avances sur salaire : **DÉSACTIVÉES PAR DÉFAUT** — option à activer
- Facturation : **MANUELLE en Phase 1** — pas de paiement intégré au démarrage
- Onboarding entreprise : **Créé par le Super Admin** — pas d'inscription publique en Phase 1

---

## FLOW 1 — ONBOARDING ENTREPRISE (Phase 1 : Super Admin uniquement)

```
Super Admin se connecte au panneau admin
    ↓
Clique "Créer une entreprise"
    ↓
Remplit le formulaire :
  - Nom, secteur, pays, ville
  - Email du premier gestionnaire
  - Langue principale (fr/ar/tr/en)
  - Devise, fuseau horaire
  - Plan tarifaire sélectionné
  - Pays → sélection du modèle RH pré-rempli
    ↓
Système exécute automatiquement :
  1. Création UUID entreprise
  2. CREATE SCHEMA company_{uuid} (PostgreSQL)
  3. Application du modèle RH du pays (cotisations, IR, congés, jours fériés)
  4. Insertion types d'absence standards
  5. Création planning par défaut (8h-17h, Lun-Ven)
  6. Création compte du premier gestionnaire
  7. Envoi email de bienvenue avec identifiants temporaires
    ↓
Super Admin voit la confirmation : "Entreprise créée, email envoyé à gestionnaire@email.com"
    ↓
Gestionnaire reçoit l'email → clique le lien → change son mot de passe → accède au dashboard
```

**Note Phase 2 :** Un formulaire d'inscription publique sera ajouté avec période d'essai automatique.

---

## FLOW 2 — AJOUT D'UN EMPLOYÉ

```
Gestionnaire (Principal ou RH) dans le dashboard web
    ↓
Menu "Employés" → "Ajouter un employé"
    ↓
Formulaire en étapes :
  Étape 1 : Infos personnelles (prénom, nom, email, téléphone)
  Étape 2 : Contrat (type, date début, planning, département, poste)
  Étape 3 : Salaire (base, type rémunération, IBAN si virement)
    ↓
Système :
  - Génère le matricule auto (EMP-0001, EMP-0002...)
  - Génère un mot de passe temporaire
  - Crée le compte employees
  - Envoie email à l'employé avec ses identifiants et lien app mobile
    ↓
Employé reçoit email → télécharge l'app → se connecte → change son mot de passe
```

---

## FLOW 3 — POINTAGE EMPLOYÉ (Mode déclaratif — par défaut)

```
Employé ouvre l'app Leopardo RH (session maintenue — pas de reconnexion)
    ↓
Écran d'accueil s'affiche immédiatement avec :
  - Grand bouton "POINTER MON ARRIVÉE" (ou "MON DÉPART" si déjà arrivé)
  - Statut du jour (heure arrivée si pointé, "Non pointé" sinon)
    ↓
L'employé appuie et maintient le bouton 1.5 secondes (appui long anti-accident)
    ↓
[Si GPS activé par l'entreprise] → vérification position automatique en arrière-plan
[Si photo activée par l'entreprise] → caméra s'ouvre pour une photo automatique
    ↓
Requête POST /attendance/check-in envoyée à l'API
    ↓
Serveur enregistre l'heure serveur (JAMAIS l'horloge du téléphone)
    ↓
Réponse : Animation de confirmation (checkmark vert + vibration)
          Message : "Arrivée enregistrée à 07:58" (heure serveur)
    ↓
Notification push au gestionnaire si retard détecté
```

**Cas d'erreur :**
```
Pas de réseau      → Message orange "Connexion requise pour pointer"
GPS hors zone      → Message rouge "Vous n'êtes pas dans la zone autorisée (450m/100m)"
Déjà pointé        → Message "Arrivée déjà enregistrée à 07:58. Pointer votre départ ?"
```

---

## FLOW 4 — DEMANDE DE CONGÉ

```
Employé : App → "Mes absences" → "Nouvelle demande"
    ↓
Formulaire :
  - Sélection type de congé (liste des types configurés par l'entreprise)
  - Date de début / Date de fin (calendrier)
  - Nombre de jours calculé automatiquement (hors WE et jours fériés)
  - Commentaire (optionnel)
  - Pièce jointe (si le type de congé l'exige)
    ↓
Validation côté Flutter (solde disponible, délai de prévenance)
    ↓
POST /absences → validation côté serveur (double vérification)
    ↓
Demande créée en statut "pending"
Notification push + email au gestionnaire
    ↓
Gestionnaire : dashboard → "Demandes en attente" → voir la demande
  Affiche : solde actuel de l'employé, planning de l'équipe sur la période
  Actions : "Approuver" | "Refuser" (motif obligatoire)
    ↓
Employé notifié (push + email) avec le résultat
Si approuvé : solde de congés mis à jour, jours marqués "leave" dans le calendrier
Si refusé : motif affiché dans l'app, solde inchangé
```

---

## FLOW 5 — GESTION RETARD / ABSENCE

```
[Détection automatique à H+1 après l'heure de début du planning]
Si aucun check_in enregistré :
  → Statut auto : 'absent'
  → Notification web au gestionnaire : "Ahmed Benali n'a pas pointé"
    ↓
Gestionnaire peut :
  A) Marquer absent justifié / non justifié (saisie d'une absence rétroactive)
  B) Saisir un pointage manuel (l'employé était là mais a oublié)
  C) Ne rien faire (le système conserve le statut 'absent' et l'impact sur la paie)
    ↓
Si retard (pointage existant mais en retard) :
  → Notification web au gestionnaire (pas de push — informatif seulement)
  → Pénalité calculée automatiquement selon les règles configurées
  → Visible dans le récapitulatif mensuel de paie
```

---

## FLOW 6 — GÉNÉRATION DE PAIE

```
Gestionnaire (Principal, RH ou Comptable) : menu "Paie"
    ↓
Sélection du mois → "Calculer la simulation"
    ↓
Système agrège pour chaque employé :
  - Jours travaillés et heures depuis attendance_logs
  - Absences non payées depuis absences
  - Heures supplémentaires calculées
  - Avances à rembourser ce mois depuis salary_advances
  - Pénalités retard si configurées
    ↓
Tableau de simulation affiché :
  - Ligne par employé : brut, cotisations, IR, retenues, net
  - Total masse salariale
  - Comparaison avec le mois précédent
  - Anomalies en rouge (0 jour de présence, données manquantes...)
    ↓
Gestionnaire vérifie et corrige les anomalies si nécessaire
    ↓
"Valider la paie" → confirmation avec validation_token (UUID)
    ↓
Jobs lancés en parallèle (Redis queue) :
  - GeneratePayslipPDF pour chaque employé (DomPDF)
  - Mise à jour des salary_advances (amount_remaining, statut 'repaid' si soldé)
  - Notification push + email "Votre bulletin est disponible" à chaque employé
    ↓
Gestionnaire télécharge le fichier de virement bancaire (CSV/XML selon format banque)
```

---

## FLOW 7 — GESTION DES RÔLES ET PERMISSIONS

```
Gestionnaire Principal : paramètres → "Équipe de gestion"
    ↓
Voir la liste des gestionnaires actifs (si plan le permet)
    ↓
"Ajouter un gestionnaire" → choisir parmi les employés existants
    ↓
Attribuer un rôle : RH | Département | Comptable | Superviseur
Si Département : choisir quel département
Si Superviseur : choisir quels employés sont sous sa supervision
    ↓
Le rôle est actif immédiatement (pas de reconnexion requise pour les autres gestionnaires)
    ↓
Toutes les permissions du nouveau rôle s'appliquent dès la prochaine requête API
```

---

## FLOW 8 — DEMANDE D'AVANCE SUR SALAIRE (module activé par l'entreprise)

```
[Pré-requis : advance.enabled = true dans company_settings]
    ↓
Employé : App → "Mes avances" → "Nouvelle demande"
    ↓
Formulaire :
  - Montant demandé
  - Raison (optionnelle)
  - Nombre de mensualités souhaitées
  L'app affiche en temps réel :
  - Maximum autorisé (% du salaire)
  - Plan de remboursement estimé
    ↓
POST /advances → validation règles (montant, délai, avances en cours)
    ↓
Gestionnaire notifié → approuve ou refuse
    ↓
Si approuvé : plan de remboursement activé, déductions automatiques sur les paies suivantes
Si refusé : employé notifié avec le motif
```