# Plan technique — ResilientLLMClient (#8221 / BOS-031)

> Spec : `./spec.md`. Contrairement à BOS-010 (spec seule), l'issue #8221 livre
> **spec + implémentation dans la même PR** (« 1 issue = 1 PR », livrables 1 et 2 fusionnés par le programme 09 — MERGE BOS-030 → BOS-031).

## Architecture cible

```
AppServiceProvider::bind(LLMClient)
  │  ai.driver → client direct (match existant, INCHANGÉ)
  ▼
ai.resilience.enabled ?
  ├─ false (défaut) → driver direct            ← parité stricte, rollback instantané
  └─ true  → ResilientLLMClient(primary, fallbacks[], cb, policy, tenants, retry)
                │  implements LLMClient — zéro changement consommateur
                ▼
        chat(messages, tools, responseFormat)
          1. candidat = primaire (jamais gated), puis chaîne filtrée :
             cloud ? AiCloudPolicy::cloudAllowed(TenantManager::current()) : éligible
          2. CB fermé/half-open ? sinon candidat sauté (fail-fast)
          3. tentative + ≤2 retries (erreurs retryables : réseau, 429, 5xx)
             backoff 200ms×2ⁿ plafonné 2000ms
          4. succès → AIResponse (provider = candidat servant) ; compteur CB ← 0
             échec  → CB +1 ; seuil 3 → ouverture 60 s ; candidat suivant
          5. tous épuisés/interdits → dégradation explicite (erreur tracée, jamais muette)
```

## Composants

| Fichier | Rôle | Statut |
|---|---|---|
| `api/app/AI/ResilientLLMClient.php` | décorateur (retry, fallback, cloud-gate, orchestration CB) | NOUVEAU |
| `api/app/AI/Support/LLMCircuitBreaker.php` | état CB par provider en Cache (closed/open/half-open), best-effort | NOUVEAU |
| `api/app/AI/LLMClient.php` | interface : `?array $responseFormat = null` (3e param optionnel) | MODIFIÉ |
| `api/app/AI/DTOs/AIResponse.php` | `status` (?int) + `provider` (?string) optionnels en fin de constructeur ; `isRetryable()` | MODIFIÉ |
| `api/app/AI/Providers/{OpenAI,Groq,Claude}Client.php` | renseignent `status`/`provider` ; pass-through `response_format` (OpenAI/Groq) | MODIFIÉ |
| `api/app/AI/Providers/FakeLLMClient.php` | nouvelle signature ; `provider='fake'` | MODIFIÉ |
| `api/app/AI/AIAuditLogger.php` | `estimateCost` lit `ai.costs.*` (défauts = valeurs actuelles, parité) | MODIFIÉ |
| `api/app/Providers/AppServiceProvider.php` | binding : wrap conditionnel (flag ON) — construction des fallbacks, inconnus ignorés + log | MODIFIÉ |
| `api/app/AI/Orchestrator.php` | audit : `response->provider ?? client->provider()` (4 sites, FR-9) ; signature `chat()` privée alignée | MODIFIÉ (minimal) |
| `api/config/ai.php` | sections `resilience` + `costs` (additif) | MODIFIÉ |
| `api/tests/**` (12 fichiers, doublons `implements LLMClient`) | nouvelle signature (mécanique, comportement inchangé) | MODIFIÉ |
| `api/tests/Feature/AI/ResilientLLMClientTest.php` | failover, retry, CB, cloud-policy, dégradation, parité | NOUVEAU |
| `api/tests/Feature/AI/AICostRatesConfigTest.php` | tarifs config + parité défauts | NOUVEAU |
| `CHANGELOG.md` | entrée `[Unreleased]` / `### Added` | MODIFIÉ |

## Phases (au sein de la même PR)

| Phase | Contenu | Commit |
|---|---|---|
| 0 | claim marker + sync `main` | `8b9eddf2` (existant) + merge |
| 1 | spec.md / plan.md / tasks.md | `docs(spec): …` |
| 2 | DTO + interface + clients + signature des doublons de test | `feat(ai): …` |
| 3 | CB + décorateur + binding + config | idem |
| 4 | tarifs config + attribution provider (audit/Orchestrator) | idem |
| 5 | tests Feature + parité + CHANGELOG | idem |

## Risques / garde-fous

- **Latence pire cas** (chaîne × retries × timeout 30 s) → CB ouvert = fail-fast ; recommandation ops chaîne ≤ 2 ; timeouts clients hors périmètre.
- **Panne du Cache** (Redis down) → CB traité fermé (best-effort) : jamais de refus LLM à cause du CB ; documenté spec §5.3.
- **Régression de signature** (`LLMClient::chat`) → compilation PHP + suites Feature/AI existantes vertes ; les 12 doublons de test migrés dans la même PR (sinon CI rouge).
- **Fuite de politique cloud** → espion de test « jamais appelé » sur le client cloud ; `TenantManager::current()` seul (jamais d'argument LLM/utilisateur).
- **Parité coûts** → mêmes valeurs par défaut en config qu'en dur ; test dédié (modèle connu, inconnu → défaut).
- **Exclusivité zone Z2** (`app/AI`, `Orchestrator.php`, `config/ai.php`) : vérifiée libre à la prise en charge (16 PR contrôlées) ; BOS-032/034 ne démarrent qu'après merge.
