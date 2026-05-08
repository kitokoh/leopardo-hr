# SCENARIOS DE TEST API POUR GITHUB ACTIONS

## Objectif

Definir une couverture backend exhaustive pour la CI GitHub Actions, alignee sur les roles reels de l'application, les domaines metier critiques et les risques multitenant.

## Perimetre

- API publique
- API authentifiee tenant
- RBAC et isolation multitenant
- Parcours critiques RH
- Endpoints techniques et resilients
- Contrats JSON consommes par le mobile
- Contrats d'auth et de session de la plateforme admin

## Roles a couvrir

1. Super Admin
2. Owner / Company Admin
3. HR Manager
4. Manager
5. Employee
6. Finance / Payroll
7. Utilisateur inactif / bloque
8. Utilisateur hors tenant / tenant etranger

## Strategie CI recommandee

1. Tests `Unit`
2. Tests `Feature`
3. Tests critiques par domaine metier
4. Tests de securite / isolation
5. Rapport CI lisible avec mapping vers les scenarios

## Matrice complete des scenarios backend

### 1. Sante technique et bootstrap

- `GET /api/health` retourne 200 avec structure attendue
- Application demarre avec migrations `public` puis `tenant`
- Redis / cache / queue sync ne cassent pas les endpoints critiques
- Une erreur de bootstrap ne fuit pas d'informations sensibles

### 2. Auth publique et onboarding

- Register public succes avec creation tenant
- Register refuse si email deja utilise globalement
- Register refuse si payload invalide
- Login succes pour chaque role autorise
- Login refuse pour mot de passe invalide
- Login refuse pour compte inactif ou bloque
- `me` retourne le bon role, tenant, permissions et contexte
- Logout invalide le token en cours

### 3. RBAC par role

- Super Admin peut acceder aux ressources globales seulement
- Owner/Admin peut administrer son tenant sans acceder au global
- HR peut gerer employes et conges selon permissions
- Manager peut consulter/valider seulement son equipe
- Employee ne peut acceder qu'a ses propres donnees
- Finance peut consulter paie si activee
- Toute elevation de privilege est refusee en `403`

### 4. Isolation multitenant

- Un token du tenant A ne voit jamais les ressources du tenant B
- Les recherches par identifiant refusent les objets externes au tenant
- Les ecritures inter-tenant sont refusees
- Les user lookups / shared tables restent coherents
- Les migrations tenant ne polluent pas `public`

### 5. Employes et organisation

- Liste employees avec pagination, tri, filtre
- Creation employee avec validations metier
- Mise a jour employee avec verifications unicite/global email
- Desactivation / reactivation employee
- Consultation detail employee selon role
- Refus d'acces pour employee sur dossier d'un autre employee

### 6. Presence / attendance

- Check-in succes
- Check-out succes
- Double check-in interdit
- Check-out sans check-in interdit
- Historique presence retourne des donnees coherentes
- Resume du jour correct selon fuseau et etat
- Conflits ou doublons geres sans corruption des donnees
- `GET /attendance/anomalies` retourne un resume d'impact business (`late_minutes`, sorties manquantes, corrections, actions critiques)
- Chaque anomalie attendance expose une action manager recommandee et un flag `requires_manager_action`
- Les anomalies geofence, heures supplementaires et sequences rapides restent scopees au tenant courant

### 7. Conges / absences

- Creation demande de conge par employee
- Validation / refus par manager ou HR
- Solde mis a jour correctement
- Chevauchement de periodes refuse
- Consultation historique des demandes par role
- Employee ne peut pas valider sa propre demande sans permission speciale

### 8. Paie / finance

- Acces bulletins par employee
- Acces synthese payroll par finance / HR
- Refus d'acces payroll pour roles non autorises
- Calculs exposes sans fuite inter-tenant
- Etats de paie invalides rejetes proprement

### 9. Estimation / PDF / documents

- Quick estimate retourne structure et montants attendus
- Daily summary respecte les donnees filtrees
- PDF recu genere un fichier telechargeable valide
- Erreurs de generation PDF gerees sans crash global
- Rapport mensuel attendance JSON expose jours travailles, heures, retards et estimations paie terrain
- Export CSV du rapport mensuel conserve les colonnes d'estimation paie et reste exploitable par comptable
- PDF du rapport mensuel affiche les indicateurs de cloture et l'estimation globale sans casser le rendu

### 10. Notifications / evenements / audit

- Evenement metier declenche la notification attendue
- Endpoint de lecture marque lu / non lu correctement
- Journalisation des actions sensibles disponible si prevue

### 11. Resilience et erreurs

- `401` si token manquant / invalide
- `403` si role insuffisant
- `404` sur ressource absente avec payload standard
- `422` sur validation metier
- `429` si rate limit active
- `500` ne fuit ni stack ni secrets en production

### 12. Contrats API pour mobile

- Les endpoints auth renvoient les champs attendus par Flutter
- Les endpoints attendance renvoient un shape stable
- Les listes paginees gardent une structure constante
- Les enums / statuts attendus par le mobile restent stables

### 13. Contrats API pour la plateforme admin

- `POST /api/v1/platform/auth/login` accepte `email`, `password`, `device_name` et optionnellement `two_fa_code`
- Un super-admin sans 2FA obtient `200` avec `data`, `token`, `token_type`, `role=super_admin` et `two_fa_enabled`
- Un super-admin avec 2FA active et sans code valide obtient `202` avec `code=TWO_FA_REQUIRED` au lieu d'un faux succes silencieux
- `GET /api/v1/platform/auth/me` retourne un shape stable pour hydrater la session admin sans hypothese cote frontend
- `POST /api/v1/platform/auth/logout` invalide le token courant sans exiger de mecanisme de refresh fantome
- Aucun contrat admin ne doit reintroduire des routes `/admin/auth/*` inexistantes
- `GET /api/v1/platform/companies/{company}/health` retourne plan/MRR, features, adoption pointage 30 jours, onboarding, anomalies et next actions
- `GET /api/v1/platform/companies/health` retourne le portefeuille client avec MRR total, repartition des risques et prochaine action par company
- `GET /api/v1/platform/plans` retourne le catalogue des plans pour alimenter les formulaires d'abonnement super-admin
- `GET/PATCH /api/v1/platform/companies/{company}/subscription` lit et met a jour plan, statut, dates d'abonnement et notes client
- Le health client classe clairement le risque (`low`, `medium`, `high`) et reste reserve au guard `super_admin_api`
- Les metriques health ne doivent jamais lire les donnees d'un autre tenant ni dependre d'un `current_company` applicatif
- Le contrat abonnement refuse les statuts inconnus, les plans inexistants et les dates incoherentes

### 14. Catalogue de traductions distant et variantes de locale

- `GET /api/v1/i18n/catalog` retourne les variantes supportees, checksums et metadata de version
- `GET /api/v1/i18n/catalog/{locale}` normalise `fr-CA`, `fr-BE`, `ar-SA`, `ar-MA`, `tr-TR`, `en-US`, `en-GB` vers leur langue canonique
- L'endpoint retourne `ETag`, `checksum`, `fallback_locale` et `rtl` de facon stable
- Une requete `If-None-Match` valide doit repondre `304` sans payload parasite
- Les catalogues invalides ou absents ne doivent jamais provoquer une erreur `500` silencieuse

### 15. Onboarding go-live client

- `GET /api/v1/onboarding/checklist` reste reserve aux managers autorises
- La checklist couvre creation societe, manager actif, equipe ajoutee/active, bases de paie, geofence, biometrie et kiosque
- Le payload expose `go_live_ready` et `next_actions` pour guider l'installation client sans interpretation cote frontend
- Les metriques de progression ne doivent pas compter une etape paie complete si aucun salaire ou taux horaire n'est renseigne

## Mapping attendu vers les suites GitHub Actions

### Suite `Unit`

- Services d'authentification
- Services de presence
- Services d'estimation / calcul
- Toute logique metier pure et deterministe

### Suite `Feature`

- Auth login / me / logout
- Auth guardrails: employee archive, company suspended
- RBAC employees
- Isolation tenant
- Attendance check-in / check-out / history
- Attendance anomalies business impact / recommended actions
- Attendance monthly report JSON / CSV / PDF payroll estimates
- Onboarding checklist go-live readiness
- Estimation daily summary / quick estimate / PDF
- Contrats JSON critiques pour le mobile
- Contrats d'auth plateforme et cas `TWO_FA_REQUIRED`
- Contrat health plateforme pour adoption, retention et upsell client
- Contrat catalogue plans plateforme pour eviter les `plan_id` hardcodes cote frontend
- Contrat abonnement plateforme pour upgrade, suspension, expiration et notes client
- Health endpoint

### Suites a ajouter ou durcir progressivement

- `tests/Feature/PublicRegisterTest.php`
- `tests/Feature/Leave/LeaveApprovalTest.php`
- `tests/Feature/Payroll/PayrollAccessTest.php`
- `tests/Feature/Security/BlockedUserTest.php`
- `tests/Feature/Platform/PlatformAuthTest.php`

## Sortie attendue dans GitHub Actions

- Rapport JUnit Unit
- Rapport JUnit Feature
- Logs applicatifs en artefact
- Rapport CI central mentionnant:
  - couverture backend executee
  - scenarios backend de reference
  - gaps connus restant a fermer

## Critere GO / NO GO

- GO: tous les tests Unit + Feature passent, aucun test critique securite/isolation en echec
- NO GO: echec auth, RBAC, multitenant, attendance critique, payload contrat mobile, contrat admin plateforme ou payroll securite

## Gaps actuels a fermer en priorite

- Register public complet en CI
- Conges / approbations en CI
- Payroll access control en CI
- Utilisateur bloque distinct de l'etat archive en CI
- Suite dediee a l'auth plateforme avec 2FA
