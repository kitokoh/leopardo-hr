# 12 — PRIORITES & ROADMAP D'EXECUTION

**Objectif :** Definir l'ordre d'execution optimal, les dependances entre modules, et une timeline realiste.

---

## 1. Principes de priorisation

1. **Ce qui fait vendre d'abord** — Paie et conges sont les plus demandes
2. **API d'abord, interfaces ensuite** — Chaque module commence par le backend
3. **Tests avec chaque livraison** — Pas de code sans test
4. **Pas de big bang** — Livraison incrementale, 1 module a la fois
5. **La base avant les extras** — Architecture solide avant IA et tracking

---

## 2. Roadmap par sprint (2 semaines/sprint)

### Sprint 1-2 : Fondations (Semaines 1-4)

**Objectif :** Solidifier la base pour supporter tous les modules.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Structure DDD pour nouveaux modules | 01 T-ARCH-01/02/03 | Haute | 2j |
| Event System (domain events + audit listener) | 01 T-ARCH-04/05/06/07/08 | Haute | 3j |
| Index de performance DB | 01 T-ARCH-14 | Haute | 0.5j |
| Middleware RequestId | 07 T-MON-04 | Moyenne | 0.5j |
| Health check enrichi | 07 T-MON-01/02 | Moyenne | 1j |
| Docker Compose pour dev | 10 T-OSS-01 | Haute | 1j |
| DEVELOPMENT.md | 10 T-OSS-03 | Haute | 0.5j |
| Configurer Sentry performance | 07 T-MON-06 | Moyenne | 0.5j |

**Livrable :** Base solide, dev setup en 1 commande, observabilite basique.

### Sprint 3-4 : Paie complete (Semaines 5-8)

**Objectif :** Paie legale fonctionnelle pour DZ + MA.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Migrations paie (7 tables) | 03 T-PAIE-01 | Critique | 1j |
| Modeles paie | 03 T-PAIE-02 | Critique | 1j |
| Seeders config DZ + MA | 03 T-PAIE-03 | Critique | 1j |
| PayrollCalculator + DZ rules | 03 T-PAIE-04/05 | Critique | 3j |
| MA rules | 03 T-PAIE-05 | Haute | 1j |
| Endpoints API paie | 03 T-PAIE-10 | Critique | 2j |
| FormRequests + Resources | 03 T-PAIE-11 | Haute | 1j |
| Policies | 03 T-PAIE-12 | Haute | 0.5j |
| PaySlip PDF generator | 03 T-PAIE-07 | Haute | 2j |
| Tests Feature paie | 03 T-PAIE-13/14 | Critique | 2j |
| Self-service /me/pay-slips | 03 T-PAIE-15 | Moyenne | 0.5j |

**Livrable :** Paie DZ + MA complete avec bulletins PDF.

### Sprint 5-6 : Conges avances + Contrats (Semaines 9-12)

**Objectif :** Leave management complet + contrats de travail.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Migrations conges (4 tables) | 02 Module A | Haute | 1j |
| Modeles + endpoints conges | 02 Module A | Haute | 3j |
| Politiques, accrual, carry forward | 02 Module A | Haute | 2j |
| Workflow approbation generique | 09 T-OBD-02/03 | Haute | 2j |
| Tests conges | 02 Module A | Haute | 1j |
| Migrations contrats (2 tables) | 02 Module B | Moyenne | 0.5j |
| Modeles + endpoints contrats | 02 Module B | Moyenne | 2j |
| PDF contrat + alertes expiration | 02 Module B | Moyenne | 1j |
| Tests contrats | 02 Module B | Moyenne | 1j |

**Livrable :** Leave management enterprise-grade + contrats avec alertes.

### Sprint 7-8 : IA Phase 1 + Blog (Semaines 13-16)

**Objectif :** Chat IA fonctionnel + blog vitrine pour le SEO.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Config IA + migrations | 04 T-IA-01/02 | Haute | 1j |
| LLMClient + providers | 04 T-IA-03 | Haute | 2j |
| Tool Registry + Intent Engine | 04 T-IA-04/05 | Haute | 2j |
| AI Orchestrator + Memory | 04 T-IA-06/07 | Haute | 2j |
| AI Gateway controller + middlewares | 04 T-IA-09/10 | Haute | 1j |
| Seeder Tool Registry | 04 T-IA-11 | Moyenne | 0.5j |
| Tests IA | 04 T-IA-13/14 | Haute | 1j |
| Blog MDX setup | 06 T-VITRINE-01/02 | Haute | 2j |
| Pages pricing + demo | 06 T-VITRINE-03/04 | Haute | 1j |
| SEO (sitemap, robots, schema) | 06 T-VITRINE-05 | Moyenne | 0.5j |
| 5 premiers articles blog | 11 T-GTM-06 | Haute | 3j |

**Livrable :** Chat IA operationnel + blog avec contenu SEO.

### Sprint 9-10 : Tracking vehicules + Notes de frais (Semaines 17-20)

**Objectif :** Integration Traccar + module expense claims.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Config Traccar | 05 T-TRACK-01/02 | Haute | 1j |
| Migrations tracking (5 tables) | 05 T-TRACK-03 | Haute | 1j |
| TraccarService + sync | 05 T-TRACK-05/08 | Haute | 3j |
| Controllers vehicules + fleet | 05 T-TRACK-06/07 | Haute | 2j |
| Alertes vehicules | 05 T-TRACK-09 | Moyenne | 1j |
| Tests tracking | 05 T-TRACK-10/11/12 | Haute | 1j |
| Module notes de frais | 02 Module F | Moyenne | 3j |
| Tests notes de frais | 02 Module F | Moyenne | 1j |

**Livrable :** Flotte GPS operationnelle + notes de frais.

### Sprint 11-12 : Recrutement + Formation (Semaines 21-24)

**Objectif :** ATS basique + LMS basique.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Module recrutement (3 tables + endpoints) | 02 Module C | Moyenne | 3j |
| Tests recrutement | 02 Module C | Moyenne | 1j |
| Module formation (3 tables + endpoints) | 02 Module D | Moyenne | 3j |
| Tests formation | 02 Module D | Moyenne | 1j |
| Module prets employes | 02 Module E | Basse | 2j |
| Organigramme API | 02 Module G | Moyenne | 1j |
| Rapports RH avances | 02 Module H | Moyenne | 2j |

**Livrable :** Recrutement pipeline + formation catalogue + prets + rapports.

### Sprint 13-14 : Webhooks, Billing, Audit (Semaines 25-28)

**Objectif :** API publique, billing automatique, audit complet.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Module webhooks | 02 Module I | Moyenne | 2j |
| Module audit trail | 02 Module J | Haute | 2j |
| Integration Stripe/Chargily | 09 T-OBD-04/05/06 | Haute | 3j |
| Invoices + PDF | 09 T-OBD-11 | Haute | 1j |
| Feature flags matrice | 09 T-OBD-09 | Haute | 1j |
| Onboarding enrichi | 09 T-OBD-01 | Moyenne | 1j |
| Tests billing + webhooks | 09 T-OBD-12/13 | Haute | 1j |

**Livrable :** Billing automatique + webhooks + audit complet.

### Sprint 15-16 : Interfaces (Semaines 29-32)

**Objectif :** Dashboard admin et mobile completement branches sur les nouveaux modules.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Dashboard paie (structures, runs, bulletins) | 06 T-WEB-03 | Haute | 4j |
| Dashboard conges + contrats | 06 T-WEB-04/05 | Haute | 3j |
| Dashboard recrutement (Kanban) | 06 T-WEB-06 | Moyenne | 3j |
| Dashboard tracking (carte live) | 06 T-WEB-08 | Haute | 3j |
| Widget chat IA | 06 T-WEB-09 | Haute | 2j |
| Mobile bulletins + conges | 06 T-MOB-01/02 | Haute | 3j |
| Mobile chat IA | 06 T-MOB-06 | Haute | 2j |

**Livrable :** Interfaces completes pour tous les modules.

### Sprint 17-18 : IA avancee + Polish (Semaines 33-36)

**Objectif :** Voice IA + agents + export bancaire + polish general.

| Tache | Fichier ref | Priorite | Effort |
|-------|------------|----------|--------|
| Voice IA (STT + TTS) | 04 T-IA-20/21/22/23 | Moyenne | 3j |
| Agents autonomes | 04 T-IA-26/27/28 | Basse | 3j |
| Export SEPA + CCP | 03 T-PAIE-08/09 | Haute | 2j |
| Paie TN, FR, TR, SN | 03 T-PAIE-05 | Moyenne | 3j |
| E2E Playwright | 08 T-CI-02/11 | Haute | 2j |
| Polish UI/UX | - | Moyenne | 3j |

**Livrable :** Plateforme complete, testee, deployee.

---

## 3. Diagramme de dependances

```
                    ┌─────────────────┐
                    │  01 Fondations  │
                    │  (Sprint 1-2)   │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
    ┌─────────▼──────┐  ┌───▼──────────┐  ┌▼──────────────┐
    │ 03 Paie        │  │ 02 Conges    │  │ 10 Open Source │
    │ (Sprint 3-4)   │  │ (Sprint 5-6) │  │ (Continu)      │
    └────────┬───────┘  └──────┬───────┘  └───────────────┘
             │                 │
    ┌────────▼─────────────────▼────────┐
    │ 04 IA Phase 1 + 06 Blog          │
    │ (Sprint 7-8)                      │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ 05 Tracking + Notes de frais      │
    │ (Sprint 9-10)                     │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Recrutement + Formation + Prets   │
    │ (Sprint 11-12)                    │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Webhooks + Billing + Audit        │
    │ (Sprint 13-14)                    │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Interfaces completes              │
    │ (Sprint 15-16)                    │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ IA avancee + Polish + Deploy      │
    │ (Sprint 17-18)                    │
    └───────────────────────────────────┘
```

---

## 4. Criteres de validation par module

Chaque module est considere "done" quand :

- [ ] Tous les endpoints API sont implementes et documentes dans OpenAPI
- [ ] Tous les tests Feature passent (coverage > 80% du module)
- [ ] Les migrations sont idempotentes (PostgreSQL/Render safe)
- [ ] Les Policies RBAC sont en place
- [ ] Les messages de validation sont i18n (FR, AR, EN minimum)
- [ ] Le CHANGELOG.md est mis a jour
- [ ] L'AGENTS.md est mis a jour si une lecon operationnelle est apprise
- [ ] Le module est derriere un feature flag
- [ ] Un test E2E couvre le parcours principal (quand l'interface existe)

---

## 5. Metriques de progression

| Metrique | Actuel | Cible Sprint 18 |
|----------|--------|----------------|
| Endpoints API | ~135 | ~350 |
| Modeles | 30 | ~70 |
| Tests | 75 | ~250 |
| Coverage backend | ~40% | >80% |
| Modules | 8 | 18 |
| Pays paie supportes | 0 (estimation only) | 6 (DZ, MA, TN, FR, TR, SN) |
| Langues | 4 (FR, AR, TR, EN) | 4 (stable) |
| Workflows CI | 10 | 14 |

---

## 6. Risques et mitigations

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Dev solo overload | Critique | Open source + agents IA + prioriser ruthlessly |
| Conformite paie incorrecte | Haut | Validation par comptable local avant deploiement |
| Traccar instable | Moyen | Fallback sans tracking, module optionnel |
| LLM couts explosent | Moyen | Quotas par plan + cache responses + model switching |
| Pas assez de contributeurs | Haut | Good first issues + Docker setup + documentation |
| Client perd des donnees | Critique | Backup automatique + tests de restore |
| Concurrent local copie | Moyen | Avancer vite, construire la communaute |

---

## 7. Definition de la victoire technique

Leopardo RH est "enterprise-grade" quand :

1. Un client peut faire sa paie legale complete sans Excel
2. Un manager terrain peut piloter ses equipes depuis son telephone
3. Un comptable peut exporter les donnees vers sa comptabilite
4. Un RH peut gerer les conges, contrats et recrutement sans papier
5. Un dirigeant peut voir les KPIs en temps reel
6. Un developpeur peut contribuer en moins de 30 minutes (Docker + docs)
7. La plateforme tient 100 clients / 10 000 employes sans degradation
8. Les tests couvrent > 80% du code
9. Le deploy est automatique et rollback-safe
10. L'IA repond aux questions RH courantes en 4 langues
