# FEUILLE DE ROUTE RÉALISTE — LEOPARDO RH
# Version 1.1 | Mars 2026
# ⚠️  L'UX vient AVANT le code — toujours.

---

## POURQUOI L'UX AVANT LE BACKEND ?

Un backend développé sans UX validées devra être refait 2 à 3 fois :
- L'API retourne des champs que l'écran n'utilise jamais
- Il manque des champs que l'écran a besoin
- Les flux (nombre d'étapes, ordre des actions) changent → les endpoints changent

**Règle d'or :** On code ce qu'on a vu fonctionner sur un écran validé.

---

## PHASE 0 — INFRASTRUCTURE + UX  (Semaines 1 à 4)

### Semaine 1-2 : Infrastructure (en parallèle de la conception UX)

Ces tâches ne dépendent d'aucune UX — elles peuvent démarrer immédiatement.

```
INFRASTRUCTURE (Claude Code peut démarrer ici)
[ ] Créer le projet Laravel 11 : composer create-project laravel/laravel leopardo-rh-api
[ ] Installer tous les packages (voir SPRINT_0_CHECKLIST.md)
[ ] Configurer PostgreSQL + connexion multi-schéma + exécuter le schéma public
[ ] Configurer Redis (cache + queues)
[ ] Configurer i18n Laravel (4 langues — fichiers lang/ vides mais structurés)
[ ] Configurer Laravel Sanctum
[ ] Mettre en place le TenantMiddleware (SET search_path)
[ ] Exécuter les seeders (plans, langues, modèles RH pays)
[ ] Configurer Nginx + Supervisor sur o2switch
[ ] Créer le projet Flutter 3.x avec GoRouter + Riverpod + l10n
[ ] Configurer firebase_messaging dans Flutter
[ ] Créer les repositories Git (api, mobile, web)
```

### Semaine 2-4 : CONCEPTION UX — OBLIGATOIRE AVANT TOUT CODAGE FONCTIONNEL

**Outil recommandé :** Figma (gratuit jusqu'à 3 projets) ou même Balsamiq pour des wireframes rapides.

**Qui fait les UX ?** Toi + Claude (qui peut générer les wireframes Flutter ici même).

#### Écrans Flutter à concevoir (par ordre de priorité) :

**BLOC A — AUTH (1 écran)**
```
[ ] A1. Écran connexion
       Champs : email, password
       Actions : Se connecter, Mot de passe oublié
       État erreur : message sous les champs
       État loading : bouton désactivé + spinner
```

**BLOC B — POINTAGE (3 écrans) ← LE PLUS IMPORTANT**
```
[ ] B1. Écran d'accueil employé
       Haut : nom + photo + date du jour
       Centre : GRAND bouton "Pointer mon arrivée" (ou "mon départ")
       Bas : résumé du jour (heure arrivée si pointé, heures travaillées)
       Badge alertes tâches urgentes

[ ] B2. Confirmation de pointage
       Popup/page : "Arrivée enregistrée à 08:02"
       Animation de succès
       Bouton retour dashboard

[ ] B3. Historique pointages (vue calendrier mensuelle)
       Calendrier avec couleurs par statut
       Tap sur un jour → détail (check_in, check_out, heures)
```

**BLOC C — MES ABSENCES (3 écrans)**
```
[ ] C1. Liste + solde de congés
       Solde affiché en grand (ex: "12.5 jours disponibles")
       Liste des demandes (passées + en cours) avec statut coloré

[ ] C2. Formulaire nouvelle demande
       Sélecteur type de congé
       Calendrier date début / fin
       Calcul auto du nombre de jours
       Champ commentaire + upload pièce jointe

[ ] C3. Détail d'une demande
       Toutes les infos + statut + motif si refus
```

**BLOC D — MES TÂCHES (2 écrans)**
```
[ ] D1. Liste de mes tâches (filtrée : à faire / en cours / terminées)
       Chaque carte : titre, priorité (badge couleur), échéance, statut

[ ] D2. Détail d'une tâche
       Description complète, checklist interactive
       Bouton changer de statut
       Fil de commentaires
```

**BLOC E — TABLEAU DE BORD GESTIONNAIRE (4 écrans)**
```
[ ] E1. Dashboard présence temps réel
       Compteurs : Présents / Absents / Retards / Non pointés
       Liste des employés avec leur statut du jour (coloré)
       Filtre par département

[ ] E2. Gestion des demandes en attente
       Tabs : Congés / Avances
       Chaque item : qui, quoi, dates, solde disponible
       Actions : Approuver / Refuser (avec motif)

[ ] E3. Fiche employé (vue gestionnaire)
       Infos générales, contrat, salaire
       Solde congés, avances en cours
       Historique pointages du mois

[ ] E4. Module paie (vue gestionnaire)
       Sélection du mois
       Tableau récapitulatif des employés
       Anomalies en rouge
       Bouton Simuler → Bouton Valider
```

**BLOC F — SUPER ADMIN (3 écrans web uniquement)**
```
[ ] F1. Dashboard global (compteurs + graphiques)
[ ] F2. Liste des entreprises + création
[ ] F3. Gestion d'une entreprise (plan, suspension, notes)
```

#### Ce qu'on valide sur chaque écran AVANT de coder :
```
1. Le flux est-il logique ? (nombre d'étapes, ordre)
2. Les données affichées sont-elles toutes disponibles dans l'API ?
3. Les actions possibles correspondent-elles aux endpoints définis ?
4. L'écran fonctionne-t-il en arabe (RTL) ? En turc ?
5. L'écran est-il utilisable à une main sur un téléphone 5.5" ?
```

---

## PHASE 1 — MVP FONCTIONNEL (Semaines 5 à 16)

Une fois les UX des blocs B, C, D et E1 validées → démarrage du codage.

### Semaine 5-6 : Auth complète

```
BACKEND (Claude Code)                    FLUTTER (Jules)
[ ] POST /auth/login                     [ ] Écran connexion (B→A1)
[ ] POST /auth/logout                    [ ] Stockage token flutter_secure_storage
[ ] POST /auth/forgot-password           [ ] Intercepteur Dio (token Bearer auto)
[ ] POST /auth/reset-password            [ ] Gestion erreurs réseau globale
[ ] Tests Feature Auth (Pest)            [ ] Écran mot de passe oublié
```

**Critère de passage à la suite :** Un employé peut se connecter depuis Flutter et voir son nom sur l'écran d'accueil.

---

### Semaine 7-8 : Pointage déclaratif

```
BACKEND                                  FLUTTER
[ ] POST /attendance/check-in            [ ] Écran pointage (B1) — grand bouton
[ ] POST /attendance/check-out           [ ] Appel API check-in / check-out
     (UPDATE — pas INSERT)               [ ] Affichage confirmation (B2)
[ ] GET /attendance (historique)         [ ] Calendrier historique (B3)
[ ] AttendanceService (calcul statut)    [ ] Format ISO 8601 → HH:mm via DateFormat
[ ] Tests Feature Pointage               [ ] Gestion erreur offline (SnackBar)
```

**Critère de passage :** Un employé pointe arrivée + départ depuis son téléphone. L'heure affichée est celle du serveur.

---

### Semaine 8-9 : Employés + Départements

```
BACKEND                                  FLUTTER
[ ] GET/POST/PUT /employees              [ ] Écran fiche employé (E3 — vue lecture)
[ ] GET/POST /departments                [ ] Liste employés (gestionnaire)
[ ] GET/POST /positions                  [ ] Pas de création employé sur mobile
[ ] GET/POST /schedules                       (web uniquement)
[ ] EmployeeObserver (audit auto)
[ ] Tests Feature Employés
```

---

### Semaine 9-10 : Module Absences

```
BACKEND                                  FLUTTER
[ ] GET/POST /absences                   [ ] Écran liste absences + solde (C1)
[ ] PUT /absences/{id}/approve           [ ] Formulaire demande (C2)
[ ] PUT /absences/{id}/reject            [ ] Détail demande (C3)
[ ] AbsenceService (jours ouvrables)     [ ] Validation côté Flutter avant envoi
[ ] GET/POST /absence-types              [ ] Écran approbation gestionnaire (E2)
[ ] LeaveAccrueMonthly (cron)
[ ] Tests Feature Absences
```

---

### Semaine 11-12 : Module Tâches

```
BACKEND                                  FLUTTER
[ ] GET/POST/PUT /tasks                  [ ] Liste tâches (D1)
[ ] PUT /tasks/{id}/status               [ ] Détail tâche + checklist (D2)
[ ] POST /tasks/{id}/comments            [ ] Changement statut
[ ] Tests Feature Tasks                  [ ] Fil de commentaires
```

---

### Semaine 12-14 : Module Paie (basique)

```
BACKEND                                  FLUTTER
[ ] POST /payroll/calculate              [ ] Écran paie gestionnaire (E4)
[ ] POST /payroll/validate               [ ] Affichage simulation
     (avec validation_token UUID)        [ ] Confirmation validation
[ ] GET /payroll/{id}/pdf                [ ] Écran mes bulletins (liste + viewer PDF)
[ ] Job GeneratePayslipPDF (DomPDF)
[ ] Template Blade bulletin
[ ] GET /payroll/export-bank
[ ] BankExportService (DZ_GENERIC d'abord)
[ ] Tests Feature Paie
```

---

### Semaine 14-15 : Notifications

```
BACKEND                                  FLUTTER
[ ] NotificationService (FCM + Email)    [ ] Réception push (foreground + background)
[ ] Job SendBulkEmail                    [ ] Navigation deep link depuis notif
[ ] POST /devices (upsert FCM token)     [ ] Upsert FCM token à chaque login
[ ] Toutes les commandes Artisan (cron)
```

---

### Semaine 15-16 : Interface Web Gestionnaire (Vue.js)

```
[ ] Layout AppLayout.vue (sidebar + header)
[ ] Dashboard gestionnaire (E1)
[ ] Module paie web (E4 — version web complète)
[ ] CRUD employés web (formulaire complet)
[ ] Module absences web
[ ] Module tâches web
[ ] Interface Super Admin (F1, F2, F3)
```

---

### FIN PHASE 1 — CRITÈRES DE VALIDATION MVP

```
[ ] Un gestionnaire peut créer son entreprise et ajouter ses employés en < 30 min
[ ] Un employé peut pointer depuis l'app Flutter en < 10 secondes
[ ] La paie mensuelle est calculée et les bulletins PDF générés
[ ] Les notifications push arrivent sur le téléphone
[ ] L'interface fonctionne en Français ET en Arabe (RTL validé)
[ ] Déploiement stable sur o2switch VPS
```

---

## PHASE 2 — VERSION COMPLÈTE (Semaines 17 à 28)

Démarrer uniquement après validation complète du MVP par de vrais utilisateurs.

```
Semaine 17-18 : Avances sur salaire (module désactivé par défaut)
Semaine 18-19 : Intégration ZKTeco (biométrique Push + Pull)
Semaine 19-20 : Option photo au pointage + GPS (désactivés par défaut)
Semaine 20-21 : Module Évaluations + indicateurs performance tâches
Semaine 21-22 : Calcul paie avancé (cotisations, IR barème, HS)
Semaine 22-23 : Formats export bancaire supplémentaires (MA_CIH, FR_SEPA, TN)
Semaine 24-25 : Module facturation automatique (passerelle de paiement)
Semaine 25-26 : Rapports complets + exports Excel
Semaine 27-28 : Tests de charge, optimisation, audit sécurité
```

---

## PHASE 3 — CONSOLIDATION (Mois 7 à 9)

```
Mois 7 : Gestion multi-sites par entreprise
Mois 7 : Organigramme interactif
Mois 8 : API publique documentée (Swagger)
Mois 8 : 2FA (TOTP Google Authenticator)
Mois 9 : Ajout facile de nouvelles langues (procédure 4 étapes)
```

---

## PHASE 4 — CROISSANCE (Mois 10+)

```
Intégrations comptabilité (Sage, QuickBooks)
Application tablette pour borne de pointage fixe
Prédiction d'absences (IA)
Module recrutement
Module formation
```

---

## TABLEAU RÉCAPITULATIF DES DÉPENDANCES

```
Infrastructure o2switch        → Peut démarrer MAINTENANT (pas besoin d'UX)
Projet Laravel (init)          → Peut démarrer MAINTENANT
Projet Flutter (init + nav)    → Peut démarrer MAINTENANT
Schéma DB (public + migrations)→ Peut démarrer MAINTENANT

UX Bloc A (Auth)               → À concevoir Semaine 1-2
UX Bloc B (Pointage)           → À concevoir Semaine 1-2  ← PRIORITÉ 1
UX Bloc C (Absences)           → À concevoir Semaine 2-3
UX Bloc D (Tâches)             → À concevoir Semaine 2-3
UX Bloc E (Gestionnaire)       → À concevoir Semaine 3-4

Code Auth                      → Après UX Bloc A validée (Semaine 5)
Code Pointage                  → Après UX Bloc B validée (Semaine 7)
Code Absences                  → Après UX Bloc C validée (Semaine 9)
Code Tâches                    → Après UX Bloc D validée (Semaine 11)
Code Paie                      → Après UX Bloc E validée (Semaine 12)
```

---

## AVEC QUOI COMMENCER DEMAIN MATIN

1. **Toi** → Ouvrir Figma, créer les wireframes de l'écran B1 (pointage — grand bouton)
   C'est 1 seul écran, 30 minutes de travail. Quand il est validé, Jules peut coder.

2. **Claude Code** → Initialisation Laravel + schéma SQL public + seeders
   Ces tâches ne bloquent sur aucune UX.

3. **Jules** → Initialisation Flutter + navigation GoRouter + écran login statique (sans API)
   Il peut avancer sur la structure même sans backend prêt.

4. **Cette semaine** → Objectif : infrastructure prête + wireframes Blocs A et B validés
   C'est la condition pour démarrer le vrai codage fonctionnel à la Semaine 5.
```

---

*Document Leopardo RH — Feuille de Route v1.1 — Mars 2026*
*Prochaine révision : après livraison du MVP (fin Semaine 16)*
