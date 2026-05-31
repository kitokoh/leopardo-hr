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

Note 2026-05-21 : les listes critiques consommees par mobile/admin (`employees`, `absences`, `attendance`, `me/pay-slips`, `notifications`) doivent conserver pagination, filtres, tri allowliste, payload vide et erreurs de validation couverts par `ApiListQueryContractTest`. Aucun `sort_by` libre ne doit atteindre une requete SQL.

Note 2026-05-25 : le mobile RH consomme maintenant `GET/POST /employees` pour l'equipe et `GET/POST /salary-advances` pour les demandes d'avance. Les scenarios API doivent verifier que la creation employe accepte et retourne les champs RH minimum (`contract_start`, `salary_type`, `salary_base` ou `hourly_rate`, `extra_data.department/job_title/work_location`) et que les avances employee-side restent soumises au workflow RH avec `repayment_months`.

Note 2026-05-27 : le compte employee durable consomme `PATCH /auth/profile`, `GET /me/career` et `GET /cabinet/stats`. Les scenarios API doivent verifier que les contacts personnels facultatifs (`personal_email`, `recovery_email`, `personal_phone`) sont sauvegardes, que la timeline carriere reste scopee a l'utilisateur courant et que les statistiques du placard numerique restent propres a l'employe authentifie.

Note 2026-05-27 : l'onboarding QR mobile consomme `GET /me/qr-profile`, `GET /company/qr-onboarding`, `POST /company/qr-onboarding/scan-employee`, `POST /company/qr-onboarding/create-employee` et `POST /me/company-qr/scan`. Les scenarios API doivent verifier les jetons signes/expires, le rejet des jetons modifies, le pre-remplissage manager, la creation depuis QR avec email professionnel unique et la demande d'integration employe via QR entreprise.

Note 2026-05-27 : le mobile employee consomme `GET /me/monthly-summary` pour l'ecran "Mon mois complet". Les scenarios API doivent verifier le mois vide avec `breakdown=[]`, totaux a zero, `period.from/to` stables et `year`/`month` retournes comme entiers meme quand ils viennent de query params.

Note 2026-05-27 : le cockpit manager mobile consomme `GET /attendance`, `GET /attendance/anomalies`, `GET /attendance/corrections`, `PUT /attendance/corrections/{id}/approve` et `PUT /attendance/corrections/{id}/reject`. Les scenarios API doivent verifier l'isolation tenant, l'interdiction employee, la file `pending` paginee et l'application d'une correction employee en pointage manuel recalcule.

Note 2026-05-27 : les estimations attendance doivent rester compatibles avec le pointage multi-session. Les scenarios API doivent verifier que `GET /me/daily-summary`, `GET /me/monthly-summary`, `GET /employees/{id}/daily-summary` et `GET /employees/{id}/quick-estimate` agregent toutes les sessions d'une journee, exposent `sessions_count`, et ne retombent jamais sur un filtre dur `session_number = 1`.

Note 2026-05-28 : les demandes RH visibles par mobile manager doivent etre decisionnables sans ambiguite. Les scenarios API doivent verifier que `GET /salary-advances` expose `employee`, `employee_name`, `company_id`, `requested_at`, `reason`, `amount`, `repayment_months` et que `GET /absences` expose `employee_name`, `absence_type`, periode, duree et motif. Les actions approve/reject doivent recharger le meme contexte dans la reponse.

Note 2026-05-28 : la liste mobile manager `GET /api/v1/employees` doit exposer `work_state` / `work_state_label` pour les etats `present`, `break`, `leave`, `mission`, `absent`, `offline`. Les scenarios API doivent verifier que ces etats restent scopes au tenant et que seul un manager principal peut modifier `role` / `manager_role` via `PATCH /employees/{employee}`.

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
- Liste employees refuse les tris non allowlistes et retourne un payload vide stable quand la recherche ne matche rien
- Organigramme retourne uniquement les employes du tenant courant et construit l'arbre sans scans repetes par noeud
- Chaine manager et subordonnes refusent les IDs hors tenant
- Creation employee avec validations metier
- Creation employee depuis mobile/RH avec date d'embauche, matricule, type de paie, salaire/taux horaire et metadonnees poste/departement/lieu
- Creation employee depuis QR employe : le QR pre-remplit le profil, le manager renseigne un email professionnel unique et les donnees contractuelles, puis l'API cree l'employe dans le tenant du manager.
- Demande d'integration via QR entreprise : un employe authentifie soumet une demande rattachee a l'entreprise cible sans valider automatiquement l'embauche.
- Mise a jour employee avec verifications unicite/global email
- Desactivation / reactivation employee
- Consultation detail employee selon role
- Refus d'acces pour employee sur dossier d'un autre employee

### 6. Presence / attendance

- Check-in succes
- Check-out succes
- Pointage multi-session : apres un check-out, un employe peut recreer une session le meme jour avec `work_type` (`resume`, `overtime`, `mission`, `travel`) et un `session_number` incremente.
- `GET /attendance/today` expose `sessions` et `summary` pour afficher details de journee, pauses, heures supp et session ouverte sur mobile.
- Les resumes et estimations `me` / `employees/{id}` additionnent toutes les sessions de la journee et retournent `sessions_count`, heures travaillees, heures supplementaires et gains sans ignorer les sessions 2+.
- Double check-in interdit
- Check-out sans check-in interdit
- Historique presence retourne des donnees coherentes
- Historique presence supporte filtre statut, intervalle de dates, tri allowliste, pagination et payload vide
- Resume du jour correct selon fuseau et etat
- Conflits ou doublons geres sans corruption des donnees
- `POST /attendance/corrections` permet a un employe de demander une modification de pointage sans ecriture directe sur le log, avec refus des heures futures, du checkout avant check-in et des logs hors utilisateur.
- `PUT /attendance/{attendanceLog}` reste reserve aux managers `principal` et `rh` pour modifier directement un log du tenant courant, avec refus des employes et des managers non autorises.
- `GET /attendance/anomalies` retourne un resume d'impact business (`late_minutes`, sorties manquantes, corrections, actions critiques)
- Chaque anomalie attendance expose une action manager recommandee et un flag `requires_manager_action`

### 6.b Taches terrain apres pointage

- `GET /tasks/today` retourne uniquement les taches assignees a l'employe courant et dues aujourd'hui.
- Un manager peut creer une tache avec duree prevue, priorite, categorie, recurrence et cle de template.
- Un employe assigne peut passer une tache a `done` avec duree realisee et note de realisation.
- Le score de performance d'une tache terminee est calcule sans exposer les taches d'un autre tenant.
- Les anomalies geofence, heures supplementaires et sequences rapides restent scopees au tenant courant

### 7. Conges / absences

- Creation demande de conge par employee
- Validation / refus par manager ou HR
- Solde mis a jour correctement
- Chevauchement de periodes refuse
- Consultation historique des demandes par role
- Liste absences supporte filtres statut/periode/employe, tri allowliste, pagination et payload vide
- Employee ne peut pas valider sa propre demande sans permission speciale
- Liste paginee `GET /api/v1/absences` expose pour le dashboard manager les champs derives `employee_name` et `type` (nom du type d'absence) en plus des relations `absence_type` / `absenceType`

### 8. Paie / finance

- Acces bulletins par employee
- Liste `GET /api/v1/me/pay-slips` supporte filtre statut `validated|sent`, tri allowliste, pagination et refuse les statuts internes `calculated`
- Detail `GET /api/v1/me/pay-slips/{id}` expose les lignes du bulletin dans un payload stable pour mobile
- Acces synthese payroll par finance / HR
- Refus d'acces payroll pour roles non autorises
- Calculs exposes sans fuite inter-tenant
- Etats de paie invalides rejetes proprement
- Declarations sociales CNAS DZ, CNSS MA et DSN FR reservees aux managers, validees par periode et scopees au tenant courant
- Les declarations sociales utilisent les identifiants entreprise depuis `companies.metadata` et les donnees salarie via les casts Eloquent, notamment `national_id` `encrypted`
- Les declarations CNSS MA comptent les jours travailles depuis `attendance_logs` du trimestre courant sans inclure les autres tenants
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
- Exports admin `GET /api/v1/export/{employees,attendance,contracts,vehicles,pay-slips,absences,training}` restent authentifies, reserves manager et disponibles pour le dashboard Cloudflare Pages.
- Le test contractuel `FrontendApiContractTest` garde les routes critiques admin, mobile et kiosk afin qu'un renommage backend ne casse pas silencieusement les frontends.
- Les endpoints kiosque terrain `employee-info`, `announcements`, `leave-balance` et `qr-punch` restent accessibles avec `X-Kiosk-Token` sans bearer Sanctum utilisateur.

### 10. Notifications / evenements / audit

- Evenement metier declenche la notification attendue
- Liste notifications supporte filtre `type`, filtre `unread` et alias mobile `unread_only`, tri chronologique allowliste, pagination, `unread_count` et payload vide stable
- Endpoints mobiles de lecture (`PUT /notifications/{id}/read`, `PUT /notifications/read-all`) et suppression (`DELETE /notifications/{id}`) restent scopes a l'utilisateur authentifie et auditent `communication_events`
- `POST /api/v1/client-events` persiste uniquement les evenements UX allowlistes, exige auth + tenant, minimise les proprietes et refuse les evenements non fiables comme `login_failed`
- `GET/PATCH /api/v1/notification-preferences` cree et met a jour les preferences de canaux de l'utilisateur authentifie, avec audit `communication_events`
- `GET /api/v1/communication/analytics` est reserve aux managers `principal` et `rh`, retourne uniquement les agregats du tenant courant, et refuse les employes.
- `GET /api/v1/launch-readiness` est reserve aux managers `principal` et `rh`, retourne score, blocages requis, prochaines actions et checks go-live tenant-scopes.
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
- Le mobile distingue la correction employe (`POST /attendance/corrections`) de la modification RH/manager (`PUT /attendance/{attendanceLog}`), et conserve un etat UI lisible meme si l'historique de la semaine echoue.
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
- Employees mobile manager : payload `work_state`, principal-only pour nomination/revocation RH, refus RH non principal
- Isolation tenant
- Isolation tenant par chaine FK : `WebhookDelivery`, `PaySlipLine`, `ApprovalDecision`, `ExpenseItem` doivent etre filtres via leur parent portant `company_id`
- Attendance check-in / check-out / history
- Attendance anomalies business impact / recommended actions
- Attendance monthly report JSON / CSV / PDF payroll estimates
- Self-service `GET /api/v1/me/monthly-summary` : mois vide, totaux zero, breakdown vide, periode stable et types JSON compatibles mobile
- Manager mobile `GET/PUT /api/v1/attendance/corrections*` : file pending, approve applique le pointage, reject cloture la demande, isolation tenant stricte
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

### Module C — Avances salaire mobile
- `GET /api/v1/salary-advances` retourne les avances de l'employe connecte, et la liste tenant pour manager/RH autorise.
- `POST /api/v1/salary-advances` permet a un employe de demander une avance avec `amount`, `reason` et `repayment_months`.
- RBAC : un employe ne peut creer une demande que pour lui-meme ; la decision reste reservee au workflow RH/manager.
- Isolation tenant : la liste et les decisions ne doivent jamais exposer les avances d'un autre tenant.
- Contrat mobile : apres creation, la reponse expose `status=pending`, `amount`, `reason`, `repayment_months`, `monthly_deduction`, `amount_remaining` et `repayment_plan` si calcule.

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
- `GET /api/v1/pay-slips` liste paginee tous les bulletins du tenant (manager), filtres optionnels `payroll_run_id` (404 si run hors tenant), `status` (`calculated|validated|sent`), sans lignes de detail — utilise par le SPA admin pour eviter un GET par run
- `GET /api/v1/payroll-runs/{id}/pay-slips` liste les bulletins d'un run (manager), avec lignes chargees (legacy / detail par run)
- `GET /api/v1/pay-slips/{id}` detail bulletin avec lignes (manager ou employe concerne)
- `GET /api/v1/pay-slips/{id}/pdf` telecharge le PDF du bulletin (manager ou employe proprietaire) ; si `pdf_path` pointe vers un fichier present sur le disque `local`, le fichier est servi sinon generation synchrone DomPDF
- `POST /api/v1/payroll-runs/{id}/send-slips` exige un run valide/paye et marque les bulletins emailable comme envoyes
- RBAC : manager voit tout, employe voit uniquement ses bulletins ; `GET /pay-slips` refuse les employes (403)
- Couverture Feature : index tenant + filtres + isolation + 422 status invalide, liste par run scopee tenant, self-service validated/sent uniquement, detail proprietaire, PDF protege, send-slips bloque avant validation et refus employe sur liste manager.

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
- `GET /api/v1/dashboard/manager-digest` signaux mobile manager du jour (presence, retards, sessions ouvertes, actions RH pending)
- Couverture Feature requise : isolation tenant, scope equipe directe pour manager departement, limite recent activity, KPI compatible SQLite/PostgreSQL

### Horaires manager
- `GET /api/v1/schedules` liste les horaires tenant-scope visibles mobile manager
- `POST /api/v1/schedules` cree un horaire avec pause, tolerance retard, jours travailles et seuils heures supp
- `PUT /api/v1/schedules/{schedule}` modifie les regles horaires existantes
- `DELETE /api/v1/schedules/{schedule}` supprime un horaire non defaut
- `POST /api/v1/employees` peut recevoir `schedule_id` pour affecter l'horaire des la creation employe
- `PATCH /api/v1/employees/{employee}` peut corriger horaire, date d'embauche, salaire/taux horaire et metadonnees poste/departement/lieu depuis la fiche mobile manager
- Couverture Feature requise : manager autorise, employe refuse, isolation tenant des horaires, refus d'un `schedule_id` hors entreprise

### Taches du jour et pointage

- `POST /api/v1/tasks` permet au manager d'assigner une tache du jour a un employe du meme tenant avec duree estimee, priorite, categorie et `template_key`
- `GET /api/v1/tasks/today` alimente le pointage employe et la vue manager du jour
- `PATCH /api/v1/tasks/{task}` permet a l'employe assigne de declarer `status=done`, `completed_minutes` et `completion_note`
- Couverture Feature requise : refus d'un `assigned_to` hors entreprise et calcul du `performance_score` a la completion

### Notifications
- `GET /api/v1/notifications` liste paginee avec unread_count
- `GET /api/v1/notifications/unread` non-lues uniquement
- `PATCH /api/v1/notifications/{id}/read` marquer comme lue
- `POST /api/v1/notifications/mark-all-read` tout marquer comme lu
- `PUT /api/v1/notifications/{id}/read`, `PUT /api/v1/notifications/read-all` et `DELETE /api/v1/notifications/{id}` gardent les alias mobiles employee/manager
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
- `GET /api/v1/pay-slips` liste paginee les bulletins du tenant (manager), filtres `payroll_run_id` / `status`
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

### Declarations sociales (CNAS DZ / CNSS MA / DSN FR)
- `POST /api/v1/social-declarations/cnas-dz` genere une declaration trimestrielle CNAS Algerie avec taux salarie 9% et employeur 26%
- `POST /api/v1/social-declarations/cnss-ma` genere une declaration trimestrielle CNSS Maroc avec jours travailles
- `POST /api/v1/social-declarations/dsn-fr` genere une declaration mensuelle DSN simplifiee France (format S10/S20/S21/S44)
  - Parametres : `month` (1-12), `year` (2020-2099)
  - Mapping types contrat : CDI→01, CDD→02, INTERIM→03, APPRENTISSAGE→04, PROFESSIONNALISATION→05, STAGE→07
  - La reponse contient `content` (texte DSN), `filename` (DSN_FR_MM_YYYY_date.dsn), `employee_count`
- Les trois endpoints sont reserves aux roles manager (isManager)
- Les calculs s'appuient sur les bulletins valides (`pay_slips` status=validated) du mois/trimestre demande
- Isolation tenant : les bulletins et employes doivent etre scopes au `company_id` de l'acteur
- La requete company ne doit effectuer qu'un seul SELECT (pas de requetes dupliquees name + tax_id)

### Notifications temps reel (SSE)
- `GET /api/v1/notifications/stream` ouvre un flux SSE (Server-Sent Events) pour les notifications en temps reel
  - Event `notification` : nouvelles notifications avec `unread_count`
  - Event `error` : session expiree
  - Event `timeout` : signal de reconnexion apres 120s
  - Heartbeat `: heartbeat` toutes les 5s
- L'endpoint est authentifie via `auth:sanctum` + tenant isolation
- Le stream verifie l'existence de l'employe a chaque iteration (securite session)

### Import employes CSV
- `POST /api/v1/employees/import` importe des employes depuis un fichier CSV avec validation ligne par ligne
- `GET /api/v1/employees/import-template` retourne un template CSV avec colonnes et exemple
- L'import utilise `Employee::create()` (pas `DB::insert`) pour respecter les casts `encrypted` sur `national_id`, `iban`, `bank_account`
- Les colonnes CSV doivent correspondre au schema reel : `address_line`, `postal_code`, `nationality` (pas `address`, `city`, `country`)
- Validation genre : `in:M,F` (pas `male/female/other`)
- Validation contract_type : `in:CDI,CDD,Stage,Interim,Consultant`
- Validation status : `in:active,inactive,on_leave,terminated,suspended`
- Detection doublons email avant insertion
- Rollback transactionnel en cas d'erreur
- Isolation tenant : l'import scope au `company_id` de l'acteur

### Compression reponse API
- Le middleware `CompressResponse` compresse les reponses JSON > 1 Ko pour les clients acceptant `Accept-Encoding: gzip`
- Les reponses compressees portent `Content-Encoding: gzip` et `Vary: Accept-Encoding`
- Les clients sans `Accept-Encoding: gzip` recoivent la reponse non compressees



### IA Workflows metier
- `POST /api/v1/ai/workflows/prepare-payroll` execute le workflow de preparation paie
  - Requiert `period_start` et `period_end` (dates)
  - Collecte les employes actifs du tenant
  - Verifie les structures salariales manquantes
  - Detecte les absences en attente de validation sur la periode
  - Compte les absences approuvees a deduire
  - Verifie si un run de paie existe deja pour la meme periode
  - Retourne un rapport multi-etapes avec status `ready` ou `requires_attention`
  - Reserve aux managers (role=manager) ; employes recoivent 403
  - Isolation tenant : toutes les requetes sont scopees au `company_id` de l'acteur
- `GET /api/v1/ai/workflows/weekly-report` genere un rapport hebdomadaire automatique
  - Parametre optionnel `week_start` (date) ; par defaut la semaine precedente
  - Effectifs par departement et par statut
  - Absences par type avec comptage pending/approved/rejected
  - Detection anomalies : employes sans pointage ni absence approuvee, contrats expirant sous 30 jours
  - Retourne un resume texte synthetique
  - Reserve aux managers ; employes recoivent 403

### Simulation cotisations sociales
- `POST /api/v1/cotisation-simulation` simule les cotisations pour un salaire brut donne
  - Requiert `gross_salary` (numeric >= 0) et `country_code` (in:DZ,MA,FR,TN,TR,SN)
  - Retourne le detail des cotisations employe et employeur par type
  - Calcule le net avant impot et le cout total employeur
  - Taux DZ : CNAS salarie 9%, employeur 26%
  - Taux MA : CNSS salarie 4.48%, AMO 2.26%, employeur CNSS 8.98%, AMO 3.40%
  - Pays non supportes retournent une erreur 422
  - Reserve aux managers ; employes recoivent 403
  - Aucune persistance : calcul en memoire uniquement

### Rapports RH avances (Iteration 8)
- `GET /api/v1/reports/headcount` retourne l'effectif total et la repartition par departement et par statut
- `GET /api/v1/reports/absenteeism` retourne le taux d'absenteisme, jours totaux, duree moyenne et repartition par type pour la periode donnee
- `GET /api/v1/reports/turnover` retourne le taux de turnover pour la periode donnee
- `GET /api/v1/reports/overtime` retourne les heures supplementaires totales, nombre d'employes concernes et repartition par departement
- `GET /api/v1/reports/payroll-summary` retourne la masse salariale (brut, net, charges patronales, nombre de bulletins)
- `GET /api/v1/reports/recruitment-pipeline` retourne les candidatures par etape du pipeline de recrutement
- `GET /api/v1/reports/training-completion` retourne le taux de completion des formations et les inscriptions par statut
- `GET /api/v1/reports/demographics` retourne la repartition demographique (age, genre, anciennete)
- `GET /api/v1/reports/cost-analysis` retourne l'analyse des couts RH par departement
- `GET /api/v1/reports/loan-summary` retourne l'encours et les remboursements de prets employes
- Tous les rapports sont scopes au `company_id` de l'acteur et reservees aux managers

### Indexes performance etendus (D6)
- La migration `2026_05_17_000001_add_extended_performance_indexes` ajoute des indexes PostgreSQL sur les colonnes filtrees des tables contracts, training_courses, training_sessions, training_enrollments, job_postings, applicants, audit_logs et webhook_endpoints
- Les indexes sont crees avec `CREATE INDEX CONCURRENTLY IF NOT EXISTS` et sont idempotents
- L'index partiel `idx_contracts_end_date` filtre sur `status = 'active'` pour optimiser les alertes d'expiration

### Predictions IA (Plan 15 C11-C13, C15)
- `GET /api/v1/predictions/turnover` retourne le scoring turnover par departement et employe, avec facteurs de risque et taux global
- `GET /api/v1/predictions/absenteeism` retourne les predictions d'absenteisme avec periodes a risque, saisonnalite et recommandations
- `GET /api/v1/predictions/notifications` retourne les notifications proactives IA (contrats expirants, periodes d'essai, anniversaires, approbations en retard, formations incompletes, soldes conges faibles)
- Les 3 endpoints sont reserves aux managers `principal` et `rh` (RBAC teste dans PredictionControllerTest)
- Les employes non-managers recoivent un 403
- Structure reponse : `{"data": {...}}` avec champs documentes
- Le TurnoverPredictor analyse anciennete, taux departement, absences frequentes
- L'AbsenteeismPredictor integre saisonnalite (juillet, aout, decembre) et tendances
- Le ProactiveNotificationService agrege 6 types de notifications tries par severite (critical > warning > info)
### Audit logs UI (E9 - Iteration 9)
- `GET /api/v1/audit-logs` retourne les logs d'audit pagines, scopes au `company_id` de l'acteur
- `GET /api/v1/audit-logs/export?format=csv` exporte les logs d'audit au format CSV
- Les logs contiennent `user_name`, `action`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`
- Filtrage par action (created, updated, deleted, login, logout, exported) et par type d'entite (Employee, Contract, Absence, PayrollRun, etc.)
- Isolation tenant : les logs sont filtres par `company_id`


### SSO SAML/OIDC (Plan 15 K2)
- `GET /api/v1/sso/providers` retourne la liste des protocoles SSO supportes (SAML 2.0, OpenID Connect) — public, pas d'auth
- `GET /api/v1/sso/status` retourne le statut SSO de l'entreprise (enabled, provider) — RBAC manager principal uniquement
- `POST /api/v1/sso/configure` configure SSO pour l'entreprise (provider in:saml,oidc, entity_id URL, sso_url URL) — RBAC manager principal
- `DELETE /api/v1/sso/disable` desactive SSO pour l'entreprise — RBAC manager principal
- `POST /api/v1/sso/saml/{companyId}/callback` recoit la reponse SAML de l'IdP — stub, validation complete a implementer
- `GET /api/v1/sso/oidc/{companyId}/callback` recoit le callback OIDC (code, state, id_token) — stub, echange token a implementer
- Les endpoints de gestion (status, configure, disable) sont proteges par auth:sanctum + tenant
- Les callbacks sont publics (recus directement de l'IdP)
- Configuration stockee en JSONB dans company_sso_configs (unique par company_id)

### Optimisation planning IA (C14 - Iteration 12)
- `GET /api/v1/planning/weekly-optimization` retourne l'analyse de couverture departement, les conflits planning et les recommandations pour la semaine donnee
- `GET /api/v1/planning/weekly-optimization?week_start=2026-06-01` accepte une date de debut de semaine optionnelle
- `GET /api/v1/planning/shift-rebalancing` retourne l'analyse de repartition des effectifs par departement avec suggestions de reequilibrage
- Le score d'optimisation est un entier 0-100 base sur la couverture departementale et le nombre de conflits
- Isolation tenant : toutes les requetes sont scopees au `company_id` de l'acteur authentifie
- Authentification requise : les endpoints retournent 401 sans token valide

### Push Notifications / Device Tokens (G8 - Batch 1)
- `POST /api/v1/device-tokens` enregistre un token FCM (platform: ios/android/web, token: string) — self-service employe
- `DELETE /api/v1/device-tokens` supprime un token FCM — self-service employe
- `GET /api/v1/device-tokens` liste les tokens actifs de l'employe connecte
- `POST /api/v1/push-notifications/send` envoie une notification push a un employe — RBAC manager uniquement
- Les tokens invalides (NotRegistered, InvalidRegistration) sont automatiquement desactives

### Calendar Sync (L6 - Batch 1)
- `GET /api/v1/calendar/connections` liste les connexions calendrier de l'employe (Google, Outlook, CalDAV)
- `POST /api/v1/calendar/connect` connecte un calendrier (provider, access_token, refresh_token, calendar_id)
- `DELETE /api/v1/calendar/disconnect/{provider}` deconnecte un calendrier
- `POST /api/v1/calendar/sync` synchronise les conges et formations vers le calendrier externe
- `GET /api/v1/calendar/events?from=2026-01-01&to=2026-12-31` liste les evenements calendrier synchronises

### ZKTeco Integration (L5 - Batch 1)
- `GET /api/v1/zkteco/devices` liste les pointeuses ZKTeco de l'entreprise — RBAC manager
- `POST /api/v1/zkteco/devices` enregistre une nouvelle pointeuse (serial_number, name, ip_address, port, protocol)
- `GET /api/v1/zkteco/devices/{id}` detail pointeuse + historique sync
- `PUT /api/v1/zkteco/devices/{id}` met a jour une pointeuse
- `DELETE /api/v1/zkteco/devices/{id}` supprime une pointeuse
- `POST /api/v1/zkteco/heartbeat/{serialNumber}` heartbeat device → marque online — pas d'auth Sanctum
- `POST /api/v1/zkteco/sync-attendance/{serialNumber}` sync pointages depuis device — pas d'auth Sanctum
- `POST /api/v1/zkteco/devices/{serialNumber}/push-users` pousse la liste employes vers le device — RBAC manager
- `GET /api/v1/zkteco/devices/{id}/sync-logs` historique des synchronisations

### Kiosk Extensions (H1-H4 - Batch 1)
- `POST /api/v1/kiosks/{deviceCode}/employee-info` info employe post-pointage (nom, departement, poste, photo, pointage du jour, solde conges)
- `GET /api/v1/kiosks/{deviceCode}/announcements` annonces actives pour le kiosk (titre, corps, priorite, dates)
- `POST /api/v1/kiosks/{deviceCode}/leave-balance` solde conges par identifiant employe
- `POST /api/v1/kiosks/{deviceCode}/qr-punch` pointage par QR code (decode base64 JSON → matricule/employee_id)
- Tous les endpoints kiosk necessitent le header X-Kiosk-Token pour l'authentification device

### Auth Token Refresh (D4 - Iteration 13)
- `POST /api/v1/auth/refresh-token` genere un nouveau token Sanctum avec les memes abilities
- L'ancien token est supprime apres rotation
- Le nouveau token respecte la duree d'expiration configuree dans `sanctum.expiration`
- Necessite `auth:sanctum` — tout role authentifie peut rafraichir son token

### Queue Jobs (D2 - Iteration 13)
- `ProcessPayrollBatchJob` dispatche sur la queue `payroll` avec 3 retries et 600s timeout
- `SendBulkNotificationsJob` dispatche sur la queue `notifications` avec 3 retries et 120s timeout
- Les jobs filtrent par `company_id` pour garantir l'isolation tenant
- Tags Horizon : `company:{id}`, `payroll_run:{id}` / `notification:{class}`

### Model Policies (Plan 23 - Iteration 5)
- AbsencePolicy : viewAny (tous), view (owner+managers), create (employes actifs), approve/reject (managers), delete (owner, pending uniquement)
- ContractPolicy : viewAny (managers), view (owner+managers), create/update/activate/terminate/renew (P, RH)
- DepartmentPolicy, PositionPolicy, SchedulePolicy : viewAny (tous), view (meme entreprise), create/update (managers), delete (P, RH)
- SitePolicy : viewAny (tous), view (meme entreprise), create/update (managers), delete (P uniquement)
- ApprovalRequestPolicy : viewAny (tous), view (demandeur+managers), create (employes actifs), approve/reject (managers, pending uniquement)
- LoanPolicy : viewAny (tous), view (owner+managers), create (employes actifs), approve/reject/disburse (P, FIN)
- ExpenseClaimPolicy : viewAny (tous), view (owner+managers), create (employes actifs), approve/reject (P, FIN, RH), delete (owner, brouillon uniquement)
- InvoicePolicy : viewAny/view (P, FIN), create/pay (P)
- WebhookEndpointPolicy : viewAny/view/create/update/delete (P uniquement)
- Toutes les policies enregistrees dans AuthServiceProvider via Gate::policy()

### API Resources Normalization (Plan 23 - Iteration 1)
- Les controllers AbsenceController, DepartmentController, PositionController, ScheduleController, SiteController, NotificationController, WebhookController, ApprovalController, ContractController retournent des JsonResource au lieu de tableaux manuels
- Chaque Resource expose un contrat JSON stable (dates ISO-8601, relations conditionnelles via whenLoaded)
- Les collections paginees conservent les meta standard Laravel (current_page, last_page, per_page, total)

### FormRequests Extraction (Plan 23 - Iteration 2)
- StoreDepartmentRequest, UpdateDepartmentRequest, StorePositionRequest, UpdatePositionRequest validations avec authorize() gates
- StoreScheduleRequest, UpdateScheduleRequest validations horaires, jours, tolerances
- StoreSiteRequest, UpdateSiteRequest validations GPS (lat -90/90, lng -180/180, radius 10-5000m)
- StoreWebhookEndpointRequest, UpdateWebhookEndpointRequest validations URL + events whitelist

### ApiError Enum (Plan 23 - Iteration 4)
- ApiError backed enum avec ~40 codes (auth, authz, not found, validation, business logic, rate limit, server)
- Methode `->status()` retourne le HTTP status code correspondant
- Methode `->message()` charge la traduction i18n (FR/EN/AR/TR) ou fallback anglais
- Methode `->response()` retourne une JsonResponse formatee {error, message}

### DB Transactions (Plan 23 - Iteration 3)
- ContractController::renew enveloppe creation nouveau contrat + expiration ancien dans DB::transaction
- ApprovalController::approve/reject enveloppe creation decision + mise a jour statut dans DB::transaction
- NotificationController::markRead/markAllRead enveloppe update + audit CommunicationEvent dans DB::transaction

### API Manager Middleware RBAC (API Consolidation - v4.16.129)
- `EnsureApiManagerMiddleware` enregistre comme `api.manager` dans `bootstrap/app.php`
- `api.manager` sans parametres autorise tout manager (principal, rh, dept, comptable, superviseur) — refuse les employes simples avec `403 MANAGER_REQUIRED`
- `api.manager:principal,rh` autorise uniquement les roles specifies — refuse les autres managers avec `403 INSUFFICIENT_ROLE`
- `api.manager:principal` sur `/billing/subscription` refuse RH, dept, superviseur et employes
- `api.manager:principal,comptable` sur `/payroll-runs` refuse RH, dept, superviseur et employes
- `api.manager:principal,rh,comptable` sur `/export/employees` refuse dept, superviseur et employes
- `GET /me/pay-slips`, `GET /me/contracts`, `GET /me/trainings`, `GET /me/loans` accessibles a tout employe authentifie (pas de middleware api.manager)
- `GET /org-chart` accessible a tout employe authentifie
- `GET /dashboard/summary` refuse les employes non-managers
- `ApiManagerMiddlewareTest` couvre : allow any manager, reject employee, allow specific roles, reject wrong role, reject unauthenticated

### DemoCompanySeeder Extensions (API Consolidation - v4.16.129)
- `seedContracts()` cree 6 contrats demo (CDI/CDD/stage, active/draft/expired) pour chaque entreprise demo
- `seedTrainingCourses()` cree 3 formations avec sessions et enrollments
- `seedRecruitmentJobs()` cree 3 postes avec pipeline candidats
- `seedLoans()` cree 1 pret actif + 1 en attente avec echeances
- `seedExpenseClaims()` cree 2 notes de frais avec lignes detaillees
- Tous utilisent `sharedTableExists()` pour tolerer les tables absentes
- `cleanupExistingCompany()` nettoie les 12 nouvelles tables avant re-seed

### Plans 60-65 — Double Validation Avances & Paiement en Masse (Redis Upstash)

#### Plan 60 — Double Validation Avances Salaire
- `PUT /api/v1/salary-advances/{id}/manager-approve` : manager approuve, met `validation_status=manager_approved` et `manager_approved_at`
- `PUT /api/v1/salary-advances/{id}/mark-paid` : comptable/manager declare paiement, met `payment_declared_at` et `payment_declared_by`
- `PUT /api/v1/salary-advances/{id}/confirm-received` : employe confirme reception, met `employee_confirmed_at`
- Acces refuse a un employe sans role manager sur les endpoints d'approbation (403)
- Acces refuse a un manager d'une autre entreprise (404)

#### Plan 61 — Cycles de Paie & Solde Employe (PayrollCycleController)
- `GET /api/v1/payroll/cycles` : retourne la liste paginee des PayrollRuns pour l'entreprise (manager requis)
- `GET /api/v1/payroll/cycles/current` : retourne `period_start`, `period_end`, `label` du cycle courant calcule
- `GET /api/v1/employees/{id}/balance` : retourne `gross_due`, `advances`, `paid`, `remaining` pour le cycle courant
- Employe peut consulter son propre solde ; manager peut consulter tout employe de son entreprise
- Acces refuse a un employe consultant le solde d'un autre employe sans etre manager (403)
- Acces refuse a un manager consultant un employe hors de son entreprise (404)

#### Plan 62 — Generation PDF Bulletins de Paie Async (GeneratePaySlipPdfJob)
- `GeneratePaySlipPdfJob` dispatche sur la queue `pdf` avec `tries=3`, `timeout=120`
- Le job genere le PDF via dompdf, stocke dans `payslips/{company_id}/{year}/{month}/{employee_id}.pdf`
- Met a jour `pay_slips.pdf_path` apres generation
- Notifie l'employe via `PushNotificationService` apres generation reussie
- Failure silencieuse si `PayrollRun`, `Employee`, `Company` ou `PaySlip` introuvable (log warning, pas d'exception)

#### Plan 63 — Architecture Redis Upstash / QueueHealthCheck
- `php artisan queue:health-check` retourne JSON avec `redis_ok`, `redis_latency_ms`, profondeurs des queues `default`, `pdf`, `notifications`, `payroll`, `webhooks`
- Retourne `status=error` si Redis inaccessible (exit FAILURE)
- Options `--queue=pdf --queue=payroll` limitent le check aux queues specifiees

#### Plan 64 — Cloture Automatique Presences (AutoCloseAttendanceCommand)
- `php artisan attendance:auto-close` cloture les pointages sans `check_out` depuis plus de 12h (defaut)
- `--threshold=N` parametre le seuil en heures
- `--dry-run` preview sans ecriture
- Calcule `hours_worked` = diff check_in / auto check_out (cap 8h ou now si check_in+8h est futur)
- Met `status=auto_closed`, `correction_note=auto_close`, `punch_note` explicatif

#### Plan 65 — Paiement en Masse (BulkPaymentController + ProcessBulkPaymentJob)
- `POST /api/v1/payroll-runs/{id}/bulk-pay` : dispatch `ProcessBulkPaymentJob` sur queue `payroll`, retourne 202 Accepted
- Retourne 422 si le run n'est pas en status `validated` ou `calculated`
- Retourne 409 si un job bulk-pay est deja en cours pour ce run (detection via Redis)
- `GET /api/v1/payroll-runs/{id}/bulk-pay/status` : retourne `status`, `done`, `total` depuis Redis
- Retourne `status=not_started` si aucun job trouve dans Redis
- Retourne 503 avec `error` si Redis indisponible
- `ProcessBulkPaymentJob` : marque les avances `manager_approved` en `payment_declared`, dispatch `GeneratePaySlipPdfJob` pour chaque employe, met le run en `paid`
- Ecrit la progression Redis avec TTL 1h (`bulk_pay:run:{id}`)
