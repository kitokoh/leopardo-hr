# Plan de remédiation OpenAPI par bounded context

**Dépôt :** `kitokoh/leopardo-hr`
**Référence :** `api/openapi.yaml`
**Base auditée :** `main` — `9c78677cf22f393f24dd3435193643658078e9cd`
**Constat initial :** contrat syntaxiquement valide, mais **1 473 avertissements Redocly**.
**Statut :** plan d’exécution proposé — aucune modification du contrat incluse dans ce document.

## 1. Objectif et périmètre

L’objectif n’est pas de masquer les avertissements par une désactivation de règles. Il est de rendre chaque opération OpenAPI exploitable comme contrat client : opération identifiée, erreurs prévisibles, sécurité explicite, paramètres cohérents, rattachement à un bounded context et couverture vérifiable par rapport aux routes Laravel.

Le plan couvre les avertissements observés dans le lint Redocly, principalement :

- opérations sans `operationId` ;
- opérations sans réponse `4XX` ;
- chemins ambigus ou susceptibles de collision ;
- dérive route Laravel ↔ contrat OpenAPI ;
- incohérences de tags, de sécurité, de pagination et de schémas d’erreur révélées pendant la remédiation.

Le plan ne crée pas de microservices et ne déplace pas les routes. Leopardo reste un monolithe modulaire Laravel avec des frontières DDD, des contrats explicites et des applications clientes spécialisées.

## 2. Règles non négociables

1. **Le propriétaire du BC est propriétaire de ses routes et de sa documentation.** Une PR ne corrige pas silencieusement les opérations d’un autre contexte.
2. **Aucun avertissement ne doit être supprimé par configuration globale.** Une exception doit être locale, justifiée, datée et associée à une issue.
3. **Les noms ne prouvent pas l’autorité.** La sécurité effective reste portée par Laravel, les Policies, le `TenantManager` et les tests cross-tenant.
4. **Le CRM commercial et le CRM client restent séparés.** Les tags, `operationId`, routes, schémas et serveurs ne doivent pas mélanger `BC-PLATFORM` et `BC-CRM`.
5. **Les artefacts générés restent non suivis par Git.** La CI doit continuer à vérifier leur génération sans les committer.
6. **Toute nouvelle route doit être documentée dans la même PR**, sauf exception allowlistée avec justification.
7. **Aucun merge forcé.** Chaque lot doit obtenir les checks requis au vert avant intégration.

## 3. Convention cible

### 3.1 `operationId`

Chaque opération reçoit un identifiant stable, unique et orienté use case :

```text
<bc>.<ressource>.<action>
```

Exemples :

```text
attendance.sessions.list
attendance.sessions.start
fuelStation.pumpReadings.close
crm.opportunities.convert
platform.tenants.provision
```

Règles :

- anglais technique stable, sans version dans le nom ;
- pas de nom de contrôleur Laravel ;
- pas de doublon entre méthodes ou versions ;
- renommage d’un `operationId` = changement de contrat documenté et vérifié dans les SDK.

### 3.2 Réponses d’erreur

Le contrat doit réutiliser les réponses communes lorsqu’elles existent :

- `400` : requête mal formée ;
- `401` : authentification absente ou invalide ;
- `403` : Policy ou rôle insuffisant ;
- `404` : ressource absente dans le périmètre autorisé ;
- `409` : conflit d’état/idempotence ;
- `422` : validation métier ou formulaire ;
- `429` : throttling ;
- `5XX` uniquement quand le cas est pertinent et documenté.

Les endpoints publics de santé, de téléchargement ou de webhook peuvent avoir un profil différent, mais l’exception doit être explicite et testée.

### 3.3 Sécurité et tenant

Chaque opération authentifiée doit déclarer la sécurité appropriée et documenter l’isolation tenant dans sa description ou ses réponses. Les routes publiques doivent utiliser `security: []` explicitement. Aucun endpoint ne doit accepter `tenant_id` ou `company_id` comme preuve d’autorité.

### 3.4 Tags

Les tags doivent correspondre au vocabulaire métier du registre BC. Un tag ne doit pas être créé uniquement pour refléter le nom d’un contrôleur. Les tags transverses (`Auth`, `Health`, `Webhooks`) sont autorisés lorsqu’ils représentent réellement une surface partagée.

## 4. Séquençage global

### Vague 0 — inventaire et garde-fous, sans changement métier

**Propriétaires :** Agent 14 (`BC-INTEGRATION`), Agent 01 (`BC-PLATFORM`), Agent 02 (`BC-TENANT`).

Livrables :

- script machine-readable listant chaque opération : chemin, méthode, tag, BC propriétaire, `operationId`, sécurité, réponses, route PHP source ;
- détection des doublons d’`operationId` ;
- détection des opérations sans tag, sans sécurité explicite ou sans erreur 4xx ;
- mapping officiel `tag → BC` versionné ;
- rapport JSON par PR pour ne bloquer que les régressions nouvelles ;
- baseline figée des 1 473 avertissements, avec compteur par règle et par BC.

**Critère de sortie :** toute nouvelle opération sans `operationId`, sans owner ou hors mapping échoue ; les avertissements historiques restent visibles mais sont répartis en lots.

### Vague 1 — fondations critiques

Ordre recommandé : `BC-TENANT`, `BC-IDENTITY`, `BC-PLATFORM`, `BC-INTEGRATION`.

Objectif : stabiliser sécurité, provisioning, authentification, webhooks, idempotence, erreurs communes et conventions avant les BC métier.

### Vague 2 — cœur RH et finance

Ordre recommandé : `BC-HR`, `BC-WORKFORCE`, `BC-LEAVE`, `BC-PAYROLL`, `BC-ACCOUNTING`, `BC-EXPENSE`.

Objectif : documenter les contrats à fort risque de données personnelles et financières, avec tests de permissions et d’isolation.

### Vague 3 — expérience client et communication

Ordre recommandé : `BC-CRM`, `BC-GROWTH`, `BC-COMMS`, `BC-RECRUITMENT`.

Objectif : préserver la distinction CRM commercial/CRM client et documenter les intégrations asynchrones.

### Vague 4 — solutions opérationnelles et devices

Ordre recommandé : `BC-FUEL`, `BC-EDU`, `BC-DEVICE`, `BC-FIELD`, `BC-RETAIL`.

Objectif : traiter les parcours mobiles, photos/OCR, edge, POS, équipements et opérations terrain sans exposer de données inter-contextes.

### Vague 5 — données, documents, facturation et IA

Ordre recommandé : `BC-DOCUMENTS`, `BC-BILLING`, `BC-ANALYTICS`, `BC-AI`.

Objectif : finaliser les surfaces de fichiers, entitlements, exports, read models, OCR et assistants bornés.

## 5. Plan par bounded context

### BC-01 — PLATFORM

**Tags probables :** `Platform`, `Onboarding`, `Readiness`, `Dashboard` lorsqu’ils concernent l’administration de la plateforme.

Actions :

- attribuer les `operationId` de catalogue de modules, provisioning, onboarding et feature flags ;
- séparer explicitement les routes admin plateforme des routes tenant ;
- documenter les commandes de provisioning, leurs conflits `409` et leur idempotence ;
- documenter les états de readiness et les opérations de migration ;
- vérifier que le CRM commercial plateforme n’est jamais tagué `CRM` client.

**Acceptation :** tests de Policy super-admin/tenant-admin, test de double provisioning, réponses `409/422`, aucun accès à une ressource tenant par simple identifiant fourni par le client.

### BC-02 — TENANT

**Tags probables :** `Platform`, `Onboarding`, `Readiness`.

Actions :

- documenter le contexte actif, changement de tenant, activation et suspension ;
- expliciter les erreurs d’isolation (`401/403/404`) sans révéler l’existence d’un autre tenant ;
- documenter les règles de pagination et les limites de recherche ;
- vérifier les opérations appelées depuis jobs, événements et webhooks.

**Acceptation :** tests cross-tenant pour chaque opération, aucun `tenant_id` d’entrée accepté comme autorité, `operationId` distincts pour l’admin plateforme et l’espace client.

### BC-03 — IDENTITY

**Tags :** `Auth`, `Invitations`, `Me`, `Privacy`.

Actions :

- attribuer les `operationId` de login, refresh, logout, profil, sessions et invitations ;
- documenter MFA, révocation, expiration et throttling ;
- distinguer `401`, `403`, `404` et `422` ;
- documenter les scopes et rôles sans exposer de secrets ni tokens d’exemple réels.

**Acceptation :** tests d’expiration/révocation, brute-force/throttle, invitation inter-tenant refusée, réponses d’erreur standardisées.

### BC-04 — HR

**Tags :** `Employees`, `HR Referentials`, `Documents` si la route appartient au cycle de vie RH.

Actions :

- documenter employés, départements, contrats, affectations et sorties ;
- distinguer lecture de profil, modification et export ;
- déclarer les contraintes de confidentialité et les Policies ;
- harmoniser les paramètres `{employee}` et les schémas de pagination.

**Acceptation :** tests employé/manager/admin, non-divulgation cross-tenant, `404` uniforme pour une ressource hors périmètre, exemples sans PII réelle.

### BC-05 — WORKFORCE

**Tags :** `Attendance`, `Schedules`, `Vehicle Tracking`, `Geo`.

Actions :

- documenter présence, shifts, planning, géolocalisation et modes de pointage ;
- stabiliser les `operationId` autour des use cases (`start`, `close`, `approve`, `reject`) ;
- documenter conflits de chevauchement `409`, validation `422` et règles de temps ;
- séparer les lectures de dashboard des commandes métier.

**Acceptation :** tests de fuseau horaire, idempotence des pointages, validation des transitions d’état et contrôle d’accès manager/employé.

### BC-06 — LEAVE

**Tags :** `Absence`, `Leave` si présent.

Actions :

- documenter demandes, approbations, soldes, justificatifs et annulations ;
- associer les réponses de conflit aux périodes qui se chevauchent ;
- lier les fichiers justificatifs à `BC-DOCUMENTS` par contrat, jamais par table directe.

**Acceptation :** tests de solde, chevauchement, séparation demandeur/approbateur et isolation tenant.

### BC-07 — PAYROLL

**Tags :** `Payrolls`, `Employees`, `Me` pour les bulletins personnels.

Actions :

- documenter périodes, calcul, validation, snapshot, bulletin et export ;
- distinguer aperçu recalculable et snapshot figé ;
- ajouter `409` pour période verrouillée et `422` pour données incomplètes ;
- documenter les frontières avec HR, Attendance et Accounting.

**Acceptation :** tests d’invariance des montants, période verrouillée, accès employé à son seul bulletin, aucune mutation d’un snapshot validé sans procédure explicite.

### BC-08 — ACCOUNTING

**Tags :** `Accounting`, `Payments`, `Documents` financier.

Actions :

- documenter journaux, écritures, paiements, rapprochements, documents et exports ;
- standardiser les erreurs de période clôturée, doublon d’idempotence et devise ;
- documenter les webhooks de paiement via `BC-INTEGRATION`.

**Acceptation :** tests de partie double, clôture, idempotency key, Policy finance et non-mutation d’une écriture postée.

### BC-09 — EXPENSE

**Tags :** `Expenses`, `Benefits` ou `Accounting` selon la surface exposée.

Actions :

- documenter dépenses, avances, prêts, remboursements et validation ;
- préciser les pièces justificatives et le contrat d’écriture Finance ;
- documenter `409` en cas de double soumission.

**Acceptation :** test employé/manager/finance, montant et devise validés, aucun accès à la ledger interne sans contrat.

### BC-10 — RECRUITMENT

**Tags :** `Recruitment`, `Candidates` si présents.

Actions :

- attribuer les `operationId` aux postes, candidatures, entretiens et décisions ;
- séparer candidat externe et employé créé après embauche ;
- documenter upload et rétention via `BC-DOCUMENTS`.

**Acceptation :** tests de confidentialité candidat, transitions de candidature et consentement d’export.

### BC-11 — CRM

**Tags :** `CRM`, `Customers`, `Contacts`, `Opportunities` si présents.

Actions :

- traiter exclusivement le CRM client tenant ;
- utiliser des `operationId` `crm.*` et des routes de l’espace client ;
- ne jamais documenter les tables ou commandes du CRM commercial plateforme dans ce lot ;
- documenter contacts, comptes, leads, opportunités, activités, tâches et conversion ;
- déclarer les liens vers Growth et Communications par événements/commandes versionnés.

**Acceptation :** test qu’un tenant ne voit que son CRM, test d’absence d’accès aux leads commerciaux plateforme, conversion idempotente et audit des changements.

### BC-12 — GROWTH

**Tags :** `Marketing`, `Growth`, `Campaigns`.

Actions :

- séparer marketing client et acquisition commerciale plateforme ;
- documenter segments, campagnes, consentements, templates et métriques ;
- documenter les transitions asynchrones et les erreurs provider sans exposer les secrets.

**Acceptation :** consentement vérifié, opt-out idempotent, isolation des audiences tenant et distinction claire avec `BC-CRM`.

### BC-13 — COMMS

**Tags :** `Notifications`, `Webhooks`.

Actions :

- documenter préférences, templates, inbox/outbox, WhatsApp/SMS/email et statuts de livraison ;
- distinguer commande d’envoi acceptée `202` et livraison effective ;
- documenter signature webhook, replay, doublon et indisponibilité provider.

**Acceptation :** test de signature, idempotence, DLQ/replay, aucun token provider dans les exemples ou réponses.

### BC-14 — INTEGRATION

**Tags :** `Webhooks`, `Readiness` pour les composants d’intégration.

Actions :

- créer le vocabulaire contractuel commun pour webhook, inbox, outbox, replay et idempotency key ;
- documenter `202`, `400`, `401`, `409`, `429` selon le type de webhook ;
- ajouter correlation ID et version d’événement dans les exemples ;
- vérifier que les opérations générées ne deviennent pas des routes métier orphelines.

**Acceptation :** test replay, duplication, ordre non garanti, timeout provider et traçabilité corrélée.

### BC-15 — FUEL

**Tags :** `FuelStation`, `Stations`, `Pumps` si présents.

Actions :

- documenter station → pompe → compteur → session pompiste → photo ouverture/fermeture → OCR → litres → dépôt → écart → clôture ;
- distinguer lecture manuelle, photo et valeur OCR ;
- documenter validation manager, corrections auditées et plusieurs pompistes sur une même journée ;
- référencer `BC-AI`, `BC-DEVICE`, `BC-DOCUMENTS` et `BC-ANALYTICS` par contrats.

**Acceptation :** test de monotonicité des compteurs, double clôture, écart financier, OCR incertain, attribution temporelle pompiste/pompe et isolation tenant.

### BC-16 — EDU

**Tags :** `Training`, `Education`, `Students`, `Attendance` lorsque la route est explicitement EduManager.

Actions :

- désambiguïser les présences EduManager par tag et préfixe de route ;
- documenter établissements, classes, élèves, guardians, notes, bulletins et admissions ;
- ne pas réutiliser les `operationId` du Workforce Attendance sans préfixe métier.

**Acceptation :** Policy guardian/enseignant/admin, verrouillage des notes publiées, isolation établissement et export contrôlé.

### BC-17 — RETAIL

**Tags :** `Retail`, `Catalog`, `Payments` si présents.

Actions :

- documenter produits, magasins, caisse, tickets, stocks et synchronisation POS ;
- distinguer catalogue partagé autorisé et stock tenant ;
- documenter offline sync, conflit et idempotence.

**Acceptation :** test de double vente, reprise offline, stock négatif interdit ou explicitement autorisé, rapprochement financier contractuel.

### BC-18 — FIELD

**Tags :** `Fleet Management`, `Vehicle Tracking`, `Projects`, `Tasks`.

Actions :

- documenter véhicules, équipements, interventions, maintenance et tâches terrain ;
- séparer position technique de l’événement métier ;
- ajouter erreurs de conflit d’affectation et d’équipement indisponible.

**Acceptation :** permissions opérateur/manager, données temporelles cohérentes et aucune fuite de localisation hors tenant.

### BC-19 — DEVICE

**Tags :** `Kiosks`, `Cameras`, `Biometrics`, `Vehicle Tracking` pour les devices concernés.

Actions :

- documenter enrôlement, heartbeat, credentials, synchronisation edge, kiosques, caméra et biométrie ;
- distinguer identité device et identité utilisateur ;
- ne jamais renvoyer de secret brut ;
- expliciter les limites offline et le replay des événements.

**Acceptation :** révocation device, rotation credential, replay idempotent, test de device d’un autre tenant et absence de biométrie dans les logs/exemples.

### BC-20 — DOCUMENTS

**Tags :** `Documents`, `Privacy`, `Payrolls` lorsqu’un document est exposé.

Actions :

- documenter upload, téléchargement signé, métadonnées, scan antivirus, rétention et suppression ;
- standardiser `413`, `415`, `422`, `404` ;
- référencer le propriétaire métier par identifiant de contrat et non par accès inter-table.

**Acceptation :** type/taille contrôlés, URL temporaire, tenant isolation, antivirus et audit de téléchargement.

### BC-21 — BILLING

**Tags :** `Billing`, `Payments`, `Platform` pour entitlements.

Actions :

- documenter plans, activation, entitlements, factures et état d’abonnement ;
- séparer paiement commercial plateforme et paiement métier tenant ;
- documenter webhooks de fournisseur et idempotence.

**Acceptation :** test d’entitlement, downgrade/upgrade, webhook dupliqué, aucun secret de paiement dans les réponses.

### BC-22 — ANALYTICS

**Tags :** `Dashboard`, `Reporting`, `Readiness`.

Actions :

- documenter read models, filtres, période, timezone, exports asynchrones et statut de génération ;
- préciser la fraîcheur des données et les éventuels retards ;
- empêcher qu’un dashboard lise directement des tables transactionnelles d’un autre BC.

**Acceptation :** test de tenant, cohérence de période, export `202` avec suivi, budget de réponse et métrique de fraîcheur.

### BC-23 — AI

**Tags :** `Biometrics`, `Cameras`, `Documents` pour OCR, `AI` si présent.

Actions :

- documenter OCR, suggestions, scores de confiance et revue humaine ;
- distinguer traitement demandé, traitement en cours, résultat et rejet ;
- documenter absence de décision automatique non confirmée ;
- ne jamais exposer prompt interne, clé provider ou donnée sensible inutile.

**Acceptation :** test de confiance basse, timeout/retry, suppression des artefacts, audit de la validation humaine et traçabilité du modèle/provider.

## 6. Découpage des PRs

Chaque PR doit rester limitée à un BC et à un lot fonctionnel :

1. **PR-OPENAPI-000** — inventaire, mapping tags/BC, générateur de rapport et baseline ;
2. **PR-OPENAPI-001** — conventions communes, réponses réutilisables et règles de lint ;
3. **PR-OPENAPI-010 à 013** — BC-02, BC-03, BC-01, BC-14 ;
4. **PR-OPENAPI-020 à 025** — BC-04 à BC-09 ;
5. **PR-OPENAPI-030 à 033** — BC-10 à BC-13 ;
6. **PR-OPENAPI-040 à 044** — BC-15 à BC-19 ;
7. **PR-OPENAPI-050 à 053** — BC-20 à BC-23 ;
8. **PR-OPENAPI-099** — suppression de la baseline restante et activation du mode strict.

Une PR peut contenir plusieurs fichiers OpenAPI uniquement si ces fichiers appartiennent au même BC et si les tests de couverture associés sont inclus.

## 7. CI progressive

### Étape A — observabilité

La CI publie :

- nombre total d’avertissements ;
- compteurs par règle ;
- compteurs par BC ;
- opérations ajoutées/modifiées sans `operationId` ;
- drift route → OpenAPI et OpenAPI → route.

### Étape B — non-régression

La PR échoue si elle :

- augmente le nombre global d’avertissements ;
- ajoute un doublon d’`operationId` ;
- ajoute une route non documentée sans justification ;
- supprime une réponse d’erreur existante ;
- déplace une route entre BC sans mise à jour du registre et de CODEOWNERS.

### Étape C — lots stricts

Après remédiation complète d’un BC, son périmètre passe en strict :

- zéro opération sans `operationId` ;
- zéro erreur de réponse obligatoire ;
- zéro ambiguïté de chemin dans son périmètre ;
- tests de routes et tests de permissions verts.

### Étape D — contrat global strict

Quand les 23 périmètres sont propres, `npx @redocly/cli lint` devient bloquant sans baseline. La baseline et les allowlists historiques sont alors supprimées après vérification de leur absence d’usage.

## 8. Definition of Done d’un lot BC

Un lot est terminé seulement si :

- chaque opération possède un owner BC et un `operationId` unique ;
- les réponses d’erreur sont documentées et cohérentes avec Laravel ;
- sécurité, tenant et Policy sont testés ;
- routes Laravel et OpenAPI sont bidirectionnellement couvertes ;
- génération miroir/SDK réussit sans artefact committé ;
- tests unitaires, API et contrat passent ;
- changelog ou documentation de contrat est mise à jour si rupture ;
- observabilité et rollback sont documentés ;
- la PR est courte, rébasée sur `main` et tous les checks requis sont verts.

## 9. Indicateurs de pilotage

À suivre à chaque PR :

| Indicateur | Cible finale |
|---|---:|
| Avertissements Redocly | 0 |
| Opérations sans `operationId` | 0 |
| Doublons d’`operationId` | 0 |
| Opérations authentifiées sans 4xx approprié | 0 |
| Routes non documentées hors allowlist | 0 |
| Opérations documentées sans route hors allowlist | 0 |
| Opérations sans BC propriétaire | 0 |
| Tests cross-tenant des endpoints sensibles | 100 % |
| Artefacts OpenAPI générés suivis par Git | 0 |

## 10. Ordre de lancement conseillé aux agents

```text
Agent 14 → inventaire, mapping, outbox/webhooks et garde anti-régression
Agent 02 → Tenant & isolation
Agent 03 → Auth, invitations, sessions et privacy de base
Agent 01 → Platform, onboarding et CRM commercial
Agent 04 → HR
Agent 05 → Workforce / Attendance
Agent 06 → Leave
Agent 07 → Payroll
Agent 08 → Accounting
Agent 09 → Expense
Agent 10 → Recruitment
Agent 11 → CRM client
Agent 12 → Growth / Marketing
Agent 13 → Communications / Notifications / WhatsApp
Agent 15 → FuelStation
Agent 16 → EduManager
Agent 17 → Retail / POS
Agent 18 → Field / Fleet
Agent 19 → Devices / Cameras / Edge / Biometrics
Agent 20 → Documents
Agent 21 → Billing
Agent 22 → Analytics / Reporting
Agent 23 → AI / OCR / assistants bornés
```

Aucun agent ne doit commencer par modifier les 1 473 avertissements au hasard. Il commence par l’inventaire de son périmètre, produit une PR de contrat ciblée, ajoute les tests correspondants et laisse la CI prouver la réduction nette de la dette.
