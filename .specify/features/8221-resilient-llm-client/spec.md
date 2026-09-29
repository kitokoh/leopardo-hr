# Feature Specification: ResilientLLMClient — retry, fallback, circuit breaker (BOS-031)

**Feature Branch**: `feat/8221-bos-031-resilient-llm-client`
**Created**: 2026-09-29 | **Status**: Validée pour implémentation (livrable 1 de #8221 — code livré dans la même PR, règle « 1 issue = 1 PR »)
**Issue**: #8221 (BOS-031, Block 3 P1 — fondation IA ; inclut la spec ex-BOS-030 fusionnée)
**Références programme**: `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` (PR #8138, §Block 3 + §2.3) ; `08_ARCHITECTURE_CHALLENGE.md` (C-3 structured output, C-4 réduction ModelRouter → décorateur) ; dépendance #8141 (BOS-001) **mergée** le 2026-09-26.

---

## 1. Contexte et problème démontré

Les trois clients LLM sont du HTTP brut, chacun avec `->timeout(30)` et **aucun
retry, aucun fallback, aucun circuit breaker** :

| # | Preuve (vérifiée sur `main` au 2026-09-29) |
|---|---|
| 1 | `api/app/AI/Providers/OpenAIClient.php:44` — `Http::withToken(...)->timeout(30)->post(...)`, échec → `AIResponse(error:)` immédiat |
| 2 | `api/app/AI/Providers/GroqClient.php` / `ClaudeClient.php` — même structure, même absence de résilience |
| 3 | `api/app/Providers/AppServiceProvider.php:66-77` — `AI_LLM_DRIVER` sélectionne **un seul** driver ; sa panne = panne de l'assistant |
| 4 | `api/app/AI/AIAuditLogger.php:193-205` — tarifs de coût **codés en dur** (`gpt-4o`, `gpt-4o-mini`, `claude-sonnet-4-20250514` + défaut), invisibles de l'exploitation |
| 5 | `api/app/AI/LLMClient.php` — interface sans `response_format` (constat C-3 du doc 08 : aucun structured output possible) |

**Conséquence** : une panne du provider actif (timeout, 5xx, coupure réseau) est
une panne de l'assistant pour tous les tenants, alors que les autres providers
restent sains. L'onboarding Léa (Block 4, BOS-041/042) mettra le LLM sur le
chemin critique du signup : la résilience est un prérequis, pas une option.

## 2. Décision d'architecture (rappel — doc 08, C-4)

Pas de `ModelRouter` ni de routage par tâche (YAGNI confirmé). Un **décorateur
`ResilientLLMClient`** qui **implémente `LLMClient`** : zéro changement
consommateur obligatoire, activation par flag, OFF = comportement strictement
actuel. Le décorateur ajoute : retry avec backoff, chaîne de fallback
configurée, circuit breaker par provider, et le respect de `AiCloudPolicy`
**par candidat**.

## 3. User Stories & Testing

### User Story 1 — Panne provider ≠ panne assistant (P1)

Le provider principal (ex. Groq) tombe (5xx, timeout). L'utilisateur de
l'assistant est servi par le provider suivant de la chaîne (ex. OpenAI), sans
action ni message d'erreur, et l'audit attribue la réponse au provider
réellement servant.

**Why this priority**: c'est l'objet de l'issue — l'Exit Gate Block 3 exige « panne Groq simulée → assistant servi par fallback, audit le prouve ».

**Independent Test**: test Feature avec un primaire scripté en échec retryable et un fallback scripté en succès → réponse du fallback servie, `provider` attribué au fallback, appels du primaire bornés (1 + retries).

**Acceptance Scenarios**:
1. **Given** une chaîne `[groq → openai]` et Groq qui répond 500, **When** `chat()` est appelé, **Then** la réponse vient d'OpenAI et porte `provider='openai'`.
2. **Given** une chaîne `[groq → openai]` et Groq qui répond 401 (non retryable), **When** `chat()` est appelé, **Then** aucun retry n'est tenté sur Groq et le fallback OpenAI est tenté immédiatement.
3. **Given** tous les candidats en échec, **When** `chat()` est appelé, **Then** la réponse est une erreur **explicite** (message de dégradation, jamais muette) et aucun effet de bord n'a lieu.

### User Story 2 — Un provider down n'est plus martelé (P1)

Après N échecs consécutifs d'un provider, le circuit breaker **ouvre** : les
appels suivants le sautent sans tentative réseau (fail-fast) pendant le
cooldown, puis une sonde **half-open** le teste ; succès → circuit refermé,
échec → ré-ouvert.

**Why this priority**: sans CB, chaque requête paie 1 à 3 timeouts de 30 s avant le fallback — l'assistant devient inutilisable précisément pendant l'incident.

**Independent Test**: test Feature — seuil 3 échecs → le 4e appel n'émet **aucune** requête vers le provider ; avance du temps au-delà du cooldown → 1 appel sonde ; sonde OK → compteur remis à zéro.

**Acceptance Scenarios**:
1. **Given** 3 échecs consécutifs de Groq (seuil configuré à 3), **When** un 4e `chat()` arrive, **Then** Groq n'est pas appelé et le fallback répond (ou la dégradation explicite si aucun fallback éligible).
2. **Given** le circuit ouvert depuis plus que le cooldown, **When** `chat()` arrive, **Then** exactement un appel sonde est tenté ; sonde en succès → circuit fermé (compteur 0) ; sonde en échec → circuit ré-ouvert pour un nouveau cooldown.
3. **Given** un succès du provider, **When** le prochain `chat()` arrive, **Then** le compteur d'échecs consécutifs est reparti à 0.

### User Story 3 — `ai_cloud_allowed=false` → jamais de fallback cloud (P1 — conformité)

Un tenant sans le flag `ai_cloud_allowed` (défaut, fail-closed — issue #6853)
ne voit **jamais** ses prompts partis vers un provider cloud via le fallback,
même si la chaîne en contient. La dégradation est explicite.

**Why this priority**: règle absolue du programme (« jamais de fallback vers un provider interdit par la politique cloud du tenant ») — CA n° 3 de l'issue.

**Independent Test**: test Feature — tenant sans flag, primaire non-cloud scripté en échec, chaîne contenant un cloud → le client cloud n'est **jamais instancié ni appelé** (espion) et la réponse est la dégradation explicite.

**Acceptance Scenarios** (matrice complète §4.2) :
1. **Given** primaire `fake` (non-cloud) en échec + fallback `openai` (cloud) + tenant sans flag, **When** `chat()` est appelé, **Then** OpenAI n'est jamais tenté et l'erreur retournée est la dégradation explicite.
2. **Given** la même chaîne + tenant **avec** flag, **When** `chat()` est appelé, **Then** OpenAI est tenté et sert la réponse.
3. **Given** aucun tenant en session (contexte CLI/queue sans `TenantManager::current()`), **When** le fallback candidat est cloud, **Then** il est sauté (fail-closed) — identique à `AiCloudPolicy::cloudAllowed(null) === false`.

### User Story 4 — Exploitation : coûts et métriques par provider réel (P1)

Les tarifs de coût vivent en **config** (plus en dur), et l'audit/analytics
attribue chaque requête au provider **réellement servant** — les métriques par
provider de l'analytics existant (`AIAnalyticsController`, groupement par
`provider`) deviennent véridiques en présence de fallback.

**Why this priority**: CA n° 4 (« coûts estimés par provider depuis la config ») ; sans attribution réelle, un incident fallback est invisible dans les coûts.

**Independent Test**: test — `AIAuditLogger` calcule le coût d'un modèle dont le tarif n'existe que dans la config posée par le test ; réponse de fallback → `AIResponse::provider` = provider du fallback.

**Acceptance Scenarios**:
1. **Given** un tarif `mon-modele-test` posé en config, **When** `estimateCost` est exercé via `log()`, **Then** le coût utilise ce tarif ; modèle inconnu → tarif `default_rate` de la config (valeurs identiques à l'existant).
2. **Given** le flag résilience OFF, **When** `estimateCost` est exercé, **Then** les coûts calculés sont **strictement identiques** à l'implémentation actuelle (parité, mêmes valeurs par défaut).

### User Story 5 — Retour arrière instantané (P1)

L'exploitation pose `AI_RESILIENCE_ENABLED=false` (défaut) : le binding
container rend le **driver direct**, comme aujourd'hui — aucun décorateur,
aucun changement de comportement, aucune donnée à rejouer.

**Independent Test**: test de parité — flag OFF → `app(LLMClient::class)` est une instance du driver direct pour chaque valeur de `ai.driver` ; une conversation complète fake-driver produit les mêmes réponses que sur `main`.

## 4. Politique de fallback et matrice cloud-policy

### 4.1 Règles de la chaîne

- La chaîne est **ordonnée** : primaire (`ai.driver`) puis `ai.resilience.fallback_chain` (CSV `AI_FALLBACK_CHAIN`), dédupliquée, primaire exclu s'il y figure.
- Seuls les drivers **connus** (`fake|groq|openai|claude` — le `match` existant d'`AppServiceProvider`) sont éligibles ; une entrée inconnue est **ignorée avec un log d'avertissement au boot** (jamais d'exception depuis le binding).
- Le **primaire n'est jamais gated** par le décorateur : son admission (politique cloud incluse) reste la responsabilité des consommateurs, **inchangée** (Orchestrator refuse déjà en amont, `Orchestrator.php:59-65`).
- Chaque **candidat fallback cloud** (`groq|openai|claude`, cf. `AiCloudPolicy::CLOUD_DRIVERS`) est gated : `AiCloudPolicy::cloudAllowed(TenantManager::current())` — tenant de **session uniquement**, jamais d'entrée utilisateur/LLM ; tenant `null` → refus (fail-closed).
- Un candidat non-cloud (`fake`/local) est toujours éligible.
- Candidat sauté pour politique cloud ou circuit ouvert : non tenté, passage au suivant.
- Tous candidats épuisés/interdits → **dégradation explicite** : `AIResponse` en erreur avec message explicite (pas de 500 muet, pas de contenu inventé), `provider` = primaire (attribution de la tentative), aucun effet de bord.

### 4.2 Matrice cloud-policy × fallback

| Primaire | `ai_cloud_allowed` (tenant session) | Candidat fallback | Verdict |
|---|---|---|---|
| non-cloud (`fake`) | OFF | cloud | **jamais tenté** → dégradation explicite si primaire échoue |
| non-cloud (`fake`) | OFF | non-cloud | tenté (éligible) |
| non-cloud (`fake`) | ON | cloud | tenté après épuisement du primaire |
| cloud | OFF | cloud | primaire = comportement actuel (refus amont consommateur) ; fallback cloud **jamais tenté** |
| cloud | OFF | non-cloud | primaire inchangé ; fallback non-cloud tenté si primaire échoue |
| cloud | ON | cloud | tenté après épuisement du primaire |
| _pas de tenant en session_ | _n/a_ | cloud | **jamais tenté** (fail-closed) |

## 5. Retry, backoff et circuit breaker

### 5.1 Classification des erreurs (nouveau, portée par `AIResponse`)

`AIResponse` gagne deux informations optionnelles et rétro-compatibles :
`status` (`?int`, code HTTP quand il existe) et `provider` (`?string`, provider
servant). Classification **retryable** :

- exception réseau/timeout (pas de statut HTTP) → **retryable** ;
- HTTP `429` ou `>= 500` → **retryable** ;
- HTTP `4xx` autres (400/401/403/404/422…) → **non retryable** ;
- réponse réussie → aucun retry.

Les 3 clients HTTP renseignent `status` (et `provider`) sur leurs réponses
d'erreur comme de succès — changement interne à `app/AI`, constructeur inchangé
en positionnels (paramètres ajoutés **en fin, optionnels**).

### 5.2 Retry

- **2 retries** après la tentative initiale (3 tentatives max par provider), uniquement sur erreur retryable.
- Backoff exponentiel déterministe : `base_delay_ms × 2^n`, borné par `max_delay_ms` (défauts : 200 ms / 2000 ms), sans jitter (reproductibilité des tests) ; sommeil via `usleep`, configurable à 0 en test.
- Aucun retry entre providers : le passage au candidat suivant est immédiat après épuisement des retries du courant.

### 5.3 Circuit breaker (par provider)

- État en **Cache** (store par défaut, Redis en prod) : `{echecs_consecutifs, opened_at}` par provider.
- `failure_threshold` (défaut **3**) échecs consécutifs → circuit **ouvert** pendant `cooldown_seconds` (défaut **60 s**) : le candidat est sauté sans appel.
- Cooldown écoulé → **half-open** : exactement 1 appel sonde. Sonde OK → fermé (compteur 0) ; sonde KO → ré-ouvert (nouveau cooldown).
- Tout succès remet le compteur à 0. Comportement **best-effort** documenté : si le Cache lève une exception, le circuit est traité comme **fermé** (une panne Redis ne doit pas casser le chemin LLM) ; pas de verrou distribué (le CB est une protection de charge, pas une garantie transactionnelle).
- Événements `fallback engagé`, `circuit ouvert`, `sonde half-open` journalisés en `Log::warning` structuré (provider, tentative, statut — **sans PII ni prompt**).

## 6. Config (`api/config/ai.php` — additif)

```php
// BOS-031 (#8221) — résilience LLM (décorateur). Défaut OFF = comportement actuel.
'resilience' => [
    'enabled' => (bool) env('AI_RESILIENCE_ENABLED', false),
    'fallback_chain' => <csv AI_FALLBACK_CHAIN → liste, vide par défaut>,
    'retry' => [
        'max_retries' => (int) env('AI_RETRY_MAX_RETRIES', 2),
        'base_delay_ms' => (int) env('AI_RETRY_BASE_DELAY_MS', 200),
        'max_delay_ms' => (int) env('AI_RETRY_MAX_DELAY_MS', 2000),
    ],
    'circuit_breaker' => [
        'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('AI_CB_COOLDOWN_SECONDS', 60),
    ],
],
// BOS-031 (#8221) — tarifs de coût externalisés (USD / 100k tokens, formule
// inchangée : (tokens/100_000)×tarif, arrondi en cents). Valeurs = celles
// codées en dur dans AIAuditLogger avant externalisation (parité stricte).
'costs' => [
    'default_rate' => ['input' => 0.1, 'output' => 0.3],
    'rates' => [
        'gpt-4o' => ['input' => 0.25, 'output' => 1.0],
        'gpt-4o-mini' => ['input' => 0.015, 'output' => 0.06],
        'claude-sonnet-4-20250514' => ['input' => 0.3, 'output' => 1.5],
    ],
],
```

## 7. `response_format` optionnel

- Signature : `LLMClient::chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse`.
- Pass-through aux clients qui le supportent : OpenAI et Groq (API OpenAI-compatible) ajoutent `response_format` au payload quand non nul ; Claude (pas d'équivalent natif) et Fake l'ignorent — documenté en docblock.
- Le décorateur transmet tel quel à chaque candidat.
- Implémentations de test (`tests/**` doublons `implements LLMClient`) migrées mécaniquement sur la nouvelle signature (paramètre optionnel ajouté, aucun changement de comportement).

## 8. Exigences fonctionnelles

| # | Exigence | Vérification |
|---|---|---|
| FR-1 | Retry 2× backoff sur erreurs retryables uniquement | test (comptage d'appels, délais 0 en test) |
| FR-2 | Chaîne de fallback ordonnée, inconnus ignorés + log, primaire jamais gated | test |
| FR-3 | CB : ouvre à N échecs, fail-fast, half-open 1 sonde, referme | test (horloge simulée via `Carbon::setTestNow` / cache array) |
| FR-4 | Fallback cloud gated par `AiCloudPolicy` sur tenant de session, fail-closed sans tenant | test (espion « jamais appelé ») |
| FR-5 | Dégradation explicite quand tout est épuisé/interdit | test |
| FR-6 | Flag OFF → binding = driver direct, parité stricte | test de parité |
| FR-7 | Tarifs en config, valeurs par défaut = parité avec l'existant | test coût |
| FR-8 | `response_format` pass-through (OpenAI/Groq), ignoré ailleurs | test payload (Http::fake) |
| FR-9 | Attribution `provider` réellement servant dans `AIResponse` + consommation par l'audit | test |

## 9. Exigences non fonctionnelles

- **Zéro changement consommateur obligatoire** : `Orchestrator`, `MarketingAiService`, services Communication — aucun appelant ne change. (Exception minimale et justifiée : `Orchestrator` lit `response->provider` avec repli sur `client->provider()` pour l'audit — FR-9, 4 sites, contrats inchangés.)
- **Budgets/quotas inchangés** : `AIRateLimiter` et `TokenBudgetGuard` restent en amont du client et fail-closed ; le décorateur ne les contourne pas (les retries/fallbacks ne se produisent qu'**après** admission).
- **Sanitization inchangée** (BOS-001) : le fallback réutilise les mêmes clients sanitisés — chaque payload sortant passe par le client du provider servant.
- **Aucune migration, aucune table** : état CB en Cache. Aucune donnée à rejouer.
- **Latence** : hors appels réseau, overhead du décorateur < 1 ms ; la latence pire cas est bornée par `Σ_candidats (1 + retries) × timeout 30 s` — l'exploitation doit garder la chaîne courte (recommandation : ≤ 2 entrées) et activer le CB (défauts choisis pour).
- **PHPStan** : niveau `max` sur les fichiers touchés (gate diff), strict sans nouvelle erreur.

## 10. Critères de succès (mappés 1:1 sur les CA de l'issue)

| CA issue | Preuve dans la PR |
|---|---|
| 1. Failover : provider A down → B répond, utilisateur servi | test Feature failover (US1) ; la démonstration staging reste l'étape Exit Gate Block 3 (hors PR, mentionnée) |
| 2. CB ouvre après N échecs, half-open, referme | test Feature CB (US2) |
| 3. `ai_cloud_allowed=false` → jamais de fallback cloud, dégradation explicite | test Feature cloud-policy (US3, matrice §4.2) |
| 4. Budgets/quotas toujours fail-closed ; coûts par provider depuis la config | suites `TokenBudgetTest` / `AIRateLimiter` vertes sans modification de contrat + test coût config (US4) |
| 5. Flag OFF = comportement strictement actuel | test de parité (US5) |

## 11. Hors périmètre (aussi contraignant que le périmètre)

- Routage par tâche (supprimé du plan — décision 08 C-4) ; **aucun nouveau provider** ; streaming (F7).
- Budgets/quotas (`AIRateLimiter`, `AiCreditService`, `TokenBudgetGuard`) : conservés en amont, **inchangés**.
- Timeouts des clients (30 s) : inchangés.
- Aucun changement de comportement pour `AI_RESILIENCE_ENABLED` absent/false.
- Aucune migration de base de données ; aucune nouvelle table.
- Démonstration staging de l'Exit Gate Block 3 (« panne Groq simulée ») : étape d'exploitation ultérieure, hors PR.

## 12. Sécurité & revue

- PR `api/app/AI` → **revue sécurité demandée explicitement** (règle programme §6).
- Aucune écriture sans confirmation humaine (le décorateur ne change rien au flux write-tools) ; aucun SQL généré par LLM (N/A).
- Journaux du décorateur : aucun prompt, aucune réponse, aucune donnée tenant — provider/statut/compteurs uniquement.
