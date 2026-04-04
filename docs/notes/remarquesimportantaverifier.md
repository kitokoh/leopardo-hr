🔍 ANALYSE DE FOND — LEOPARDO RH
Après lecture complète de tous les fichiers clés, voici le rapport complet.

✅ CE QUI EST RÉELLEMENT EN BON ÉTAT
Le dossier est globalement solide à 85%. Voici ce qui tient vraiment :

Multitenancy : La stratégie schema/shared est cohérente, le TenantMiddleware est bien pensé, le Global Scope Laravel est correct.
Règles métier : Calcul paie, heures sup, congés, avances — tout est précis et complet avec les cas limites.
RBAC : La matrice des 7 rôles est complète et non ambiguë.
API contrats v2 : 70 endpoints documentés avec payloads complets.
SQL : Le schéma est bien structuré, l'ordre des migrations résout la dépendance circulaire departments↔employees.
Sécurité : Chiffrement AES-256, rate limiting, conformité RGPD/pays.


🔴 CORRECTIONS OBLIGATOIRES — BUGS BLOQUANTS
1. Incohérence critique : supervisor_id vs manager_id dans employees
ERD (04_ERD_COMPLET.md) :
supervisor_id   INT   FK → employees.id NULL  (pour role superviseur)
SQL (07_SCHEMA_SQL_COMPLET.sql) :
sqlmanager_id   INT   NULL REFERENCES employees(id)
→ Ce sont deux champs différents avec deux noms différents pour la même chose. Le code Laravel va planter à la migration. Décision à prendre maintenant : choisir un nom et l'appliquer partout (ERD + SQL + Modèle Dart + API contrats + RBAC). Recommandation : garder manager_id (plus générique, moins ambigu).

2. Incohérence critique : salary_advances.status — valeur 'active' absente du SQL
ERD : CHECK IN ('pending','approved','rejected','active','repaid')
SQL : CHECK IN ('pending','approved','rejected','repaid')
Le statut 'active' est présent dans l'ERD (logique : une avance approuvée en cours de remboursement) mais absent du SQL. Le code PayrollService va échouer quand il cherchera des avances WHERE status = 'active'. Corriger le SQL immédiatement.

3. Incohérence : PlanSeeder vs modèle économique sur les features du plan Starter
Modèle éco (03_MODELE_ECONOMIQUE.md) : "excel_export": true pour Starter
Seeders (05_SEEDERS_ET_DONNEES_INITIALES.md) : 'excel_export' => false pour Starter
Une incohérence directe entre deux documents de référence. Le seeder va créer un plan Starter sans export Excel alors que la page de pricing promet le contraire. Source de vérité = modèle économique → corriger le seeder.

4. Features manquantes dans le PlanSeeder
Les seeders n'incluent pas evaluations et schema_isolation dans le JSON des features, alors que le modèle économique les liste explicitement. Claude Code va créer des plans incomplets qui casseront la vérification de feature.
php// À ajouter dans chaque plan du seeder :
'evaluations' => false,        // Starter
'schema_isolation' => false,   // Starter + Business
'evaluations' => true,         // Business
'schema_isolation' => true,    // Enterprise uniquement

5. employees.status vs employees.is_active — dualité dangereuse
ERD : utilise is_active BOOL
SQL : utilise status VARCHAR(20) CHECK IN ('active','suspended','archived') avec un INDEX dessus
Ces deux approches coexistent de façon incohérente. Le RBAC dit "employé archivé", le glossaire dit "is_active = false", la règle métier §9.1 dit employees.status = 'archived'. Le SQL a raison (status est plus riche), mais l'ERD et le modèle Dart (is_active: bool) sont en décalage. Décision : choisir status VARCHAR dans toute la doc et supprimer is_active partout.

6. Endpoint d'inscription publique absent des contrats API
Le modèle économique décrit clairement un auto-onboarding public (formulaire 5 champs, création automatique de compte Trial). Mais aucun endpoint POST /auth/register n'existe dans les contrats API v2. Claude Code ne peut pas implémenter cette fonctionnalité critique sans ce contrat. À documenter avant le démarrage.

🟠 MANQUES IMPORTANTS (non bloquants au J1, mais à régler vite)
7. TenantMigrationService mentionné mais jamais spécifié
Le service d'upgrade Business → Enterprise (migration shared → schema dédié) est mentionné dans deux documents mais aucune spécification de son implémentation n'existe. C'est un service complexe (transaction longue, rollback, zero-downtime). À documenter avant la semaine 8.
8. Le module de facturation automatique n'a pas d'API documentée
La Phase 2 prévoit l'intégration Stripe/Paydunya mais aucun webhook, aucun endpoint de gestion d'abonnement n'est esquissé. Si Phase 2 arrive et qu'il n'y a pas de contrats, le frontend sera bloqué.
9. Le flow d'onboarding guidé (4 étapes) n'a pas de wireframes dédiés
Le funnel de conversion décrit 4 étapes d'onboarding dans le dashboard, mais les wireframes (leopardo_rh_wireframes_COMPLET.html) ne couvrent pas cet écran. C'est le premier écran que voit un nouveau client — son absence dans les wireframes est un risque UX réel.
10. Aucune stratégie pour les notifications push temps réel (web)
Le frontend Vue/Inertia n'a aucune spec sur comment recevoir les notifications en temps réel. L'app Flutter utilise FCM (documenté), mais le dashboard web n'a aucune stratégie (SSE ? WebSockets ? polling ?). Cette décision doit être prise avant d'implémenter le module notifications.
11. amount_remaining dans salary_advances — présent dans l'ERD et les règles métier mais absent du SQL
La règle métier §7.2 utilise amount_remaining pour suivre le solde restant d'une avance. Le SQL ne crée pas ce champ. Le PayrollService va planter.
12. La stratégie de stockage des fichiers (photos, bulletins PDF) n'est pas définie pour o2switch
Le document 23_STOCKAGE_ET_SAUVEGARDES.md existe mais la question du stockage local vs S3 sur o2switch n'est pas tranchée clairement pour le MVP. Les endpoints retournent des URLs du type https://api.leopardo-rh.com/storage/... — il faut définir si c'est un simple public/storage Laravel ou un volume séparé.

🟡 CE QUE VOUS AVEZ PROBABLEMENT OUBLIÉ
13. Aucune limite de plan vérifiée côté API
Le plan Starter permet 20 employés max. Aucun middleware ni validation n'est spécifié pour bloquer un POST /employees quand la limite est atteinte. Ce contrôle doit être dans un PlanLimitMiddleware ou dans EmployeeService. Sans cette validation, un client Starter peut créer 200 employés.
14. Le champ zkteco_id dans employees n'est pas présent dans les modèles Dart
Le SQL a zkteco_id VARCHAR(50), l'ERD Flutter n'inclut ce champ nulle part dans employee.dart. Ce n'est pas bloquant pour l'app mobile (l'employé ne configure pas son propre biométrique), mais le modèle Dart est incomplet par rapport à la réalité.
15. Aucune documentation sur la rotation des secrets (tokens ZKTeco)
Les appareils ZKTeco ont un device_token permanent. Aucune procédure n'est documentée pour le renouveler en cas de compromission. À minima, documenter l'endpoint POST /devices/{id}/rotate-token dans les contrats.
16. Le CHANGELOG.md existe à la racine mais il est vide / template
L'audit suggérait de créer un CHANGELOG — il existe mais n'est qu'un squelette. Ce n'est pas critique, mais Claude Code aura besoin d'une convention de versionning dès le premier commit.
17. Le frontend web (Inertia.js) n'a pas de stratégie RTL documentée
Le produit est multilingue FR/AR/TR/EN. L'arabe est RTL. Le prompt Cursor (CU-01) ne mentionne aucune stratégie pour le support RTL dans Vue/Inertia (direction du DOM, classes Tailwind rtl:, etc.). Le Flutter a ses ARB files — le web n'a rien.
18. Aucun plan de rollback pour les migrations en production
Le CI/CD (deploy.yml) exécute php artisan migrate --force directement en production. Aucune stratégie de rollback n'est définie. Sur o2switch (sans Kubernetes), un rollback = connexion SSH manuelle. À documenter dans 19_CICD_ET_GIT.md.

📋 ORDRE DE RÉSOLUTION RECOMMANDÉ
Avant le premier commit (Sprint 0) :
#ActionFichier à modifier1Choisir manager_id ou supervisor_id et unifier partoutERD + SQL + Dart + RBAC2Ajouter 'active' dans salary_advances.status du SQL07_SCHEMA_SQL_COMPLET.sql3Corriger excel_export: true dans PlanSeeder Starter05_SEEDERS_ET_DONNEES_INITIALES.md4Ajouter evaluations + schema_isolation dans tous les plans du seederidem5Unifier employees sur status VARCHAR (supprimer is_active)ERD + SQL + Dart6Ajouter amount_remaining dans salary_advances SQL07_SCHEMA_SQL_COMPLET.sql7Documenter POST /auth/register dans les contrats API02_API_CONTRATS_COMPLET.md
Semaine 1-2 :
#Action8Créer PlanLimitMiddleware ou validation dans EmployeeService9Définir la stratégie stockage fichiers (local vs S3) dans .env.example10Documenter la stratégie RTL pour Vue/Inertia dans CU-01
Avant semaine 8 :
#Action11Spécifier TenantMigrationService (upgrade Business → Enterprise)12Définir la stratégie notifications temps réel pour le web (SSE recommandé)13Ajouter wireframes de l'onboarding guidé14Documenter la procédure de rotation des tokens ZKTeco

Résumé exécutif : votre dossier est à 85% prêt — c'est solide. Les 6 corrections bloquantes sont toutes des incohérences entre documents existants (pas des manques conceptuels) et se règlent en moins d'une journée. Le vrai risque est le bug salary_advances.status et le conflit supervisor_id/manager_id — ces deux-là feront planter le code en production si non corrigés maintenant.


Je vais approfondir l'analyse sur les points qui méritent encore une attention — notamment les prompts d'exécution et quelques zones moins explorées.Ran 10 commandsRan 10 commandsJ'ai maintenant tous les éléments pour compléter l'analyse. Voici la suite — les problèmes que j'ai trouvés lors de cette deuxième passe, plus fins mais tout aussi importants.

🔴 NOUVEAUX BUGS BLOQUANTS — Deuxième passe
19. PayrollService utilise $employee->gross_salary — champ inexistant
Dans le prompt CC-06, le PayrollService est écrit ainsi :
php$grossBase = $employee->gross_salary;
```
Mais la table `employees` n'a **aucun champ `gross_salary`** — elle a `salary_base`. Ce n'est pas la même chose conceptuellement (le brut total inclut les primes et heures sup), mais en tout cas le champ `gross_salary` n'existe ni dans l'ERD ni dans le SQL. Claude Code va générer du code avec un champ fantôme. **Corriger CC-06 pour utiliser `salary_base` et adapter la formule.**

---

### 20. Incohérence de langues : FR/AR/TR/**EN** vs Flutter qui déclare fr/ar/en/**ES**

La vision marché dit clairement **FR/AR/TR/EN** (Turc à la place de l'Espagnol — cohérent avec les marchés cibles). Mais la structure de dossiers Flutter dans JU-01 indique :
```
└── l10n/   → fichiers ARB (fr, ar, en, es)
L'espagnol es remplace le turc tr — or le Turc est un marché cible (Turquie / Persona Mehmet), et l'espagnol n'est mentionné nulle part dans la stratégie commerciale. Le seeder contient bien fr/ar/tr/en (correct). Flutter va créer les mauvais fichiers ARB. Corriger JU-01 : l10n/ → (fr, ar, tr, en).

21. fcm_tokens JSONB dans ERD vs table employee_devices dans SQL — dualité architecturale
L'ERD définit employees.fcm_tokens JSONB NOT NULL DEFAULT '[]' (tableau embarqué dans l'employé), mais le SQL crée une table séparée employee_devices avec un champ par token. Ce sont deux architectures différentes pour la même chose. Le prompt CC-02 référence employee_devices (correct — la table est plus robuste), mais le modèle Dart lit employee.fcm_tokens (champ ERD). Il faut choisir la table et supprimer fcm_tokens de l'ERD + modèle Dart — ou vice versa.
Recommandation : garder employee_devices (plus scalable, permet de gérer plusieurs appareils par employé et de voir last_seen par device).

🟠 PROBLÈMES DE FOND DANS LES PROMPTS D'EXÉCUTION
22. CC-02 référence EmployeeDevice mais l'ERD ne définit pas ce modèle Eloquent
Le prompt CC-02 liste app/Models/Tenant/EmployeeDevice.php à créer, mais aucun document de conception ne spécifie les $fillable, les relations, ni les casts de ce modèle. Claude Code doit improviser. À minima, ajouter une spec dans 02_API_CONTRATS_COMPLET.md ou dans un document modèles.

23. Le CheckSubscription middleware n'est nulle part spécifié
CC-02 liste app/Http/Middleware/CheckSubscription.php à créer. Ce middleware est critique (bloque les comptes expirés), mais son comportement exact n'est documenté que vaguement dans le TenantMiddleware. La logique de subscription_end vs today() doit être explicite :

Que se passe-t-il si subscription_end = aujourd'hui à minuit ?
La grâce est-elle de 0, 1, ou N jours ?
Les données restent-elles en lecture seule pendant la période de grâce, ou tout est bloqué ?

Sans cette spec, chaque développeur inventera sa propre règle.

24. Le SuperAdminMiddleware n'est pas du tout spécifié
Mentionné dans CC-02, jamais documenté. Le Super Admin s'authentifie depuis la table super_admins (schéma public), mais Sanctum par défaut cherche dans users. La configuration du guard Sanctum pour les super admins n'est documentée nulle part. C'est un problème d'architecture Auth non trivial à résoudre sans spec.

25. La génération du PDF de bulletin — template HTML jamais fourni
Le job GeneratePayslipPdfJob est bien spécifié (DomPDF, async, stockage), mais le template HTML du bulletin de paie n'est défini nulle part dans le dossier. DomPDF génère du PDF depuis du HTML — Claude Code doit inventer le design du bulletin. C'est un document légalement important (mention obligatoire des cotisations, du nom de l'entreprise, du numéro de bulletin, du SIRET/RC...). À ajouter : resources/views/pdf/payslip.blade.php avec les champs obligatoires documentés.

26. L'export bancaire (virement) : format non spécifié pour chaque pays
L'endpoint GET /payroll/export-bank existe dans les contrats, et payroll_export_batches.bank_format liste DZ_GENERIC, MA_CIH, FR_SEPA. Mais le contenu exact de chaque format n'est nulle part défini :

DZ_GENERIC : CSV avec quelles colonnes ? quel séparateur ? quel encodage ?
FR_SEPA : fichier XML ISO 20022 ? Schéma XSD ?
MA_CIH : format propriétaire CIH Bank ?

Sans ces specs, Claude Code inventera des formats qui ne seront pas acceptés par les banques des clients.

🟡 OUBLIS SUPPLÉMENTAIRES IDENTIFIÉS
27. Aucun document sur la gestion de l'expiration de trial
Le funnel de conversion décrit "J-14 : suspension du compte, données conservées 30 jours". Mais :

Qui exécute cette vérification ? (un cron scheduler ?)
Quelle commande Artisan ? CheckExpiredSubscriptions ?
La suspension est-elle automatique ou manuelle Super Admin ?
Que reçoit l'employé s'il se connecte sur une app dont l'entreprise est suspendue ?

Ce flow n'est documenté ni dans les règles métier, ni dans les contrats API (la réponse SUBSCRIPTION_EXPIRED existe, mais le déclencheur non), ni dans les seeders.

28. Aucune spec pour le TenantService (création de tenant)
La règle métier §9.3 liste très bien les 7 étapes de création d'entreprise. Mais le prompt CC-01 dit simplement "créer le TenantMiddleware" — il n'y a pas de prompt ni de spec pour le TenantService qui orchestre ces 7 étapes dans une transaction. Ce service sera appelé par POST /admin/companies. Sans spec, Claude Code doit deviner l'ordre exact des opérations PostgreSQL (CREATE SCHEMA → SET search_path → run migrations → seed default data).

29. La stratégie de notification temps réel côté web (Inertia) n'est toujours pas définie
Confirmé lors de la deuxième lecture : le prompt Cursor CU-01 ne mentionne aucune stratégie pour les notifications en temps réel du dashboard web (cloche en haut de page, badge compteur). L'app Flutter a FCM (documenté). Le web n'a rien. Les options sont :

Polling (GET /notifications/count toutes les 30s) — simple mais consomme
Server-Sent Events (SSE via Laravel) — bon compromis
WebSockets (Laravel Reverb ou Pusher) — le plus riche, le plus complexe

Cette décision impacte l'infrastructure (Reverb nécessite un serveur WebSocket permanent — pas trivial sur o2switch).

30. Aucune documentation sur la gestion des timezones dans les calculs de paie
Les entreprises ont une timezone (ex: Africa/Algiers, Europe/Paris, Europe/Istanbul). Mais les règles métier calculent les heures sup et les retards en comparant check_in (TIMESTAMPTZ UTC) avec schedule.start_time (TIME sans timezone). La conversion timezone n'est jamais documentée dans les règles métier. Exemple concret : un employé à Istanbul pointe à 8h00 heure locale — le serveur stocke 05:00 UTC. Si le calcul se fait en UTC sans conversion, il sera marqué en avance de 3 heures. Cette conversion doit être explicite dans les règles métier et dans le prompt CC-03.

31. La stratégie de log d'audit est incomplète : qui déclenche les audit_logs ?
La table audit_logs est bien définie dans le SQL. Mais aucun document ne précise le mécanisme de remplissage :

Est-ce un Observer Eloquent sur chaque modèle ?
Est-ce manuel dans les Controllers ?
Quels événements sont loggés ? (modification de salaire = oui, lecture de fiche = non ?)

Sans cette décision, soit tout est loggé (audit_logs explose), soit rien ne l'est (la table reste vide, inutile).

📋 PLAN D'ACTION COMPLET PRIORISÉ
Voici la liste exhaustive de tout ce qu'il faut corriger ou créer avant de coder, regroupé par urgence :
Avant le premier commit — corrections de fichiers existants :
#CorrectionFichier(s)1Choisir manager_id ou supervisor_id et unifierERD + SQL + Dart + RBAC2Ajouter 'active' dans salary_advances.statusSQL3Corriger excel_export: true Starter + ajouter evaluations/schema_isolationSeeder4Unifier status VARCHAR sur employees (supprimer is_active)ERD + SQL + Dart5Ajouter amount_remaining dans salary_advances SQLSQL6Corriger $employee->gross_salary → salary_base dans CC-06CC-067Corriger l10n/ (fr, ar, en, es) → (fr, ar, tr, en) dans JU-01JU-018Supprimer employees.fcm_tokens JSONB de l'ERD (remplacé par employee_devices)ERD9Supprimer is_active du modèle Dart employee.dart → utiliser statusDart
Avant le premier commit — documents à créer :
#À créerContenu10POST /auth/register dans API contratsPayload inscription, réponse, erreurs11Spec SuperAdminMiddleware (guard Sanctum dédié)Dans RBAC ou sécurité12Spec CheckSubscription middleware (règles de grâce)Dans règles métier13Spec cron CheckExpiredSubscriptionsDans règles métier ou CICD14Template HTML bulletin de paie (payslip.blade.php)Champs légaux obligatoires15Règle timezone dans calculs pointageDans règles métier CC-0316Stratégie Observer/audit_logsDans erreurs et logs ou RBAC
Avant semaine 5 :
#À créer17Spec TenantService (7 étapes création entreprise en transaction)18Formats export bancaire par pays (DZ_GENERIC, MA_CIH, FR_SEPA)19Décision + spec notifications temps réel web (polling/SSE/Reverb)20Spec TenantMigrationService (upgrade Business → Enterprise)21Wireframes onboarding guidé 4 étapes22Endpoint POST /devices/{id}/rotate-token dans contrats API

Bilan global : le dossier passe de 85% à environ 78% réellement prêt à coder quand on intègre ces nouvelles trouvailles. Les bugs les plus dangereux sont le gross_salary fantôme (plantera le PayrollService dès la semaine 7) et la confusion employee_devices vs fcm_tokens (plantera la gestion FCM dès la semaine 2). Ce sont deux bugs silencieux qui ne sont détectables que quand on croise les documents entre eux — exactement ce que fait cette analyse.