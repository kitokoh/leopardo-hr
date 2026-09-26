# 09 — EXECUTION READINESS REVIEW

> Date : 2026-09-26 · Référence : `08_ARCHITECTURE_CHALLENGE.md` (qui prime sur `00`–`07`) · Statut : revue finale avant création des issues
> Ce document est la **source unique** pour la création des issues GitHub. Il renumérote, redécoupe, classe et ordonne. Aucun code applicatif modifié, aucune issue créée, rien de poussé.

---

## 0. Résultat du redécoupage (avant classification)

| Action | Détail |
|---|---|
| **SPLIT** BOS-006 | Trop grosse : config scheduler + audit rétrospectif des données sont deux unités. → **BOS-006A** (unification config) + **BOS-006B** (audit rétrospectif + correction données si avérée) |
| **SPLIT** BOS-005 | Le jonglage `SET search_path` du `KioskController` (SQL brut concaténé, 1004 lignes) est un chantier de correctness à part entière, pas du « nettoyage ». → **BOS-005** (mode schema mort : enum, commentaires, docs) + **BOS-019** (encapsulation search_path KioskController + PlatformCompanyHealthService) — réutilise le numéro 019 laissé libre en 2e passe |
| **MERGE** BOS-030 → BOS-031 | La spec « résilience LLM » est le premier livrable de BOS-031 (spec-first, convention repo), pas une issue séparée |
| **BOS-001, BOS-007, BOS-008** | Vérifiées : taille et scope corrects, **pas de redécoupage**. BOS-007 reste une seule unité (feature + PII du même fichier commande) ; la PII des autres fichiers logs est dans BOS-002 (périmètres disjoints, aucune collision) |
| **NON CRÉÉES** | BOS-027 (abonnement ×3 — P3, reporté), BOS-033 (supprimée en 2e passe), BOS-030 (fusionnée) |
| **BOS-024** | Reste 7 issues filles (a–g), un gabarit unique — créées comme 7 issues distinctes pour parallélisation réelle |

**Comptage final : 37 entrées ci-dessous = 43 issues GitHub** (BOS-024 compte pour 7). Nombre piloté par le contenu, pas l'inverse.

---

## 1–2. Classification et validation issue par issue

Légende : **P0** production/sécurité/intégrité · **P1** fondation/architecture · **P2** Business OS/valeur produit · **P3** futur.
Colonne « CA précis ? » : critères d'acceptation — ✅ = rédigés dans `05` et affinés ici · 🔧 = affinés/précisés **dans ce document** (§ ci-dessous).

### BLOCK 1 — Production / sécurité (toutes P0)

| Issue | Problème démontré (preuve code) | Objectif | Deps | Risque | Taille | Agent | ∥ | CA ? | Tests | Rollback |
|---|---|---|---|---|---|---|---|---|---|---|
| **BOS-006A** Unifier le scheduler | 8 commandes planifiées en double, horaires contradictoires (`bootstrap/app.php:55-126` vs `routes/console.php:147-302`) ; `leave:accrue` daily+monthly ; 2 expireurs Travel concurrents publiant 2 events différents | Une seule source de planification, `withoutOverlapping` partout, suppression expireur legacy | aucune | Régression de planification | S | Backend senior | ✅ | 🔧 §2.1 | Chaque commande s'exécute 1×/période (test scheduler) ; expireur legacy retiré | Revert (config seule) |
| **BOS-006B** Audit rétrospectif doubles exécutions | Conséquence probable du double scheduler en prod depuis des semaines | Inventorier doublons (`leave_accruals`, factures, expirations Travel) ; corriger si avéré ; rapport | BOS-006A (pour borner la période) | Correction de données prod | M | Backend senior + revue owner | ❌ après 006A | 🔧 §2.1 | Script d'audit en `--dry-run`, rapport chiffré, correction rejouable testée sur copie | Correction versionnée + backup préalable |
| **BOS-001** Sanitiser tool_result toutes branches | `PrivacySanitizer` ne traite que les strings ; branche Claude envoie tool_result tableaux non sanitisés (`Orchestrator.php:150-166`) | 100 % des payloads sortants sanitisés | aucune | Casser les réponses IA | S | Backend/Security | ✅ | ✅ (05) | Test par driver : fixture PII dans résultat tool → intercept HTTP sans PII | Revert |
| **BOS-007** contracts:alert-expiring réel | Commande entière = `Log::info` seul (`AlertExpiringContracts.php`, 47 l.) — aucune notification ; PII (noms employés) dans les logs | Notifications J-30/15/7 réelles aux managers concernés + retrait PII des logs de la commande | aucune | Spam de notifications | S | Backend | ✅ | 🔧 §2.1 | Notification envoyée aux bons destinataires aux 3 seuils (test) ; idempotence journalière (pas de doublon si 2 runs) ; logs sans noms | Revert |
| **BOS-008** Quota IA atomique | Check-then-increment sans verrou (`AIRateLimiter.php:71-76`) alors que l'upsert `AiCreditService:176-186` est atomique mais sa valeur de retour inutilisée | Décision de quota prise sur la valeur post-increment (ou `Cache::lock`) | aucune | Refus à tort sous charge | XS | Backend/AI | ✅ | ✅ | Test concurrence : N requêtes parallèles sous quota → exactement `limit` passent | Revert |
| **BOS-002** Confidentialité logs & rétention IA | `ai_audit_logs` prompt+réponse en clair sans rétention ; emails prospects en clair (`SelfServiceTrialController:155,190,225,353`) | Purge planifiée `ai:purge-audit-logs` + registre RGPD à jour + logs sans emails | BOS-003 pour l'effet planifié en prod (le code reste livrable avant) | Faible | S | Backend | ✅ | ✅ | Purge datée vérifiable ; grep logs = 0 email | Revert |
| **BOS-004** Write-tool legacy absences | `WriteActionRunner` legacy bypass l'Action canonique, pas d'`AbsenceApproved` | Un seul chemin d'approbation | aucune | Conversations en cours avec pending action (TTL 15 min) | S | Backend/AI | ✅ (déployer hors pic) | ✅ | Approbation IA → événement émis (test) ; registre DB nettoyé | Migration additive (ligne registry) |
| **BOS-022** Tests Planning | Planning possède absences+frais avec **2 tests** | ≥30 tests : cycle absence, soldes (golden), frais→écritures, scoping manager, lien Payroll | aucune | Faible | M | Backend/QA | ✅ | ✅ | Couverture chemins critiques ; golden tests soldes | N/A (tests) |
| **BOS-003** Workers/scheduler dédiés prod | Queue+scheduler dans le web free qui s'endort ; prod 2 j derrière main sans alerte (#8092) | Services séparés + alerte drift | **Décision owner (BLOCK 0)** + BOS-006A | Coût récurrent | S | DevOps | ✅ | 🔧 §2.1 | Job planifié à l'heure sur 7 j ; alerte < 1 h | Rétrogradation blueprint |

**Exit Gate BLOCK 1** (tous vérifiables) :
- [ ] `schedule:list` ne montre **aucune commande en double** ; chaque commande critique a `withoutOverlapping` ; l'expireur Travel legacy n'existe plus
- [ ] Rapport BOS-006B livré : nombre de doublons trouvés par domaine, corrections appliquées ou « aucun dégât » documenté
- [ ] Test d'interception HTTP : 0 PII (email/téléphone/ID) dans les payloads des 3 drivers LLM
- [ ] Une expiration de contrat de test déclenche une **notification réelle** reçue par le manager ; logs sans nom d'employé
- [ ] Test de concurrence quota : jamais plus que la limite, même sous 20 requêtes parallèles
- [ ] Approbation d'absence via IA émet `AbsenceApproved` ; 0 tool orphelin en base
- [ ] Planning ≥ 30 tests verts ; suite complète verte ; aucune régression API (tests de contrat)
- [ ] (Si décision owner prise) job planifié exécuté à l'heure 7 j consécutifs en prod + alerte drift active

### BLOCK 2 — Fondation (P1)

| Issue | Problème démontré | Objectif | Deps | Risque | Taille | Agent | ∥ | CA ? | Tests | Rollback |
|---|---|---|---|---|---|---|---|---|---|---|
| **BOS-010** Spec registre unifié | 3 sources de vérité features, désyncs #7220/7235/7432/7785/7976 | Spec+plan+tasks+ADR (spec-kit) | aucune | Faible | S | Architecte | ✅ | ✅ | Revue spec par owner | N/A |
| **BOS-011** ModuleRegistry source unique | Idem + tout nouveau module exige 3 enregistrements manuels | Registre PHP versionné unique ; `KNOWN_MODULES`, feature-flags.php, hasFeature, activateHorizontalTool, PlatformCompanyFeatureController le consomment ; dual-read | BOS-010 | Régression feature map | L | Backend senior (exclusivité Core) | ❌ | 🔧 §2.2 | Parité feature map (snapshot tous tenants staging) ; `/auth/me` identique ; kill switch prioritaire ; garde CI « module non enregistré = échec » | Dual-read 1 release |
| **BOS-012** Consolidation données features | `metadata.modules` et `features` écrits par chemins distincts | Backfill + écriture unifiée | BOS-011 | Désync données | M | Backend | ❌ après 011 | ✅ | `modules:consolidate --dry-run` : 0 diff fonctionnel hasFeature avant/après | Rapport diff + dual-read |
| **BOS-013** Manifest v2 | `permissions()` jamais câblé ; pas d'`industry` | Champ `industry` ; activation installe les permissions déclarées via grants existants (idempotent, transactionnel) | BOS-010 (∥ 011 si dev distinct) | Sur-attribution de droits | M | Backend | ⚠️ coord. Core | 🔧 §2.2 | Activer edumanager en staging → grants installés ; manifest inactif → 0 permission effective (fail-closed) | Retrait grants via même transaction d'activation |
| **BOS-014** Convergence manifests legacy | RestaurantManager = contrat local non conforme **non enregistré** ; Delivery idem ; 2 manifests restaurant | Tous manifests au contrat Core ; 1 seul restaurant ; garde CI | BOS-013 | Activation verticale cassée | S | Backend | ✅ | ✅ | 9 manifests conformes ; activation de chaque verticale testée | Revert |
| **BOS-015** Renommage Feature Registry (API mobile) | Homonymie avec le feature-gating = confusion avérée | `ApiEndpointRegistry` ; décision table documentée | BOS-011 | Casser manifeste mobile | XS | Backend+Docs | ✅ | ✅ | Manifeste mobile versionné inchangé (test contrat) | Alias de compat 1 release |
| **BOS-005** Nettoyage mode schema mort | `tenancy_type='schema'` verrouillé mort, encombre enum/docs | Retrait code mort + ADR « shared-schema définitif » | aucune | Faible | M | Backend | ✅ | ✅ | 0 chemin `tenancy_type=schema` ; tests tenancy verts | Revert |
| **BOS-019** Encapsulation search_path kiosk | `KioskController` (1004 l.) jongle `SET search_path` en SQL brut concaténé (`:605-693, :978-989`) — d'où le middleware de reset #3368 | Centraliser la bascule search_path dans TenantManager (try/finally garanti) ; supprimer le SQL manuel | BOS-005 ∥ possible | Régression pointage kiosk | M | Backend senior | ⚠️ (fichiers kiosk) | 🔧 §2.2 | Parcours kiosk complet en staging (pointage, sync) ; test de fuite search_path entre requêtes | Revert + middleware de reset conservé |

**Exit Gate BLOCK 2** :
- [ ] Ajouter un module fictif en staging = **1 seul fichier** modifié + enregistrement ; CI refuse un module non enregistré
- [ ] Parité feature map : `hasFeature` identique avant/après sur l'échantillon complet des tenants staging
- [ ] 9 manifests conformes au contrat Core ; activation edumanager installe les grants déclarés (et les retire proprement à la désactivation manuelle)
- [ ] 0 occurrence `SET search_path` concaténée hors TenantManager ; suite kiosk verte
- [ ] ADR « shared-schema définitif » mergé ; suite complète verte

### BLOCK 3 — Convergence & fiabilité IA (P1 sauf mention)

| Issue | Problème démontré | Objectif | Deps | Risque | Taille | Agent | ∥ | CA ? | Tests | Rollback |
|---|---|---|---|---|---|---|---|---|---|---|
| **BOS-016** Complétude Retail + Edu | Retail : **aucun manifest** (signup `solutions:["retail"]` = 422) ; Edu : activation = flag seul, workspace vide | Manifest Retail + enregistrement ; seed minimal Edu (campus + année scolaire) | BOS-013 (contrat v2) | Faible | M | Backend | ⚠️ (contrat manifest) | 🔧 §2.3 | Signup trial avec chaque solution → workspace amorcé ; E2E par verticale | Revert (additif) |
| **BOS-017** Jobs & listeners | 3/37 jobs `ShouldBeUnique` ; exports doublonnables ; 9 listeners sync à effets externes (reçu PDF dans la requête) | `ShouldBeUnique`/`$tries` sur exports ; queue les 3 listeners identifiés | aucune | Doublon de traitement pendant bascule | M | Backend | ✅ | 🔧 §2.3 | Double dispatch → 1 exécution (test) ; listener `InvoicePaid` async (test) | Revert |
| **BOS-018** Hygiène migrations | `schemaTableExists()` no-op silencieux dans 337/416 migrations ; `down()` destructeur Travel ; correctif commité 2× (#7417/#7420) | Garde CI anti-création-dupliquée ; fix down() ; purge doublons | aucune | Casser migrate:fresh local | S | Backend | ✅ | ✅ | Garde rouge sur duplication introduite ; migrate + rollback batch Travel OK sur copie | Revert |
| **BOS-050** Plumbing commerce public | Résolution tenant public ×6, idempotence invitée ×4, secret suivi ×3, throttle `shop-public` enregistré 2× (`AppServiceProvider:272,376`) ; suivi restaurant sans secret | `PublicTenantResolver` + `IdempotentGuestWrite` + `TrackingSecretService` partagés ; dédoublonnage throttle ; suivi restaurant renforcé ; doc conventions | aucune | Toucher 5 surfaces publiques transactionnelles | M | Backend senior | ⚠️ (middlewares communs) | 🔧 §2.3 | Chaque surface migrée garde ses tests publics verts ; nouveau suivi restaurant : ancien lien réf+slug déprécié avec transition documentée | Par surface, revert indépendant |
| **BOS-020** Integration outbox (descopée) | Outbox ×4 (Travel, Restaurant, Edu, Platform) | Socle `Integration` + **1** module migré en référence (issues filles pour les autres, hors scope) | BOS-010 (process) | Perte d'événements | M | Backend senior | ⚠️ | ✅ (modifiée 08) | Replay sans perte sur le module migré | Double-écriture transitoire |
| **BOS-021** Webhooks unifiés | 3 implémentations (Billing, Travel, Platform) | Un mécanisme dans Integration | BOS-020 | Signature clients existants | M | Backend | ⚠️ après 020 | ✅ | Livraison+retry+signature compatibles | Revert |
| **BOS-023** Cycles cœur HR | 55 paires allowlistées, cycles HR↔Attendance↔Planning↔Payroll | Contrats Shared ; **0 cycle nouveau** + allowlist ≤ 35 (indicateur) | aucune | Régression cœur | L (continue) | Backend senior | ⚠️ (conflits modules) | 🔧 §2.3 | `check-module-isolation.sh` vert ; allowlist décroissante à chaque PR | Revert par PR |
| **BOS-024a–g** Application layer ×7 | 7 modules à 0 fichier Application (Edu, Health, Pharmacy, Hospitality, Communication, Catalog, Fleet) | Actions nommées par module, controllers amincis | aucune | Faible | M ×7 | Backend ×N | ✅ total | ✅ | ≥5 Actions/module ou justification ; tests Actions ; API inchangée | Revert |
| **BOS-025** Frontière Notif/Comm | Threads dupliqués, `CommunicationEvent` dans Notification | ADR + déplacement avec alias compat | aucune | Casser événements cross-module | M | Architecte+Backend | ✅ | ✅ | ADR validé ; événements migrés sans renommage de tables | Alias 1 release |
| **BOS-026** Façades Growth/Absence | Growth 0 modèle propre ; Absence façade | Absorber Growth→Billing ; finaliser Absence→Planning | BOS-011 | Route cassée | S | Backend | ✅ | ✅ | Registre/providers/routes/tests déplacés ; test contrat routes | Revert |
| **BOS-031** ResilientLLMClient (incl. spec) | Aucun retry/fallback/circuit breaker ; tarifs coûts en dur ; pas de `response_format` | Décorateur `LLMClient` : retry 2× backoff, chaîne fallback config, circuit breaker/provider, tarifs en config, respect `AiCloudPolicy` par candidat, `response_format` optionnel ; **spec d'abord (livrable 1)** | BOS-001 | Fallback vers cloud interdit | M | Backend/AI senior | ❌ (fichiers AI) | 🔧 §2.3 | Failover démontré staging (A down → B répond) ; CB ouvre/ferme ; `ai_cloud_allowed=false` → jamais de fallback cloud ; budgets intacts | Config `AI_LLM_DRIVER` direct (décorateur désactivable par flag) |
| **BOS-032** Idempotence write-tools | Pas de clé métier ; PendingActionStore sur Cache | Clés idempotence + table `ai_write_idempotency` ; décision PendingActionStore→DB | BOS-001 | Faible | S | Backend/AI | ⚠️ | ✅ | Double confirmation = 1 effet (test par write critique) | Migration additive |
| **BOS-034** Anti-injection + outputSchema | Chat principal sans anti-injection ; `outputSchema` jamais validé | Délimiteurs contenus métier + validation JSON-schema args/sorties, fail-closed + audit | BOS-001 | Faux positifs bloquants | M | Security/AI | ⚠️ | ✅ | Injection via motif d'absence hostile → aucun write non demandé | Revert (validation en mode warn 1 semaine puis strict) |
| **BOS-035** Assistant web portail (P2) | Aucun front web ; confirm/reject sans client → écritures IA dormantes | Panneau assistant `front/web` + cartes de confirmation | aucune (API existante) | Faible | M | Frontend | ✅ | ✅ | E2E Playwright : question→tool→réponse ; write→confirmation→exécution | Feature flag UI |
| **BOS-036** ADR multi-org (P2) | Cas B/D/G-multi/H non couverts (email unique global, pas de switch) | ADR go/no-go/go-limité + POC ; scénarios explicites : propriétaire multi-entreprises, employé multi-employeurs, **cabinet comptable**, holding | aucune | Décision prématurée | M | Architecte+Backend senior | ✅ | 🔧 §2.3 | ADR avec matrice d'impact (kiosk, invitations, OTP, mobile) + estimation migration ; **0 code prod** | N/A |
| **BOS-051** ADR surfaces publiques (P2) | Chevauchement front/web (shop/restaurants/vitrine) vs front/marketplace | Arbitrage produit documenté, lié ADR-0004 | aucune | Faible | XS | PM | ✅ | ✅ | ADR validé owner | N/A |

**Exit Gate BLOCK 3** :
- [ ] `solutions:["retail"]` et `["edumanager"]` provisionnent un workspace **amorcé** en staging (E2E verts)
- [ ] Un export déclenché 2× ne produit qu'un fichier ; `InvoicePaid` n'envoie plus de mail synchrone (temps de réponse mesuré avant/après)
- [ ] Garde CI migrations rouge sur duplication introduite de test
- [ ] Les 5 surfaces publiques utilisent le plumbing partagé, tests publics verts, throttle dédoublonné
- [ ] 1 module sur outbox unifié avec replay sans perte ; allowlist cycles ≤ 35
- [ ] Panne Groq simulée en staging → assistant servi par fallback, audit le prouve ; `ai_cloud_allowed=false` → dégradation explicite, jamais de cloud
- [ ] Assistant web : parcours write complet avec confirmation depuis le navigateur
- [ ] ADR multi-org : décision écrite go / go-limité / no-go, validée owner

### BLOCK 4 — Léa / Business OS (toutes P2, **gated** : Exit Gate BLOCK 2 + BOS-031 + BOS-009)

| Issue | Problème démontré | Objectif | Deps | Risque | Taille | Agent | ∥ | CA ? | Tests | Rollback |
|---|---|---|---|---|---|---|---|---|---|---|
| **BOS-009** Complétude Travel (pilote) | Gaps §3 : survey 404, E2E signup absent, demo sans trajet vendable, rôles non seedés | Survey travel + E2E trial-signup (+assert seed géo) + trajet vendable demo kit + template rôles agence | BOS-013/014 | Faible | M | Backend | ✅ | 🔧 §3 | Cf. critères pilote §3 | Revert (additif) |
| **BOS-040** Spec Léa | Aucun appel LLM dans l'onboarding ; FREE_TEXT inerte | Spec : NL → réponses d'interview existantes → validation allowlist → `CompleteSetupInterview` inchangé ; règles constitution (IA propose/moteur décide ; futures tables knowledge : company_id+ACL+rétention) | BOS-011, BOS-013 | Faible | S | PM/Architecte | ✅ | ✅ | Revue owner | N/A |
| **BOS-041** InterviewAnswerExtractor | — | LLM (JSON prompt + validation + 1 retry) → schéma d'answers existant ; inconnu → clarification | BOS-040, BOS-031 | JSON invalide | M | Backend/AI | ❌ séquence | 🔧 §3 | 5 descriptions types → answers attendus ; injection dans la description → rejet ; hors allowlist → clarification, jamais de valeur inventée | Flag |
| **BOS-042** Endpoint Léa | — | `POST /onboarding/lea/interpret` (auth trial, rate-limit, flag) + ≤2 rounds clarification + exécution via flux existant + fallback interview | BOS-041, BOS-031, BOS-009 | Provisioning silencieux | M | Backend/AI | ❌ | 🔧 §3 | Parcours E2E « agence de voyages » : plan Léa == plan interview ; audit `lea.intent.*` ; LLM down → interview proposé | Flag `onboarding.lea.enabled` |
| **BOS-043** UI Léa | — | Champ libre → résumé structuré **éditable** → confirmation explicite → provisioning ; bascule formulaire permanente ; i18n 4 langues | BOS-041 (contrat mocké) | Faible | M | Frontend | ✅ (mock) | 🔧 §3 | E2E Playwright : NL → résumé → **modification d'une capability** → provisioning du plan modifié | Flag |
| **BOS-044** Pilote mesuré | — | Métriques : complétion Léa vs formulaire, taux fallback, taux modification résumé, coût/onboarding, revue audits | BOS-042, 043 | Faible | S | QA+Product | ❌ | 🔧 §3 | Rapport + décision GA/itération/retrait documentée | Flag OFF |

**Exit Gate BLOCK 4 (= critères de succès pilote, §3)** :
- [ ] 2 descriptions NL (« agence de voyages 2 bureaux » + « école 600 élèves ») → workspaces provisionnés en staging, plan identique à l'interview déterministe pour le même besoin
- [ ] L'utilisateur a **validé et pu modifier** le résumé avant exécution (trace d'audit)
- [ ] LLM coupé → parcours interview proposé, 0 inscription bloquée
- [ ] Taux de fallback pilote < 30 % ; coût LLM/onboarding mesuré ; revue RGPD des audits sans anomalie

---

## 2 bis. Critères d'acceptation affinés (extraits 🔧)

**§2.1**
- **BOS-006A** : `schedule:list` sans doublon ; chaque entrée documentée (commande, fréquence, verrou) ; suppression `TravelExpireBookingsCommand` legacy + ses publications d'événements ; note de déploiement (fenêtre sans run critique).
- **BOS-006B** : requêtes d'audit par domaine (accruals de congés, factures, expirations travel) bornées à la période du double scheduler ; rapport « doublons trouvés / corrigés / irrelevants » validé owner ; toute correction de données = script rejouable + backup préalable.
- **BOS-007** : notifications aux 3 seuils avec destinataires = managers de l'employé (rôle `principal`/`rh`) ; dédup par (contrat, seuil, date) ; logs = IDs uniquement.
- **BOS-003** : blueprint avec worker+scheduler ; alerte « prod derrière main > 1 h » ; coût mensuel estimé joint à l'issue pour validation owner.

**§2.2**
- **BOS-011** : liste exhaustive des consommateurs migrés dans l'issue ; dual-read avec log de divergence ; la bascule est un flag, pas un big-bang.
- **BOS-013** : `industry` ∈ enum versionné ; installation permissions = `employee_module_grants` du principal (jamais de nouveau système de rôles) ; test de non-attribution si manifest inactif.
- **BOS-019** : toute bascule search_path via API TenantManager avec `try/finally` ; test multi-requêtes simulant la fuite.

**§2.3**
- **BOS-016** : seed Edu = 1 campus « Campus principal » + année scolaire courante (idempotent) ; manifest Retail conforme au contrat v2 du jour (pas de dérogation).
- **BOS-017** : liste fermée des jobs/listeners touchés dans l'issue (exports identifiés + 3 listeners) — pas de ratissage général.
- **BOS-050** : migration surface par surface, chacune derrière ses tests publics existants ; les anciens liens de suivi restaurant restent valables 90 j (dépréciation documentée).
- **BOS-023** : chaque PR cible 1 cycle ; fusion si l'allowlist remonte.
- **BOS-031** : décorateur activé par config (OFF possible = comportement actuel) ; jamais de fallback vers un provider interdit par la politique cloud du tenant.
- **BOS-036** : livrables = ADR + POC jetable + estimation ; décision « go-limité » possible (switcher limité aux comptes liés `user_employee_links`, sans toucher l'unicité email).

---

## 3. Pilote Léa — scénario de validation (TravelAgency)

### Scénario nominal de validation

```
1. Utilisateur (staging, compte trial) : « Je gère une agence de voyages
   avec 2 bureaux à Alger et Oran. Je veux vendre des billets en ligne
   et suivre mes guichets. »
2. POST /onboarding/lea/interpret → InterviewAnswerExtractor (LLM)
3. Answers structurés produits :
   { company_type: "team", team_size: "1-10", sector: "travel",
     premises: "multiple", priorities: ["attendance"] }
   (valeurs ∈ allowlist SetupInterviewPlanner — validation fail-closed)
4. UI : résumé éditable affiché → utilisateur confirme (ou corrige)
5. Exécution = chemin interview existant : plan {solutions:["travelagency"], tools:[…]}
   → CompleteSetupInterview → SolutionActivator::activateWithDependencies
   → modules requis (rh, documents, notifications, crm) + flag + audit
   → listener TravelAgency : géo 223 pays + villes seedée
   → checklist onboarding travel (travel_setup_network → first_trip → first_sale)
   → (BOS-009) rôles agence proposés + demo kit avec trajet vendable
6. Workspace : manager connecté, checklist travel affichée, trajet démo réservable
7. Vérification : feature map travel active, seed géo en base, audit complet,
   marketplace publique travel accessible pour le tenant
```

### Les 6 gaps (rappel) et leur résolution

| # | Gap | Résolu par |
|---|---|---|
| 1 | Rien ne traduit le NL en `sector=travel` | BOS-041/042 (cœur Léa) |
| 2 | Aucun rôle/persona agence seedé | BOS-009 (template rôles + BOS-013 permissions) |
| 3 | Demo kit sans trajet vendable | BOS-009 |
| 4 | Survey travel 404 (pas de mesure conversion) | BOS-009 |
| 5 | Pas d'E2E trial-signup travel (+assert seed) | BOS-009 |
| 6 | App guichet Flutter quasi vide (21 fichiers) | **Hors pilote** — guichet validé via `front/web` ; l'app Flutter est un chantier produit séparé, non bloquant pour déclarer le pilote réussi |

### Critères de succès du pilote

1. **Parité déterministe** : pour 5 descriptions types, le plan produit via Léa est **identique en structure** au plan de l'interview pour le même besoin (test automatisé).
2. **Bout en bout** : les scénarios « agence » (travel) et « école » (edumanager — prouve la généricité) aboutissent à un workspace amorcé utilisable en staging, sans intervention développeur.
3. **Contrôle humain** : 100 % des exécutions précédées d'une confirmation explicite ; modification du résumé prise en compte (E2E).
4. **Résilience** : LLM indisponible → fallback interview, 0 blocage ; réponse LLM hors allowlist → clarification, jamais de provisioning invalide.
5. **Mesure** : complétion onboarding Léa ≥ formulaire ; fallback < 30 % ; coût/onboarding < seuil fixé en spec ; audits RGPD propres.
6. **Réversibilité** : flag OFF = produit identique à aujourd'hui.

---

## 4. Ordre d'exécution et règles de simultanéité

```
BLOCK 0 (humain, semaine 1)  : décision budget workers/scheduler (BOS-003) + validation de ce document
BLOCK 1 (P0, ~2-3 sem)       : BOS-006A → 006B ∥ 001 ∥ 007 ∥ 008 ∥ 002 ∥ 004 ∥ 022 (+010 architecte ∥) ; BOS-003 dès décision
BLOCK 2 (P1, ~3-4 sem)       : 011 → 012 ∥ 013 → 014 ∥ 015 ∥ 005 ∥ 019
BLOCK 3 (P1+P2, ~4-6 sem)    : 016 ∥ 017 ∥ 018 ∥ 050 ∥ 020→021 ∥ 023 ∥ 024a-g ∥ 025 ∥ 026 ∥ 031 ∥ 032 ∥ 034 ∥ 035 ∥ 036 ∥ 051
BLOCK 4 (P2, ~3-4 sem)       : 009 ∥ 040 → 041 → 042 (∥ 043 mocké) → 044
```

| Règle | Détail |
|---|---|
| Avant BLOCK 2 | Exit Gate BLOCK 1 + spec BOS-010 relue |
| Avant BLOCK 3 | Exit Gate BLOCK 2 (registre unifié en place — BOS-016/026 en dépendent) |
| Avant BLOCK 4 | Exit Gate BLOCK 2 + BOS-031 mergé + BOS-009 mergé |
| **Jamais simultanément** | Deux agents sur `app/Core` ou `Company.php` (BOS-011/012/013/026 : séquentiel) · deux PR sur `Orchestrator.php`/`config/ai.php` (BOS-031/032/034 : une à la fois) · deux migrations tenant dans la même PR · BOS-006B pendant BOS-006A (le périmètre d'audit dépend de la config figée) |
| Parallélisme total sûr | BOS-024a–g · BOS-035 vs toute la backend Phase 3 · BOS-036/051 (ADR) · BOS-043 sur contrat mocké |

---

## 5. READY FOR ISSUE CREATION: **YES**

Justification : chaque issue a (1) un problème démontré dans le code avec chemin:ligne, (2) un objectif unique, (3) des dépendances explicites, (4) des critères d'acceptation testables (affinés ici là où ils manquaient de précision), (5) une stratégie de rollback, (6) un agent type et un verdict de parallélisation. Les 4 issues sensibles (006, 001, 007, 008) ont été examinées : 006 splittée, les trois autres validées comme unités autonomes. Aucune issue ne survit « parce qu'elle était dans le rapport précédent » : 027 et 033 sont sorties du plan, 030 absorbée.

**Condition unique** : la création se fait **par bloc**, pas en une fois — BLOCK 1 d'abord (10 issues), les blocs suivants créés au déblocage de leur gate, pour éviter le bruit et le travail prématuré sur des specs susceptibles d'évoluer.

### Liste finale dans l'ordre de création/exécution

| # | Issue | P | Bloc |
|---|---|---|---|
| 1 | BOS-006A Unifier le scheduler | P0 | 1 |
| 2 | BOS-006B Audit rétrospectif doubles exécutions | P0 | 1 |
| 3 | BOS-001 Sanitiser tool_result toutes branches LLM | P0 | 1 |
| 4 | BOS-007 contracts:alert-expiring réel + logs sans PII | P0 | 1 |
| 5 | BOS-008 Quota IA atomique | P0 | 1 |
| 6 | BOS-002 Confidentialité logs & rétention IA | P0 | 1 |
| 7 | BOS-004 Write-tool legacy absences | P0 | 1 |
| 8 | BOS-022 Tests Planning (2→≥30) | P0 | 1 |
| 9 | BOS-003 Workers/scheduler prod (bloquée : décision owner) | P0 | 0→1 |
| 10 | BOS-010 Spec registre unifié | P1 | 1 |
| 11 | BOS-011 ModuleRegistry source unique | P1 | 2 |
| 12 | BOS-012 Consolidation données features | P1 | 2 |
| 13 | BOS-013 Manifest v2 (industry + permissions) | P1 | 2 |
| 14 | BOS-014 Convergence manifests legacy | P1 | 2 |
| 15 | BOS-015 Renommage ApiEndpointRegistry | P1 | 2 |
| 16 | BOS-005 Nettoyage mode schema mort | P1 | 2 |
| 17 | BOS-019 Encapsulation search_path kiosk | P1 | 2 |
| 18 | BOS-016 Complétude Retail + Edu | P1 | 3 |
| 19 | BOS-017 Jobs & listeners fiabilisation | P1 | 3 |
| 20 | BOS-018 Hygiène migrations | P1 | 3 |
| 21 | BOS-050 Plumbing commerce public | P1 | 3 |
| 22 | BOS-020 Integration outbox (socle + 1 module) | P1 | 3 |
| 23 | BOS-021 Webhooks unifiés | P1 | 3 |
| 24 | BOS-023 Cycles cœur HR | P1 | 3 |
| 25 | BOS-024a–g Application layer ×7 | P1 | 3 |
| 26 | BOS-025 Frontière Notification/Communication | P1 | 3 |
| 27 | BOS-026 Façades Growth/Absence | P1 | 3 |
| 28 | BOS-031 ResilientLLMClient (spec incluse) | P1 | 3 |
| 29 | BOS-032 Idempotence write-tools | P1 | 3 |
| 30 | BOS-034 Anti-injection + validation schémas | P1 | 3 |
| 31 | BOS-035 Assistant web portail + confirmations | P2 | 3 |
| 32 | BOS-036 ADR multi-org | P2 | 3 |
| 33 | BOS-051 ADR surfaces publiques | P2 | 3 |
| 34 | BOS-009 Complétude Travel (pilote) | P2 | 4 |
| 35 | BOS-040 Spec Léa | P2 | 4 |
| 36 | BOS-041 InterviewAnswerExtractor | P2 | 4 |
| 37 | BOS-042 Endpoint Léa | P2 | 4 |
| 38 | BOS-043 UI Léa | P2 | 4 |
| 39 | BOS-044 Pilote mesuré | P2 | 4 |

*(39 lignes = 43 issues GitHub avec BOS-024a–g · BOS-027 et BOS-033 non créées · BOS-030 absorbée)*

**Prochaine étape si validation** : (1) PR documentaire `docs/business-os` (fichiers 00–09) ; (2) création des labels (`business-os`, `p0-production`, `p1-fondation`, `p2-business-os`, + existants) ; (3) création des 10 issues BLOCK 1 uniquement, corps généré depuis ce document.
