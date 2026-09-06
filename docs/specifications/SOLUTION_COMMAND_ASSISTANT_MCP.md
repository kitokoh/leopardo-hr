# Spécification — Assistant Intelligence : commandes texte & voix (BC-23 AI)

- **Statut :** validée par le fondateur le 2026-09-06 (commentaire EPIC #6846) — document canonique pour l'implémentation
- **BC :** BC-23 AI (`api/app/AI` + `api/app/Core/AI`) — **aucun nouveau module**
- **Issues liées :** EPIC #6846 — programme #6847 → #6861
- **Références :** `api/ARCHITECTURE.md`, `AGENTS.md`, `.specify/constitution.md`, conception « Module Intelligence / MCP » (2026-09-05)

---

## 1. Vision

Un manager (ou tout utilisateur, selon son rôle) donne une **instruction en langage naturel — texte ou voix** — depuis l'application (« combien d'absences cette semaine ? », « approuve la demande de congé de Dupont », « préviens l'équipe du site A »). L'assistant :
1. comprend l'intention,
2. **propose des actions d'outils** (jamais plus),
3. les exécute **après vérification serveur** (permissions RBAC + confirmation si effet de bord),
4. répond en langage naturel,
5. **trace tout** (conversation → action → effet, marqueur « exécuté via assistant IA »).

**Principe d'architecture (le cœur) : chaque BC est propriétaire de ses outils ; BC-23 est un hôte qui orchestre — il ne code rien en dur, n'importe jamais les classes d'un autre BC, et n'accède à leur logique que par le contrat d'outil déclaré.**

## 2. Cas d'usage v1

| # | Rôle | Instruction | Résultat |
|---|---|---|---|
| US1 | Manager / RH | « Combien d'absences dans mon équipe cette semaine ? » | Lecture agrégée + réponse |
| US2 | Manager / RH | « Quel est le solde de congés de Dupont ? » | Lecture (scope équipe) |
| US3 | RH / principal | « Où en est la paie ? » | Statut agrégé du run en cours (jamais de bulletins) |
| US4 | Manager autorisé | « Approuve la demande de congé de Dupont (demain) » | **Confirmation** → exécution via le workflow métier |
| US5 | Manager autorisé | « Pose un shift à Martin mardi 9 h–17 h » | **Confirmation** → exécution |
| US6 | Manager / RH | « Préviens l'équipe du site A » | **Confirmation** → notification (BC-13) |
| US7 | RH | « Quels outils as-tu ? » | Liste des outils autorisés du profil |

Hors v1 : actions écriture sans confirmation, autres langues que FR (interface), TTS, agent autonome multi-tours non borné.

## 3. Placement & frontières

- **Hôte :** BC-23 étend l'existant `api/app/AI` (Orchestrator, AgentRunner, AIGatewayController…) — on **réutilise, on ne réécrit pas** (inventaire §8).
- **Contrat d'outil :** défini en BC-23 (`AIToolDefinition`), **déclaré par chaque BC propriétaire** dans son propre périmètre (convention de localisation) — découverte dynamique par profil.
- **Interdits :** `use App\Modules\<X>` dans `app/AI` hors contrats (garde isolation #5584) ; accès DB direct d'un autre BC ; exécution d'outil sans le middleware permissions.
- **Multi-tenant & RBAC :** contexte tenant (middleware existant) + Policy du BC propriétaire **re-vérifiée à l'exécution** — jamais de confiance au LLM.

## 4. Architecture

```
Manager (leopardo_manager / API)
   │  texte ──────────────┐      voix (bouton micro)
   ▼                      ▼
POST /ai/chat       POST /ai/voice/transcribe ──► SpeechToTextPort (Groq-Whisper v1)
   │                      │
   └──────── texte ───────┘
              ▼
   Orchestrateur (existant, étendu)
      │ LLM via LanguageModelDriver (config/ai.php : fake|groq|openai|claude|ollama)
      ▼
   Proposition tool_call(s) [schémas AIToolDefinition du profil]
      ▼
   ToolExecutionGuard (serveur) : tenant ✓ + Policy ✓ + sensibilité
      ├─ read        → exécution directe
      └─ write|send  → PendingAction → « Voulez-vous exécuter X ? » → confirm|reject (existant)
      ▼
   Exécution via l'outil du BC (jamais en direct) + PrivacySanitizer (si driver cloud)
      ▼
   Réponse naturelle (FR) + AIAuditLog (chaîne conversation→action→effet)
```

## 5. Contrat d'outil — `AIToolDefinition` (standard façon MCP)

```php
AIToolDefinition {
  name:        string,          // snake_case unique, ex. absence_decision
  description: string,          // pour le LLM (FR) — quand l'utiliser
  inputSchema: object,          // JSON Schema 2020-12 (types, required, enum…)
  outputSchema: object,         // JSON Schema (shape agrégée, jamais de PII brute)
  permission:  string,          // gate/Policy requise (BC propriétaire)
  sensitivity: 'read'|'write'|'send',
  bc:          string,          // BC propriétaire (ex. BC-06)
  version:     int
}
```

- **Déclaré par le BC propriétaire** (fichier/registre local au BC) ; BC-23 le découvre et le filtre par profil — un nouveau BC ajoute ses outils **sans toucher au code de BC-23**.
- Rétrocompat : refactor progressif de `AIToolRegistryEntry` existant vers ce contrat (issue #6850).
- Exemple (schéma) : cf. §5.2 de l'issue #6850.

## 6. Pipeline, permissions, confirmation

1. Texte (ou audio → STT) ; contexte tenant/rôle résolu côté serveur.
2. Appel LLM avec les **schémas des outils autorisés du profil** (le LLM ne voit jamais les autres).
3. Le LLM renvoie 0..n `tool_call`s **structurés** (tool-calling natif Groq/OpenAI).
4. **`ToolExecutionGuard`** (serveur, non contournable) : outil existe ? tenant conforme ? Policy du BC propriétaire OK ? budget tokens OK ?
5. Sensibilité : `read` → exécuter ; `write`/`send` → créer une `PendingAction` (endpoint existant `/ai/actions/{id}/confirm|reject`).
6. Exécution via l'outil (le BC propriétaire fait ses propres validations métier + événements).
7. Résultat → LLM → réponse FR ; **audit complet**.

Bornes : boucle ≤ 4 itérations, budget tokens (`TokenBudgetExceededException` existante), timeout global, rate limits existants (`ai-sensitive`, `api-plan`), feature flag `AIFeatureCheck`.

## 7. API

**Existantes (réutilisées) :** `POST /ai/chat`, `/ai/chat/history`, `DELETE /ai/chat/{id}`, `POST /ai/actions/{id}/confirm|reject`, `GET /ai/tools`, `POST /ai/voice/transcribe|synthesize|command`, `POST /ai/agent/run`, `GET /ai/workflows/*`, `GET /ai/analytics/*` (réservé principal/rh).
**Évolutions v1 (issues #6848–#6860) :**
- `GET /ai/tools` → filtré par profil via le nouveau contrat (découverte BC).
- `POST /ai/voice/transcribe` → branché sur `SpeechToTextPort` (fail-closed 503 `STT_UNAVAILABLE` si pas de clé).
- Réponses et erreurs **i18n** (`__()`), codes d'erreur stables (pattern `ApiError`).

## 8. Inventaire réutilisé (vérifié sur main 2026-09-05)

`api/app/AI` : `Orchestrator.php`, `AgentRunner.php`, `IntentEngine.php`, `LLMClient.php`, `MemoryManager.php`, `PendingActionStore.php`, `AIAuditLogger.php`, `Providers/{OpenAIClient,ClaudeClient}.php`, `Interfaces/Api/V1/Controllers/{AIGatewayController,AgentController,VoiceController,AIAnalyticsController,ConversationExportController}.php`, `Jobs/ExportAiConversationJob.php`, modèles `AIConversation`, `AIAuditLog`, `AIToolRegistryEntry`, `AiDeadLetterEntry`, `AiExport`, exceptions `TokenBudgetExceededException`/`ToolPermissionDeniedException`, middlewares `AIFeatureCheck`, `AITenantInjector`, `AIRateLimiter`, `EnsureAIAnalyticsAccess`, routes `routes/ai.php`. `api/app/Core/AI` : pattern de **ports** (contrats + ValueObjects + adaptateurs Fake/Unavailable fail-closed) à imiter pour STT. Garde : `AIOrchestrator` n'existe pas (AGENTS.md).

## 9. Stack LLM / STT (zéro API payante obligatoire)

- **Driver par défaut en prod : `groq`** si `GROQ_API_KEY` posée (free tier ; llama-3.3-70b FR correct) — sinon `fake` hors prod / erreur actionnable en prod (jamais de 500 muet).
- `config/ai.php` : `AI_LLM_DRIVER` (fake|groq|openai|claude|ollama), clés via env (`.env.example`), timeouts, budget tokens. `Providers/GroqClient` = API OpenAI-compatible → logique partagée avec le client OpenAI existant.
- **STT :** `Core/AI/Domain/Contracts/SpeechToTextPort` + `GroqWhisperAdapter` (whisper-large-v3) v1 ; `FakeSpeechToTextAdapter` pour tests ; adaptateur local (faster-whisper) en option plus tard.
- Aucune clé commitée (secret scanning TruffleHog en CI).

## 10. RGPD & minimisation (P0)

- **`PrivacySanitizer`** appliqué à tout payload sortant vers un driver cloud : identifiants internes plutôt que PII, agrégats, jamais `national_id`/salaires/données de santé (cf. whitelist déjà existante côté `EmployeeResource`, #6546).
- **Toggle par tenant** via feature flags (`Core/Feature`) : `ai_cloud_allowed` — défaut **off** pour les drivers cloud ; réponse explicite si refusé. Drivers locaux/fake sans restriction.
- Registre RGPD (`docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`) : entrée « assistant IA » à la livraison.

## 11. i18n & UX

- Interface FR v1 (catalogues existants, garde PA2-I18N-007) ; réponses du LLM en FR (prompt système) ; transcriptions FR (whisper large multilingue).
- Confirmations d'action explicites dans l'UI (boutons Approuver/Refuser), jamais d'ambiguïté sur l'effet.

## 12. Hors périmètre v1 & phases suivantes

- **Phase 2 (spike #6861)** : exposition d'un BC pilote en **serveur MCP standard** (SDK PHP) pour clients externes — décision écrite, pas de code prod.
- Plus tard : TTS (Piper ou équivalent), autres langues, outils écriture élargis, agent planifié (workflows), assistant vocal kiosque.

## 13. Séquencement & issues

| Lot | Contenu | Issues |
|---|---|---|
| I1 | Spec (ce document) | #6847 |
| A | Drivers LLM (#6848), STT (#6849), contrat d'outil (#6850), permissions (#6851), audit (#6852), privacy (#6853) | A1→A6 |
| B | Outils HR (#6854), Payroll (#6855), Leave (#6856), Workforce (#6857), Comms (#6858) | B1→B3c |
| C | Boucle agent (#6859), mobile manager (#6860) | C1–C2 |
| D | Spike MCP externe (#6861) | D1 |

Ordre : A dans l'ordre (A3/A4 critiques), puis B, puis C ; D indépendant.

## 14. Risques

| Risque | Mitigation |
|---|---|
| Hallucination / mauvaise action | Outils typés + schémas stricts + confirmation obligatoire (write/send) + guard serveur |
| Fuite de données RH vers le cloud | PrivacySanitizer + toggle tenant (défaut off) + audit |
| Quotas free tier Groq | Drivers interchangeables (fallback local plus tard) ; compteurs d'usage ; limites par tenant |
| Latence | Bornes (itérations, timeout), réponses partielles possibles (jobs async pour les traitements longs) |
| Dérive de périmètre (« l'IA fait tout ») | Outils v1 limités et déclarés par BC ; toute action hors contrat = refus |

## 15. Critères de qualité & tests

- Tests feature avec **LLM fake scripté** : lecture → réponse ; écriture → confirmation → exécution ; outil inconnu/hors droits → refus + audit ; cross-tenant → refus ; itérations max → arrêt propre ; pas de clé STT → 503 actionnable.
- PHPStan strict L8 0 sur delta ; Pint ; gardes isolation #5584 vertes ; CHANGELOG ; i18n.
