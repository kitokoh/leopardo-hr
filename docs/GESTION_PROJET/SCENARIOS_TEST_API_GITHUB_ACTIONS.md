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

Note 2026-05-12 : les tests Feature des modules post-sprints doivent verifier les routes reelles (`/billing/subscription/*`, `/training/courses/{id}/sessions`, actions `PUT` pour prets/frais) et rester alignes avec le schema `CreatesMvpSchema`.

Note 2026-05-14 : les endpoints sensibles utilisent des limiters nommes configurables (`auth-sensitive`, `privacy-sensitive`, `payroll-sensitive`, `platform-sensitive`, `ai-sensitive`). Les scenarios API doivent conserver au moins un test `429` sur auth publique et un test `429` sur privacy authentifie.

Note 2026-05-15 : l'API expose maintenant des headers de version (`X-API-Version`, `X-API-Supported-Versions`) et refuse un `X-API-Version` non supporte. Les routes authentifiees tenant portent aussi un limiter `api-plan` configurable par plan commercial ; garder un test `429` dedie sur un plan Starter avec seuil abaisse en test.

## Matrice complete des scenarios backend

### 1. Sante technique et bootstrap

- `GET /api/health` retourne 200 avec structure attendue
- `GET /api/v1/health/live` retourne 200 (liveness probe, pas de verification DB)
- `GET /api/v1/health/ready` retourne 200 si DB accessible, 503 sinon (readiness probe)
- Application demarre avec migrations `public` puis `tenant`
- Redis / cache / queue sync ne cassent pas les endpoints critiques
- Une erreur de bootstrap ne fuit pas d'informations sensibles
- Le middleware `RequestIdMiddleware` ajoute un header `X-Request-Id` a chaque reponse API
- Le middleware `ApiVersionMiddleware` ajoute `X-API-Version: v1` et `X-API-Supported-Versions`
- Une requete avec `X-API-Version: v2` sur `/api/v1/*` retourne `400 UNSUPPORTED_API_VERSION`
- Un `X-Request-Id` fourni dans la requete est reechoe dans la reponse
- `GET /docs` publie Swagger UI sans authentification
- `GET /docs/openapi.yaml` sert la specification canonique `api/openapi.yaml` sans copie divergente

### 2. Auth publique et onboarding

- Register public succes avec creation tenant
- Register refuse si email deja utilise globalement
- Register refuse si payload invalide
- Login succes pour chaque role autorise
- Login refuse pour mot de passe invalide
- Login public retourne `429` apres depassement du limiter `auth-sensitive`
- Une route authentifiee retourne `429` apres depassement du limiter `api-plan` du plan client
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
- Organigramme retourne uniquement les employes du tenant courant et construit l'arbre sans scans repetes par noeud
- Chaine manager et subordonnes refusent les IDs hors tenant
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
- Admin middleware : seul manager `principal` est considere admin cote tenant ; les sous-roles `dept` et `superviseur` doivent recevoir `403`

### 9. Estimation / PDF / documents

- Quick estimate retourne structure et montants attendus
- Daily summary respecte les donnees filtrees
- PDF recu genere un fichier telechargeable valide
- Erreurs de generation PDF gerees sans crash global
- Rapport mensuel attendance JSON expose jours travailles, heures, retards et estimations paie terrain
- Rapport mensuel attendance reste performant sur 500+ employes en groupant les logs par employe avant generation des lignes
- Export CSV du rapport mensuel conserve les colonnes d'estimation paie et reste exploitable par comptable
- PDF du rapport mensuel affiche les indicateurs de cloture et l'estimation globale sans casser le rendu

### 10. Notifications / evenements / audit

- Evenement metier declenche la notification attendue
- Endpoint de lecture marque lu / non lu correctement
- Journalisation des actions sensibles disponible si prevue
- `AuditLogger` listener ecoute les 8 domain events et ecrit dans `audit_logs`
- `WebhookListener` dispatche les events vers les endpoints webhook du tenant
- Les events sont dispatches depuis les services (EmployeeCreated, EmployeeArchived, AttendanceCheckedIn/Out, AbsenceRequested/Approved/Rejected, PayrollValidated)
- `EventServiceProvider` cable chaque event aux listeners AuditLogger et WebhookListener

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
- `GET /api/v1/platform/metrics/overview` retourne les agregats plateforme MRR/ARR, encaissements 30 jours, impayes, companies, abonnements, facturation et systeme
- Le health client classe clairement le risque (`low`, `medium`, `high`) et reste reserve au guard `super_admin_api`
- Les metriques health ne doivent jamais lire les donnees d'un autre tenant ni dependre d'un `current_company` applicatif
- Le contrat abonnement refuse les statuts inconnus, les plans inexistants et les dates incoherentes
- Le contrat metrics overview reste reserve au guard `super_admin_api`, ne retourne aucune donnee nominative tenant et tolere les tables billing absentes pendant les migrations progressives

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

### 16. Privacy / RGPD self-service

- `GET /api/v1/privacy/export` retourne uniquement le bundle de donnees de l'employe authentifie et des compteurs d'activite scopes par `company_id`
- `POST /api/v1/privacy/deletion-request` cree une demande tracee non destructive pour revue RH/juridique et ne supprime jamais le compte immediatement
- `PATCH /api/v1/privacy/biometric-consent` enregistre le consentement ou retire le consentement en desactivant les flags biometriques et en effacant les references de templates
- Les endpoints privacy restent sous `auth:sanctum` + `tenant` et ne prennent jamais d'`employee_id` client pour eviter l'export d'un collegue ou d'un autre tenant
- Les acces aux fiches employees et exports privacy creent une entree `audit_logs` avec `category=hr_data_access`, acteur, tenant et cible quand elle existe
- Les endpoints privacy retournent `429` apres depassement du limiter `privacy-sensitive`

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
- Isolation tenant par chaine FK : `WebhookDelivery`, `PaySlipLine`, `ApprovalDecision`, `ExpenseItem` doivent etre filtres via leur parent portant `company_id`
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
- Contrat metrics overview plateforme pour MRR/ARR, impayes, encaissements, companies, abonnements et facturation
- Contrats privacy/RGPD pour export donnees personnelles, demande de suppression et consentement biometrique employe
- Journalisation `audit_logs` des acces RH sensibles : liste employees, fiche employee, export privacy
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

## Modules API etendus (v4.2.0)

### Module A — Conges avances
- `GET /api/v1/leave-policies` retourne la liste des politiques actives
- `POST /api/v1/leave-policies` cree une politique (manager RH uniquement)
- `GET /api/v1/leave-policies/{id}` retourne le detail d'une politique
- `PUT /api/v1/leave-policies/{id}` modifie une politique (manager RH)
- `DELETE /api/v1/leave-policies/{id}` desactive une politique (manager RH)
- `GET /api/v1/leave-balances?year=2026` retourne les soldes par employe et annee
- `GET /api/v1/me/leave-balances` retourne les soldes de l'employe connecte
- `GET /api/v1/leave-accruals` retourne l'historique des cumuls
- `POST /api/v1/leave-accruals` cree un cumul manuel (manager RH)
- RBAC : employe non-manager ne voit que ses propres soldes
- Isolation tenant : policies, balances et accruals doivent etre scopes au `company_id` de l'acteur ; `POST /leave-accruals` refuse employee/policy d'un autre tenant.
- Couverture Feature : CRUD policy existant, index tenant-scope, balances manager/self-service, accrual success + refus cross-tenant employee/policy, accrual index tenant-scope.
- Scheduler : `leave:accrue` accumule les soldes le 1er de chaque mois

### Module B — Contrats
- `POST /api/v1/contracts` cree un contrat en statut draft (manager RH)
- `PUT /api/v1/contracts/{id}` modifie un contrat
- `POST /api/v1/contracts/{id}/activate` active un contrat draft (signed_at auto)
- `POST /api/v1/contracts/{id}/suspend` suspend un contrat actif
- `POST /api/v1/contracts/{id}/terminate` resilie avec motif obligatoire
- `POST /api/v1/contracts/{id}/renew` renouvelle (cree nouveau + expire ancien)
- `GET /api/v1/contracts/expiring?days=30` liste les contrats expirant dans 30 jours
- `GET /api/v1/contracts/{id}/amendments` liste les avenants
- `POST /api/v1/contracts/{id}/amendments` cree un avenant
- `GET /api/v1/contracts/{id}/generate-pdf` genere les donnees PDF
- `GET /api/v1/me/contracts` retourne les contrats de l'employe connecte
- RBAC : employe voit uniquement ses propres contrats
- Isolation : `GET /api/v1/contracts` et `GET /api/v1/contracts/expiring` ne retournent que le tenant courant
- Isolation : `POST /api/v1/contracts` refuse un `employee_id` hors tenant
- Self-service : un employe ne peut pas consulter, generer le PDF ou lire les avenants du contrat d'un collegue
- Scheduler : `contracts:alert-expiring` alerte a 30/15/7 jours

### Module K — Workflows d'approbation
- `GET /api/v1/approval-workflows` liste les workflows (admin RH)
- `POST /api/v1/approval-workflows` cree un workflow
- `PUT /api/v1/approval-workflows/{id}` modifie un workflow
- `DELETE /api/v1/approval-workflows/{id}` desactive un workflow
- `GET /api/v1/approvals/pending` liste les approbations en attente
- `POST /api/v1/approvals/{id}/approve` approuve avec commentaire
- `POST /api/v1/approvals/{id}/reject` rejette avec commentaire obligatoire
- `GET /api/v1/approvals/history` historique des decisions

### Module C — Recrutement/ATS
- `POST /api/v1/recruitment/jobs` cree une offre d'emploi (manager RH)
- `PUT /api/v1/recruitment/jobs/{id}` publie une offre (status draft -> published, published_at auto)
- `POST /api/v1/recruitment/jobs/{id}/applicants` ajoute un candidat
- `POST /api/v1/recruitment/applicants/{id}/interviews` planifie un entretien
- RBAC : employes non-managers recoivent 403 sur toutes les routes recrutement
- Isolation : listes, details, mises a jour et creation de candidats restent scopees au `company_id` courant.

### Module D — Formation/LMS
- `POST /api/v1/training/courses` cree un cours (manager RH)
- `POST /api/v1/training/courses/{id}/sessions` planifie une session
- `POST /api/v1/training/sessions/{id}/enroll` inscrit un employe
- `PUT /api/v1/training/enrollments/{id}` complete une inscription (score, feedback)
- Isolation : catalogue, details, sessions, trainers et enrollments refusent les ressources ou employees hors tenant.

### Module E — Prets employes
- `POST /api/v1/loans` cree un pret avec echeancier auto-genere
- `PUT /api/v1/loans/{id}/approve` approuve un pret (manager RH)
- `PUT /api/v1/loans/{id}/disburse` debloque les fonds (apres approbation)
- Validation : un pret non approuve ne peut pas etre debloque (422)
- Isolation : manager ne voit que les prets de son tenant et ne peut pas creer/approuver un pret pour un employe externe.

### Module F — Notes de frais
- `POST /api/v1/expense-claims` cree une note avec items
- `PUT /api/v1/expense-claims/{id}/submit` soumet pour approbation
- `PUT /api/v1/expense-claims/{id}/approve` approuve (manager RH)
- Validation : seul le draft peut etre soumis, seul le submitted peut etre approuve
- Isolation : employe ne voit que ses notes, manager seulement celles de son tenant, et les approvals cross-tenant retournent 404.

### Module G — Organigramme
- `GET /api/v1/org-chart` retourne l'arbre hierarchique complet
- `GET /api/v1/org-chart/{id}/subordinates` retourne les subordonnes directs
- `GET /api/v1/org-chart/{id}/manager-chain` retourne la chaine manageriale ascendante

### Module H — Rapports RH
- `GET /api/v1/reports/headcount` retourne effectifs par departement, type contrat, genre (payload mis en cache par tenant avec TTL `HR_REPORT_HEADCOUNT_CACHE_TTL`, desactive si `0`)
- `GET /api/v1/reports/turnover?months=12` retourne embauches/departs par mois
- `GET /api/v1/reports/absenteeism?month=5&year=2026` retourne jours absence par type
- `GET /api/v1/reports/payroll-summary` retourne masse salariale brute/nette
- RBAC : uniquement managers

### Module I — Webhooks
- `POST /api/v1/webhooks` cree un endpoint avec secret genere (principal)
- `GET /api/v1/webhooks/events` retourne la liste des evenements disponibles
- `GET /api/v1/webhooks/{id}` inclut les 20 dernieres livraisons
- `DELETE /api/v1/webhooks/{id}` supprime un endpoint
- RBAC : uniquement principal

### Module J — Audit Trail
- `GET /api/v1/audit-logs` retourne les logs filtres par action, type, user, date
- `GET /api/v1/audit-logs/{id}` retourne le detail avec old/new values
- `GET /api/v1/audit-logs/export-csv` exporte les logs en CSV avec filtres `from`/`to` (stream chunked)
- RBAC : uniquement principal

## Paie Complete Multi-Pays (v4.3.0)

### Salary Structures
- `GET /api/v1/salary-structures` retourne les structures salariales de la company
- `POST /api/v1/salary-structures` cree une structure (manager RH)
- `GET /api/v1/salary-structures/{id}` retourne la structure avec ses composants
- `PUT /api/v1/salary-structures/{id}` met a jour une structure
- `DELETE /api/v1/salary-structures/{id}` supprime une structure
- RBAC : managers uniquement

### Salary Components
- `POST /api/v1/salary-components` cree un composant (earning, deduction, employer_contribution)
- `GET /api/v1/salary-components?type=earning` filtre par type
- Validation : code unique par company + structure
- RBAC : managers uniquement

### Tax Slabs
- `GET /api/v1/tax-slabs?country_code=DZ` retourne les tranches fiscales par pays
- `POST /api/v1/tax-slabs` cree une tranche avec effective_from/to
- RBAC : managers uniquement

### Social Contributions
- `GET /api/v1/social-contributions?country_code=DZ&type=employee` filtre par pays et type
- `POST /api/v1/social-contributions` cree une cotisation avec code unique
- RBAC : managers uniquement

### Payroll Runs
- `POST /api/v1/payroll-runs` cree un run en draft (period_start, period_end, country_code)
- `POST /api/v1/payroll-runs/{id}/calculate` lance le calcul (genere les pay_slips)
- `POST /api/v1/payroll-runs/{id}/validate` valide le run (status calculated -> validated) et enqueue un warmup PDF (`WarmPaySlipPdfPathsForPayrollRunJob`) lorsque `PAYROLL_QUEUE_PDF_WARMUP` est actif
- `POST /api/v1/payroll-runs/{id}/cancel` annule un run (interdit si paid)
- `GET /api/v1/payroll-runs/{id}/summary` retourne le resume avec totaux et liste employes
- Validation : seul un run calculated peut etre valide, seul un run draft/calculated peut etre recalcule
- RBAC : managers uniquement
- Couverture Feature : liste scopee tenant, creation manager, calcul via contrat `PayrollCalculator`, validation run + bulletins + dispatch warmup PDF + fichier local `pdf_path`, annulation draft, refus paid et refus d'acces cross-tenant.

### Pay Slips
- `GET /api/v1/payroll-runs/{id}/pay-slips` liste les bulletins d'un run (manager)
- `GET /api/v1/pay-slips/{id}` detail bulletin avec lignes (manager ou employe concerne)
- `GET /api/v1/pay-slips/{id}/pdf` telecharge le PDF du bulletin (manager ou employe proprietaire) ; si `pdf_path` pointe vers un fichier present sur le disque `local`, le fichier est servi sinon generation synchrone DomPDF
- `POST /api/v1/payroll-runs/{id}/send-slips` exige un run valide/paye et marque les bulletins emailable comme envoyes
- RBAC : manager voit tout, employe voit uniquement ses bulletins
- Couverture Feature : liste par run scopee tenant, self-service validated/sent uniquement, detail proprietaire, PDF protege, send-slips bloque avant validation et refus employe sur liste manager.

### Self-service
- `GET /api/v1/me/pay-slips` retourne les bulletins valides/envoyes de l'employe connecte
- `GET /api/v1/me/pay-slips/{id}` detail bulletin avec lignes (uniquement si validated/sent)
- RBAC : employe connecte, ses propres bulletins uniquement

### Bank Exports
- `POST /api/v1/payroll-runs/{id}/bank-export` genere un fichier export (format: sepa_xml, ccp_dz, virement_ma, csv_generic)
- `GET /api/v1/bank-exports/{id}` detail de l'export
- `GET /api/v1/bank-exports/{id}/download` telecharge le fichier genere
- Validation : payroll run doit etre validated ou paid
- RBAC : managers uniquement

---

## Module Tracking Vehicules (Sprint 9-10)

### Vehicles CRUD
- `GET /api/v1/vehicles` liste paginee avec filtres status, type
- `POST /api/v1/vehicles` creation vehicule
- `GET /api/v1/vehicles/{id}` detail vehicule
- `PUT /api/v1/vehicles/{id}` mise a jour
- `DELETE /api/v1/vehicles/{id}` suppression
- RBAC : authentification requise, isolation par company_id

### Vehicle Sub-Resources
- `GET /api/v1/vehicles/{id}/position` position GPS actuelle (via Traccar)
- `GET /api/v1/vehicles/{id}/trips` historique trajets paginé
- `GET /api/v1/vehicles/{id}/alerts` alertes du vehicule paginées
- `GET /api/v1/vehicles/{id}/maintenance` historique maintenance paginé

### Affectations Chauffeurs
- `POST /api/v1/vehicles/{id}/assign` affecter un chauffeur
- `POST /api/v1/vehicles/{id}/unassign` retirer affectation
- `GET /api/v1/vehicles/{id}/assignments` historique affectations

### Trips
- `GET /api/v1/vehicle-trips` liste trajets filtrable (vehicle, driver, dates)
- `GET /api/v1/vehicle-trips/{id}` detail trajet

### Alerts
- `GET /api/v1/vehicle-alerts` liste alertes filtrable (type, acknowledged, vehicle)
- `POST /api/v1/vehicle-alerts/{id}/acknowledge` acquitter alerte

### Maintenance
- `GET /api/v1/vehicle-maintenance` liste maintenance filtrable
- `POST /api/v1/vehicle-maintenance` enregistrer maintenance
- `PUT /api/v1/vehicle-maintenance/{id}` modifier
- `DELETE /api/v1/vehicle-maintenance/{id}` supprimer

### Traccar Sync
- `POST /api/v1/tracking/sync-devices` synchroniser devices depuis Traccar
- `POST /api/v1/tracking/sync-positions` synchroniser positions
- `POST /api/v1/tracking/sync-trips` synchroniser trajets

### Fleet Dashboard
- `GET /api/v1/fleet/overview` statistiques flotte (total, active, maintenance, alertes)
- `GET /api/v1/fleet/live-map` positions temps reel de tous les vehicules
- `GET /api/v1/fleet/reports/fuel` rapport consommation carburant
- `GET /api/v1/fleet/reports/mileage` rapport kilometrage
- `GET /api/v1/fleet/reports/maintenance-due` maintenances a venir (30 jours)
- Couverture Feature : overview scope au tenant, live-map avec Traccar fake sans HTTP externe, rapports carburant/kilometrage groupes par vehicule, maintenances dues a 30 jours et refus non authentifie.
## Module IA (Sprint 7-8)

### Chat IA
- `POST /api/ai/chat` envoie un message, retourne la reponse IA avec conversation_id
- `POST /api/ai/chat` avec `conversation_id` existant continue la conversation
- Validation : `message` requis, max 2000 caracteres
- Rate limiting : quota par plan SaaS (trial: 10, starter: 50, business: 200/mois)
- Feature flag : retourne 403 si `AI_ENABLED=false`
- RBAC : authentification Sanctum requise

### Historique conversations
- `GET /api/ai/chat/history` retourne les conversations paginées de l'utilisateur
- `DELETE /api/ai/chat/{conversationId}` supprime une conversation
- Isolation tenant : chaque utilisateur ne voit que ses conversations dans son entreprise

### Actions write IA avec confirmation
- Les outils write (`create_absence`, `approve_absence`, etc.) retournent `confirmation_required` avec `pending_action_id` sans mutation immediate
- `POST /api/v1/ai/actions/{pendingActionId}/confirm` execute l'action apres validation utilisateur
- `POST /api/v1/ai/actions/{pendingActionId}/reject` annule l'action en attente
- Isolation : un utilisateur ne peut confirmer que ses propres actions pending dans son tenant
- Couverture Feature : `AIWriteActionConfirmationTest` (confirmation, rejet, approve_absence, orchestrator pending_confirmations)

### Tool Registry
- `GET /api/ai/tools` liste les outils IA actifs (debug/admin)
- Les outils sont filtrés par role (employee, manager, admin)
- 15 outils enregistrés : get_employees, search_employees, get_departments, get_headcount, etc.

### Middlewares IA
- `AIFeatureCheck` : bloque si `AI_ENABLED=false`
- `AITenantInjector` : injecte company_id et user_id dans le request
- `AIRateLimiter` : quota mensuel par entreprise

---

## Modules RH Avances (Sprint 11-12)

### Recrutement — Actions avancees
- `POST /api/v1/recruitment/jobs/{id}/publish` publier une offre (draft -> published)
- `POST /api/v1/recruitment/jobs/{id}/close` fermer une offre (published -> closed)
- `DELETE /api/v1/recruitment/jobs/{id}` supprimer (draft uniquement)
- `GET /api/v1/recruitment/applicants/{id}` detail candidat avec entretiens
- `PATCH /api/v1/recruitment/applicants/{id}/status` changer statut pipeline
- `DELETE /api/v1/recruitment/applicants/{id}` supprimer candidat
- `PATCH /api/v1/recruitment/interviews/{id}/feedback` ajouter feedback + notation
- `DELETE /api/v1/recruitment/interviews/{id}` annuler entretien

### Self-service employe
- `GET /api/v1/me/trainings` mes inscriptions formations avec details cours/session
- `POST /api/v1/me/trainings/{sessionId}/enroll` auto-inscription a une session
- `GET /api/v1/me/loans` mes prets avec compteur echeances
- `GET /api/v1/me/loans/{id}/repayments` echeancier de mon pret

### Rapports avances
- `GET /api/v1/reports/recruitment-pipeline` candidats par statut
- `GET /api/v1/reports/training-completion` inscriptions par statut
- `GET /api/v1/reports/loan-summary` montants prets par statut
- `GET /api/v1/reports/demographics` effectifs par departement et type contrat
- `GET /api/v1/reports/cost-analysis` analyse couts (prets, formations) par annee

---

## Dashboard, Notifications & Exports (Sprint 15-16)

### Dashboard
- `GET /api/v1/dashboard/summary` resume (employes, departements, pointage today, absences pending)
- `GET /api/v1/dashboard/recent-activity` activite recente depuis audit_logs
- `GET /api/v1/dashboard/kpi` KPI mensuel (turnover, new hires, absence rate)
- Couverture Feature requise : isolation tenant, limite recent activity, KPI compatible SQLite/PostgreSQL

### Notifications
- `GET /api/v1/notifications` liste paginee avec unread_count
- `GET /api/v1/notifications/unread` non-lues uniquement
- `PATCH /api/v1/notifications/{id}/read` marquer comme lue
- `POST /api/v1/notifications/mark-all-read` tout marquer comme lu
- Couverture Feature requise : seules les notifications de l'employe authentifie sont visibles/modifiees

### Exports
- `GET /api/v1/export/employees` export JSON ou CSV des employes
- `GET /api/v1/export/attendance` export pointage avec filtre dates


## Billing, Onboarding & Feature Flags (Sprint 13-14)

### Billing / Abonnements
- `GET /api/v1/billing/subscription` detail abonnement courant
- `POST /api/v1/billing/subscription/upgrade` changer de plan (starter/business/enterprise)
- `POST /api/v1/billing/subscription/cancel` annuler abonnement avec raison
- `POST /api/v1/billing/subscription/renew` renouveler abonnement annule
- `GET /api/v1/billing/invoices` liste factures paginee
- `GET /api/v1/billing/invoices/{id}` detail facture avec paiements
- `GET /api/v1/billing/invoices/{id}/pdf` lien PDF facture
- RBAC : upgrade/cancel/renew reserves aux managers
- Couverture Feature : renew abonnement annule, refus cancel/renew employe, liste/detail/PDF factures scopes au tenant authentifie

### Webhooks paiement
- `POST /api/v1/webhooks/stripe` webhook Stripe (invoice.paid, payment_failed, subscription.deleted)
- `POST /api/v1/webhooks/chargily` webhook Chargily (checkout.paid)
- Pas d'authentification requise (endpoints publics)
- Couverture Feature requise : facture payee, paiement cree, past_due, annulation abonnement
- Couverture Feature negative : facture Stripe inconnue, evenement Stripe inconnu et facture Chargily inconnue ne doivent creer aucun paiement ni changer abonnement/facture existants

### Onboarding enrichi
- `GET /api/v1/onboarding-setup/checklist` checklist dynamique (auto-seed 10 etapes si vide)
- `GET /api/v1/onboarding-setup/progress` pourcentage progression
- `PATCH /api/v1/onboarding-setup/{stepKey}/complete` marquer etape complete
- `PATCH /api/v1/onboarding-setup/{stepKey}/skip` sauter etape (non-required seulement)
- Couverture Feature : auto-seed 10 etapes, progression completed/skipped, completion par tenant, skip optionnel, refus skip obligatoire et isolation inter-tenant

### Feature Flags
- `GET /api/v1/feature-flags/matrix` matrice complete features x plans
- `GET /api/v1/feature-flags/check/{featureKey}` verifier si feature active pour company
- `PUT /api/v1/feature-flags/matrix` refuse les utilisateurs tenant ; les ecritures matrice passent par les contrats plateforme super-admin
- Couverture Feature : lecture matrix, check par abonnement actif, fallback trial, feature inconnue desactivee et refus ecriture matrix depuis un utilisateur tenant

### Rapports avances
- `GET /api/v1/reports/recruitment-pipeline` candidats par statut
- `GET /api/v1/reports/training-completion` inscriptions par statut
- `GET /api/v1/reports/loan-summary` montants prets par statut
- `GET /api/v1/reports/demographics` effectifs par departement et type contrat
- `GET /api/v1/reports/cost-analysis` analyse couts (prets, formations) par annee

---

## IA Avancee — Voice, Agents, Analytics (Sprint 17-18)

### Voice IA
- `POST /api/v1/ai/voice/transcribe` audio -> texte (Whisper ou Deepgram)
- `POST /api/v1/ai/voice/synthesize` texte -> audio (Edge TTS ou ElevenLabs)
- `POST /api/v1/ai/voice/command` pipeline complet audio -> IA -> audio
- Support langues : fr, ar, tr, en
- Rate limiting applique
- Couverture Feature : transcribe/synthesize restent stables sans cles provider externes, et `voice/command` garde le contrat orchestrateur `conversation_id`, reponse IA et URL audio nullable

### Agents autonomes
- `POST /api/v1/ai/agent/run` executer une tache multi-step (max 10-20 etapes)
- `GET /api/v1/ai/agent/workflows` lister les workflows predefinis
- Workflows : prepare_payroll, weekly_report, new_employee_onboarding
- Couverture Feature : workflows predefinis presents, execution agent borne par `max_steps`, validation refuse `max_steps > 20`

### Analytics IA
- `GET /api/v1/ai/analytics/usage` utilisation du tenant authentifie (requests, tokens, couts)
- `GET /api/v1/ai/analytics/costs` couts par periode et provider (day/week/month)
- `GET /api/v1/ai/analytics/tools` outils les plus appeles
- `GET /api/v1/ai/analytics/errors` taux de succes + erreurs recentes
- Couverture Feature requise : colonnes reelles `ai_audit_logs`, isolation tenant, historique/tools IA scopes
- RBAC : analytics IA reservees aux managers `principal` et `rh`; les managers `dept` et `superviseur` doivent recevoir `403`
- Couverture Feature : `usage`, `tools`, `errors` et `costs` groupable par `day/week/month` restent scopes au tenant authentifie
- CI : toute retouche de ces routes doit rester couverte par le gate Pint diff-aware et par les tests Feature RBAC associes

---

## DevOps — Health enrichi, Metrics, Structured Logging (Post-Sprint)

### Health enrichi
- `GET /api/v1/health` — Inclut desormais : queue (driver + size), memory (usage_mb, peak_mb, limit_mb), environment, uptime_seconds
- `GET /api/v1/health/live` — Sonde liveness inchangee
- `GET /api/v1/health/ready` — Sonde readiness inchangee

### Metrics platform
- `GET /api/v1/metrics` — Retourne: companies (total, active, trial), employees (total, active), system (php_version, laravel_version, memory_usage_mb, cache_driver, queue_driver, db_driver)
- `GET /api/v1/platform/metrics/overview` — Retourne: revenue (currency, mrr, arr, collected_30d, overdue_total), companies, subscriptions, billing, system et generated_at pour le cockpit super-admin

### Structured Logging
- Middleware `StructuredLogging` enregistre chaque requete API en JSON : method, uri, status, duration_ms, ip, user_agent, user_id, company_id, request_id
- Channel `structured` : daily JSON logs dans `storage/logs/structured.log`
- Channel `audit` : daily JSON logs dans `storage/logs/audit.log` (90 jours retention)
- Couverture Feature : requetes API non-health journalisees sur le channel `structured`, sondes health exclues du bruit de logs

## Paie avancee — PDF, Bank Export, Billing (Post-Sprint)

### Bulletin de paie PDF
- `GET /api/v1/pay-slips/{id}/pdf` telecharger le bulletin en PDF (manager ou employe proprietaire)
- `GET /api/v1/me/pay-slips/{id}/pdf` telecharger son propre bulletin (self-service)
- Template Blade adapte par pays (DZ, MA, TN, FR, TR, SN) avec mentions legales
- DomPDF genere le PDF A4 portrait

### Envoi bulletins
- `POST /api/v1/payroll-runs/{id}/send-slips` marquer les bulletins comme envoyes (manager)
- Verifie que le run est valide avant envoi

### Export bancaire reel
- `POST /api/v1/payroll-runs/{id}/bank-export` avec format : sepa_xml, ccp_dz, virement_ma, csv_generic
- SEPA XML : format pain.001.001.03 pour virements europeens
- CCP Algerie Poste : format texte fixe (entete, detail, total)
- CSV generique / virement_ma : employee_id, first_name, last_name, iban, bank_account, net_salary, currency, period
- Les tests doivent couvrir que l'export ne selectionne pas de colonnes employees inexistantes (`rib`, `bank_name`)

### Facture PDF (Billing)
- `GET /api/v1/billing/invoices/{id}/pdf` telecharger la facture en PDF
- Numero auto-incremente LEO-2026-XXXX
- Mentions legales et TVA incluses

### Scheduled jobs billing
- `billing:check-trials` (daily) : notifier les trials expirant dans 3 jours
- `billing:check-overdue` (daily) : marquer les factures en retard comme overdue
- `billing:generate-invoices` (monthly) : generer les factures pour les abonnements actifs

### Leave carry-forward
- `leave:carry-forward` (annuel) : reporter les soldes non utilises selon LeavePolicy
- Expiration des reports selon carry_forward_expiry_days
