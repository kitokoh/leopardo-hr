# Bounded Context Deep Maturity Backlog

**Statut :** plan de travail obligatoire avant maturité production
**Architecture :** modular monolith Laravel, monorepo, migrations Laravel canoniques
**Référence de responsabilité :** `BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md`
**Objectif :** empêcher qu’un bounded context soit considéré comme terminé parce qu’il possède seulement des modèles, des routes ou une interface.

> Un bounded context mature n’est pas seulement « fonctionnel ». Il doit être **compréhensible, isolé, sécurisé, testable, observable, performant, réversible et exploitable**.

## 1. Définition commune de la profondeur

Chaque bounded context doit être audité et amélioré selon les douze dimensions suivantes. Une dimension absente constitue une lacune de maturité, même si le parcours principal fonctionne.

| Dimension | Preuve attendue |
|---|---|
| D1 — Domaine | Glossaire, invariants, agrégats, commandes, événements et cas limites documentés. |
| D2 — Données | Migrations Laravel, contraintes, FK, index, rétention, stratégie de volume et rollback. |
| D3 — Tenant | Résolution par `TenantManager`, absence de `company_id` d’autorité client, tests cross-tenant. |
| D4 — API | Routes versionnées, Requests strictes, Resources allowlistées, erreurs sûres et OpenAPI. |
| D5 — Autorisation | Policies deny-by-default, rôles, scopes, ownership et tests 401/403/404. |
| D6 — Transactions | Idempotence, verrouillage, concurrence, invariants transactionnels et outbox. |
| D7 — Asynchronisme | Jobs tenant-scoped, retry borné, DLQ, replay, timeout, backpressure et métriques. |
| D8 — Sécurité | Threat model, secrets, PII, uploads, rate limits, audit, dépendances et scans. |
| D9 — Frontends | États loading/error/empty, accessibilité, permissions UI non autoritaires, mobile/offline si pertinent. |
| D10 — Performance | Budgets p95/p99, pagination, N+1 guards, index, cache tenant et tests de charge. |
| D11 — Exploitation | Logs corrélés, dashboards, alertes, runbook, backup, restauration et rollback. |
| D12 — Produit | Parcours golden, seed pilote, documentation utilisateur, support, métriques et go/no-go. |

Un contexte ne peut recevoir le statut `READY_FOR_PRODUCTION` que lorsque les douze dimensions possèdent une preuve. `PARTIAL` signifie qu’un parcours fonctionne mais que des preuves manquent. `BLOCKED` signifie qu’une dépendance ou un risque empêche la poursuite.

## 2. Règles de propriété des agents

L’agent propriétaire d’un contexte est responsable de la profondeur de celui-ci, mais il ne possède pas les contextes partagés. Les agents 01, 02, 03, 13 et 14 valident les contrats de plateforme ; les agents métiers valident leurs invariants ; l’agent 22 valide les read models ; les agents 19 et 20 valident devices et documents.

Aucun agent ne peut déclarer son contexte mature en se fondant sur des tests unitaires uniquement. La preuve doit couvrir les appels HTTP, les jobs, les migrations, les permissions, les données tenant, les scénarios UI et l’exploitation.

## 3. Fiches de profondeur par bounded context

### BC-01 — Platform Core

**Agent propriétaire : 01.** Platform Core possède le catalogue des modules, provisioning, configuration globale, feature flags et administration. Il doit distinguer les opérations plateforme du CRM commercial Leopardo et ne doit jamais exposer par erreur une route tenant.

Les travaux de profondeur sont : contrat de catalogue signé, activation idempotente, version de solution, entitlements, kill switch, audit des changements, compensation du provisioning, permissions d’opérateur plateforme, compatibilité des versions et tests de non-régression du CRM commercial.

La sortie exige une démonstration `catalogue → plan → activation → configuration → audit → désactivation contrôlée`, avec échec simulé et reprise. Les modules sont refusés par défaut et aucune configuration JSON ne doit devenir une autorité d’accès.

### BC-02 — Tenant & Isolation

**Agent propriétaire : 02.** Ce contexte est le gardien absolu de `TenantManager`, `current_company`, `search_path`, `withinTenant()` et des jobs tenant-scoped. Il ne doit pas contenir de règles métier CRM, FuelStation ou EduManager.

Les travaux de profondeur sont : matrice de résolutions tenant par HTTP/job/event/console/webhook, restauration du contexte en `finally`, FK et index tenant, tests d’énumération, cache tenant, export tenant, migrations shared/tenant, rotation de contexte et tests de concurrence.

La sortie exige qu’un utilisateur, job, event, cache key, upload, export ou webhook d’un tenant A ne puisse ni lire ni écrire une donnée du tenant B, y compris avec des IDs connus ou des URLs modifiées.

### BC-03 — Identity & Access

**Agent propriétaire : 03.** Identity gère utilisateurs, memberships, invitations, sessions, rôles et permissions de base. Il ne doit pas décider des règles métier propres à Payroll, FuelStation ou EduManager.

Les travaux de profondeur sont : MFA ou politique de session selon exposition, expiration/revocation, invitations idempotentes, séparation platform/client, ownership multi-stations, guardians, appareils, service accounts et audit des changements de permission.

La sortie exige une matrice allow/deny versionnée et des tests pour rôle supprimé, membership révoqué, session expirée, changement de tenant, invitation rejouée et escalade horizontale.

### BC-04 — HR

**Agent propriétaire : 04.** HR possède employés, contrats, départs, documents RH et cycle de vie. Il expose des contrats stables à Attendance, Payroll et aux verticales.

Les travaux de profondeur sont : historique des contrats, dates d’effet, confidentialité, classification PII, consentement documents, employee identifier stable, import contrôlé, archivage, événements `EmployeeHired/Changed/Departed` et compatibility consumers.

La sortie exige qu’un changement de contrat ne réécrive pas un calcul Payroll historique, qu’un départ soit auditable, qu’un manager ne voie que son périmètre et que les exports soient bornés.

### BC-05 — Workforce / Attendance / Planning

**Agent propriétaire : 05.** Workforce possède présence, planning, shifts, affectations et modes de pointage. Les règles de paie consomment ses résultats validés, mais ne doivent pas être codées ici.

Les travaux de profondeur sont : timezone, DST, chevauchements, corrections versionnées, géolocalisation, offline, devices, approbations, présence calculée et contrats de shift. Pour FuelStation, l’affectation temporelle de pompe reste dans BC-15 mais s’appuie sur les shifts.

La sortie exige des scénarios de journée normale, nuit, changement de timezone, hors ligne, correction manager et double pointage, avec absence de doublon et audit complet.

### BC-06 — Leave & Absence

**Agent propriétaire : 06.** Leave gère demandes, soldes, politiques, justificatifs et validations. Il ne modifie pas directement les contrats RH ou les périodes Payroll.

Les travaux de profondeur sont : calcul de solde versionné, conflits, jours fériés, délégation d’approbation, justificatifs protégés, annulation, rétroactivité, notifications et contrat vers Attendance/Payroll.

La sortie exige qu’une demande ne soit ni approuvée deux fois ni appliquée à un mauvais tenant, qu’un solde soit explicable et qu’une correction historique soit auditée.

### BC-07 — Payroll

**Agent propriétaire : 07.** Payroll est un contexte financier critique. Il possède périodes, runs, règles, snapshots, lignes, bulletins et exports. Toute règle doit être déterministe et versionnée.

Les travaux de profondeur sont : golden tests par pays, snapshots immuables, arrondis, rétroactivité, clôture, permissions de validation, idempotence des jobs, exports bancaires, conservation légale et contrat Accounting.

La sortie exige des résultats reproductibles après rejouabilité, des tests de concurrence, aucun montant modifié silencieusement après clôture et une procédure de correction auditable.

### BC-08 — Accounting & Finance

**Agent propriétaire : 08.** Accounting possède plan comptable, exercices, journaux, écritures, lettrage, FEC et états. Il reçoit des contrats validés de Payroll, Expense et verticales.

Les travaux de profondeur sont : équilibre débit/crédit, périodes verrouillées, idempotence de posting, références source, audit, exports, clôture, permissions, reprise d’échec et performance des grands journaux.

La sortie exige qu’aucun événement dupliqué ne crée deux écritures, qu’une période clôturée soit protégée et qu’un état financier soit reproductible à partir des écritures.

### BC-09 — Expense

**Agent propriétaire : 09.** Expense possède dépenses, avances, prêts, remboursements et avantages. Il ne doit pas créer des écritures Accounting directement sans contrat.

Les travaux de profondeur sont : politique de dépense, validation multi-niveaux, pièces jointes, devise, détection doublons, limites, remboursement, confidentialité et posting idempotent.

La sortie exige que chaque montant ait une devise, un état et une preuve ; qu’une dépense d’un autre tenant soit invisible ; et qu’un remboursement ne puisse être exécuté deux fois.

### BC-10 — Recruitment

**Agent propriétaire : 10.** Recruitment possède postes, candidats, candidatures, entretiens et décisions. Il doit rester distinct du CRM commercial plateforme et du CRM client.

Les travaux de profondeur sont : consentement candidat, rétention, anonymisation, ownership recruiter, pièces jointes, anti-énumération, pipeline, conversion vers HR et audit des décisions.

La sortie exige une conversion candidat → employé explicite, idempotente et contrôlée ; aucun CV ne doit être exposé par une URL devinable ou conservé au-delà de la politique.

### BC-11 — CRM client

**Agent propriétaire : 11.** CRM possède accounts, contacts, leads, opportunités, activités, tâches, pipelines et conversions dans l’espace client. Il ne lit jamais le CRM commercial Leopardo.

Les travaux de profondeur sont : déduplication tenant, ownership et équipes, champs personnalisés bornés, timeline, import/export, recherche PII, consentements, contrats Marketing et conversion lead → account/contact.

La sortie exige qu’un tenant ne voie que ses propres comptes, qu’une conversion soit idempotente, qu’un contact supprimé/anonymisé respecte les relations et que les exports soient autorisés et audités.

### BC-12 — Marketing & Growth

**Agent propriétaire : 12.** Growth gère segmentation, campagnes, templates, audiences et consentements marketing tenant-scoped. Le CRM commercial Leopardo reste séparé dans Platform/Marketing.

Les travaux de profondeur sont : opt-in/opt-out par canal et finalité, suppression globale, frequency caps, templates, prévisualisation, planification, délivrabilité, attribution et événements d’audience sans PII excessive.

La sortie exige qu’aucun message ne parte sans consentement valide, qu’un opt-out soit effectif dans les files déjà planifiées et qu’un tenant ne puisse pas cibler un autre tenant.

### BC-13 — Communications

**Agent propriétaire : 13.** Communications possède notifications, préférences, email, SMS, WhatsApp et état de délivrabilité. Les domaines métiers ne stockent pas de tokens provider.

Les travaux de profondeur sont : adaptateurs, templates versionnés, secrets externalisés, rate limits, retries, fenêtres WhatsApp, webhooks signés, redaction, opt-out et fallback.

La sortie exige l’absence de perte et de doublon métier sous retry, l’isolation des connexions par tenant, la non-divulgation de PII et un runbook provider.

### BC-14 — Integration Runtime

**Agent propriétaire : 14.** Integration Runtime possède inbox, outbox, queues, webhooks, déduplication, replay et backpressure. Il orchestre, mais ne contient pas les règles métier des consommateurs.

Les travaux de profondeur sont : transaction outbox, inbox unique par événement, clés idempotentes, leases, retries classifiés, DLQ, poison messages, replay borné, quotas, métriques et shutdown propre.

La sortie exige un test de pic avec zéro perte, zéro doublon métier, récupération après crash worker et preuve que le contexte tenant est restauré pour chaque job.

### BC-15 — FuelStation

**Agent propriétaire : 15.** FuelStation possède tenant → stations → pompes → compteurs → sessions pompistes → photos/OCR → litres → montant attendu → dépôt réel → écart → validation manager.

Les travaux de profondeur sont : affectations temporelles sans chevauchement, mobile pompiste uniquement, photo ouverture/fermeture, OCR avec confidence, relevé manuel contrôlé, compteur décroissant/rollover, prix par période, caisse, stock, offline et bilan multi-stations.

La sortie exige le scénario réel de bout en bout avec plusieurs stations, plusieurs pompistes successifs sur une pompe, plusieurs pompes par pompiste, perte réseau, photo illisible, anomalie financière et approbation manager.

### BC-16 — EduManager

**Agent propriétaire : 16.** EduManager possède campus, élèves, guardians, classes, matières, présence, évaluations, notes, bulletins et admissions.

Les travaux de profondeur sont : séparation élève/contact/employee, confidentialité guardian, notes versionnées, publication atomique, rétention, portail guardian, conflits de classe, présence, imports et notifications.

La sortie exige qu’un guardian voie uniquement ses enfants, qu’une note publiée soit immuable/versionnée, qu’un bulletin soit reproductible et qu’un export respecte les droits et la rétention.

### BC-17 — Retail/POS

**Agent propriétaire : 17.** Retail possède produits, magasins, caisses, tickets, stocks, retours et synchronisation POS. Il ne transforme pas CRM en caisse.

Les travaux de profondeur sont : offline transactionnel, idempotence ticket, clôture caisse, stock, prix, taxes, retours, appareils, impressions, Accounting et conflits de synchronisation.

La sortie exige une vente offline rejouée sans double débit ni double ticket, une clôture auditable et l’impossibilité de vendre depuis un magasin non autorisé.

### BC-18 — Field Service/Fleet

**Agent propriétaire : 18.** Field/Fleet possède véhicules, équipements, interventions, maintenance et opérations de service. Les données RH et clients sont consommées par contrat.

Les travaux de profondeur sont : assignment technicien, disponibilité, géolocalisation, statut intervention, pièces, coûts, SLA, maintenance préventive, preuves photo et contrat CRM/Accounting.

La sortie exige une intervention complète, un changement d’assignation audité, un mode réseau instable contrôlé et des coûts réconciliables.

### BC-19 — Devices/Cameras/Edge

**Agent propriétaire : 19.** Device possède appareils, kiosques, caméras, biométrie, clés, firmware et synchronisation edge.

Les travaux de profondeur sont : enrollment sécurisé, rotation secrets, révocation, attestation selon capacité, chiffrement, command replay, payload bounds, stockage local, purge et audit des opérations device.

La sortie exige qu’un appareil révoqué ne puisse plus synchroniser, qu’un replay ne réexécute pas une commande et que les données biométriques suivent leur rétention stricte.

### BC-20 — Documents & Evidence

**Agent propriétaire : 20.** Documents possède assets, pièces, preuves, scans, signatures, exports et retention. Il est transverse mais ne doit pas devenir une table polymorphe sans règles.

Les travaux de profondeur sont : MIME allowlist, taille, antivirus, chiffrement, URLs temporaires, ownership, versionnement, retention, suppression, redaction et audit.

La sortie exige qu’un fichier d’un tenant soit inaccessible à un autre, qu’un fichier malveillant soit bloqué, qu’une URL expire et qu’une suppression respecte l’audit nécessaire.

### BC-21 — Billing & Subscription

**Agent propriétaire : 21.** Billing possède plans, souscriptions, entitlements, facturation et paiements de plateforme. Il est distinct de Accounting tenant et du CRM client.

Les travaux de profondeur sont : webhooks provider idempotents, états de souscription, prorata, échec de paiement, grace period, activation feature flag, droits de support et réconciliation.

La sortie exige qu’un webhook rejoué ne double pas une souscription, qu’un paiement ne débloque pas un module hors entitlement et qu’un tenant en défaut soit traité selon une politique explicite.

### BC-22 — Analytics & Reporting

**Agent propriétaire : 22.** Analytics possède read models, agrégats, exports et data quality. Il ne doit pas ralentir les transactions ni lire des tables tenant sans scope.

Les travaux de profondeur sont : événements sources, fraîcheur, recalcul, correction, snapshots, budgets p95, export asynchrone, masquage PII, permissions et lineage.

La sortie exige que deux recalculs produisent le même résultat, que les dashboards n’utilisent pas de jointures profondes transactionnelles et qu’un manager ne voie que les stations/campus autorisés.

### BC-23 — AI Assistive Services

**Agent propriétaire : 23.** AI possède registry, OCR, suggestions, prédictions et assistants. L’IA assiste ; elle ne devient pas une autorité métier implicite.

Les travaux de profondeur sont : allowlist d’outils, validation schema, redaction PII, consentement, limites de coût/volume, traçabilité modèle/version, fallback humain, confidence et rétention des prompts/résultats.

La sortie exige qu’un OCR faible demande confirmation, qu’une suggestion ne modifie pas Payroll/Accounting/notes sans confirmation, qu’un prompt tenant A ne soit jamais réutilisé pour B et que les erreurs provider soient récupérables.

## 4. Dépendances de profondeur

```text
BC-02 Tenant + BC-03 Identity
              ↓
BC-01 Platform + BC-14 Integration + BC-13 Communications
              ↓
BC-04 HR + BC-05 Workforce + BC-06 Leave
              ↓
BC-07 Payroll + BC-08 Accounting + BC-09 Expense
              ↓
BC-11 CRM + BC-12 Growth
              ↓
BC-15 FuelStation + BC-16 EduManager + BC-17 Retail + BC-18 Field
              ↓
BC-19 Devices + BC-20 Documents + BC-22 Analytics + BC-23 AI
```

`BC-21 Billing` peut progresser en parallèle après validation de Platform, Identity et Integration, mais aucune activation d’entitlement ne doit contourner les Policies ou feature flags.

## 5. Statuts de maturité

| Statut | Signification |
|---|---|
| `DISCOVERED` | Le contexte existe ou est identifié ; preuves insuffisantes. |
| `MAPPED` | Domaine, fichiers, routes, données et dépendances cartographiés. |
| `CONTRACTED` | Frontières, événements, API et ownership ratifiés. |
| `HARDENING` | Tests, sécurité, performance et exploitation en cours. |
| `PILOT_READY` | Golden journey, rollback et runbook validés sur données synthétiques. |
| `PRODUCTION_READY` | Douze dimensions prouvées, CI verte et go/no-go signé. |
| `BLOCKED` | Dépendance ou risque critique non résolu. |

## 6. Ordre concret pour les agents

La première vague prend `BC-02`, `BC-03`, `BC-01`, `BC-14` et traite les contrats qui empêchent les fuites et les duplications. La deuxième vague prend `BC-04`, `BC-05`, `BC-06`, `BC-07`, `BC-08`, `BC-11` et `BC-13` afin de rendre les modules transversaux fiables. La troisième vague prend `BC-15` et `BC-16` comme solutions pilotes ; les autres verticales restent en conception jusqu’à preuve de capacité.

Chaque agent doit créer ou compléter les issues de son contexte sous la forme `BC-XX-D01` à `BC-XX-D12`, une par dimension lorsque la profondeur justifie un découpage séparé. Une tâche peut regrouper plusieurs dimensions uniquement si elle reste vérifiable, courte et sans mélange de responsabilité.

## 7. Gate globale de maturité

Le projet ne sera pas déclaré mature parce que les 23 agents ont terminé leurs tickets. Il sera mature lorsque les preuves suivantes seront réunies :

```text
23 bounded contexts cartographiés
+ contrats inter-contextes versionnés
+ zéro fuite cross-tenant démontrée
+ migrations Laravel reproductibles
+ CI ciblée et verte
+ golden journeys Platform/CRM/Payroll/FuelStation/EduManager
+ restore/rollback testés
+ observabilité active
+ budget de performance respecté
+ sécurité et RGPD validés
+ pilotes métier signés
```

Le coordinateur doit maintenir le registre de statut et refuser les changements qui avancent une fonctionnalité verticale alors qu’un contrat plateforme critique est `BLOCKED`.
