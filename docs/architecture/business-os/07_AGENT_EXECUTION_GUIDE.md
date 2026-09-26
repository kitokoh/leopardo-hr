# AGENT EXECUTION GUIDE — Programme Business OS

> Date : 2026-09-26 · Public : développeurs et agents exécutant les issues BOS-xxx
> Ce guide complète — ne remplace pas — `AGENTS.md`, `CONTRIBUTING.md`, `CONVENTIONS.md` et la constitution spec-kit du repo.

---

## 1. Règles d'engagement (avant toute issue)

1. **Lire l'issue complète** : scope ET hors scope. Le hors scope est aussi contraignant que le scope.
2. **Respecter le processus repo** : auto-assignation + marker branch anti-doublon (#2400) ; spec-first pour toute issue marquée `[architecture]` ou touchant un registre ; CHANGELOG Keep-a-Changelog obligatoire ; CI verte (tests, PHPStan, gardes).
3. **REUSE → EXTEND → REFACTOR → CREATE.** Avant de créer une classe/table/route : chercher l'existant (`rg`). Toute duplication introduite est un motif de refus de PR.
4. **Multi-tenant inviolable** : tout modèle métier avec `BelongsToCompany` ; jamais de requête cross-tenant hors bypass nommés audités ; jamais de tenant dérivé d'une entrée utilisateur/LLM — uniquement de la session.
5. **L'IA n'écrit jamais sans confirmation humaine** et ne génère jamais de SQL. Toute PR qui contourne ces deux règles est refusée par principe.
6. **Migrations additives uniquement**, sans downtime ; dual-read quand une source de vérité change.
7. **Une issue = une PR** (ou une chaîne de PR explicite). Pas de drive-by refactoring, surtout dans le cœur HR.

## 2. Attribution par type d'agent

| Type d'agent | Issues typiques | Zones autorisées en autonomie |
|---|---|---|
| Backend core (senior) | BOS-011, 012, 013, 023, 027 | `api/app/Core`, `api/app/Shared` — **exclusivité séquentielle** |
| Backend modules | BOS-020, 021, 024a–g, 026 | `api/app/Modules/<un seul module>` à la fois |
| Backend/AI | BOS-001, 004, 031, 032, 034, 041, 042 | `api/app/AI`, `config/ai.php` — 1 agent à la fois |
| Frontend | BOS-035, 043 | `front/web` uniquement |
| Mobile | BOS-033 (partie client) | `front/mobile_apps` |
| DevOps | BOS-003 | `render*.yaml`, workflows, scripts |
| QA | BOS-022, 044 | `api/tests`, plans de test |
| Security | BOS-001, 034 (revue obligatoire sur toute PR `api/app/AI`) | revue |
| Architecte/PM | BOS-010, 030, 036, 040, 050, 051 | specs, ADR — **pas de code de production** |
| Documentation | en support de chaque issue | `docs/` |

## 3. Coordination anti-collision

- **Zones à exclusivité** (1 agent à la fois, voir `06_DEPENDENCY_GRAPH.md` §5) : `app/Core`, `app/AI`, migrations tenant, Planning.
- **Avant de commencer** : vérifier les branches ouvertes (`marker branch`), annoncer la prise de l'issue, vérifier le graphe de dépendances — une issue dont la dépendance n'est pas mergée ne démarre pas (sauf contrat mocké explicitement prévu, ex. BOS-043).
- **Specs** : les issues `[architecture]` produisent une spec dans `.specify/features/` AVANT toute issue d'implémentation fille ; utiliser les commandes existantes (`speckit-specify → clarify → plan → tasks`). Ne pas créer de nouveau système de specs.
- **Registres de gouvernance** : si l'issue ajoute/retire un module ou un BC → mettre à jour `BOUNDED-CONTEXT-REGISTRY` + `dev-hub/governance/bounded-context-registry.json` (gardés en CI) et le registre unifié (post BOS-011).

## 4. Definition of Done (toutes issues)

- [ ] Critères d'acceptation de l'issue tous satisfaits, démontrés par des tests.
- [ ] Suite de tests verte en CI (y compris tests d'architecture et gardes `dev-hub/tools/check-*.sh`).
- [ ] Couverture : pas de baisse sous le gate (65 %) ; les issues de dette doivent l'augmenter.
- [ ] Sécurité : pas de régression tenant-scope ; revue security si `app/AI`, auth, ou endpoints publics.
- [ ] Migration : additive, documentée (stratégie + rollback + impact données + downtime = 0).
- [ ] CHANGELOG + docs touchées (`docs/architecture/`, `docs/ai/` si concerné) synchronisées avec le code.
- [ ] Hors scope respecté ; aucune classe/table/route non prévue par l'issue.
- [ ] Pour les issues IA : matrice de permissions, audit, budgets — tests de garde mis à jour.

## 5. Comment vérifier la valeur (par phase)

| Phase | Vérification objective |
|---|---|
| 0 | Test PII rouge→vert ; job planifié survit 7 j en prod ; `AbsenceApproved` émis via IA |
| 1 | Ajouter un module fictif en staging = 1 seul fichier modifié ; parité feature map sur tous les tenants |
| 2 | `module-isolation-allowlist.txt` ≤ 35 paires ; Planning ≥ 30 tests ; outbox unique utilisée par ≥ 2 modules |
| 3 | Couper Groq en staging → l'assistant répond via fallback ; flux write complet depuis le web avec confirmation |
| 4 | Provisioning « école » et « restaurant » de bout en bout via langage naturel en pilote ; plan == plan déterministe |
| 5 | Le contrat PublicOffer est implémenté par 2 verticales sans duplication de code de transport |

## 6. Escalade

- Désaccord d'architecture → ADR court dans `docs/architecture/adr/` + revue owner. Ne jamais « contourner » une décision par le code.
- Découverte en cours d'issue d'un doublon non cartographié → ne pas corriger dans la PR ; ouvrir une issue `lecon`/`constat` et le référencer.
- Blocage externe (budget, hébergeur, compte provider) → remonter immédiatement ; ne pas construire de workaround temporaire qui deviendrait permanent.
