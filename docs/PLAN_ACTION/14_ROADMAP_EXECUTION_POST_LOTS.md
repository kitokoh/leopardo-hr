# 14 - ROADMAP D'EXECUTION ACTUALISEE (Post lots 4-5)

**Date :** 2026-05-13
**Contexte :** Ce document enrichit le plan d'action apres l'implementation des lots plateforme metrics backend et dashboard admin. Il ne remplace pas `13_RESTANT_POST_SPRINTS.md` : il transforme l'inventaire en sequence d'execution pragmatique, orientee API solide, ventes, scalabilite et dette technique.

---

## 1. Retour d'experience implementation

### Lots completes

| Lot | Surface | Livraison | Validation |
|---|---|---|---|
| Lot 4 | Backend API | `GET /api/v1/platform/metrics/overview` protege par `super_admin_api` | Backend, coverage, quality, security, CodeQL, governance verts |
| Lot 5 | Admin dashboard | Cockpit et abonnements branches sur `/platform/metrics/overview` | Web lint, build, Playwright, governance verts |

### Ce que ces lots confirment

- L'API est bien le socle du produit : une fois le contrat backend propre, le dashboard peut consommer les memes chiffres sans recalcul local fragile.
- Le cockpit super-admin doit piloter le business avec des agregats fiables : MRR, ARR, encaissements, impayes, companies, subscriptions et billing.
- Les workflows GitHub Actions sont la source de verite : le poste Windows local peut etre limite par `mbstring` PHP ou une installation npm lente, mais les checks CI valident correctement le code.
- Vercel reste un statut externe bruyant et non representatif pour les PR backend/admin tant que le projet Vercel historique n'est pas realigne.

---

## 2. Etat actuel actualise

### Excellent

- Backend Laravel API riche, multi-tenant, deja fortement teste sur les parcours critiques.
- Migrations Render durcies : idempotence, transactions desactivees sur les migrations sensibles, scenarios de race mieux documentes.
- Auth plateforme super-admin et 2FA mieux contractuels.
- Health, logging structure, request id et metrics commencent a former une base observabilite exploitable.
- Dashboard admin consomme de plus en plus des contrats reels au lieu de widgets mockes.
- Vitrine multilingue dispose deja d'un rail FR/EN/TR/AR.

### Correct mais a consolider

- La couverture backend progresse, mais tous les modules post-sprints n'ont pas encore une couverture feature uniforme.
- Les FormRequests, Resources et Policies existent sur de nombreux modules, mais il faut continuer l'audit par module pour eliminer les restes inline.
- Le dossier `docs/PLAN_ACTION` est riche, mais certains items coches doivent etre relies a des preuves CI et endpoints reels.
- La strategie i18n enterprise existe, mais la synchronisation backend/web/mobile doit rester prioritaire pour eviter les divergences.

### Fragile

- Deux frontends coexistent (`front/admin-dashboard` et `front/web`) avec des workflows distincts : toute confusion de chemins peut casser la CI ou les deploys.
- Certaines donnees billing vivent dans `shared_tenants`; il faut continuer a qualifier les tables pour eviter les faux positifs PostgreSQL via `search_path`.
- Les dashboards commencent a etre API-first, mais il manque encore une couche client typee ou un SDK interne pour stabiliser les contrats.
- L'installation locale Windows n'est pas totalement reproductible pour PHP/npm, ce qui augmente la dependance a GitHub Actions.

### Critique

- L'API doit rester la priorite absolue : mobile, vitrine, admin, kiosk et future IA dependent de sa stabilite.
- OpenAPI/contrats API restent trop partiels par rapport au nombre d'endpoints.
- La gestion des permissions doit rester verifiee par tests sur chaque nouveau module, notamment IA, billing, paie et documents.
- Les metrics business doivent evoluer vers du pilotage decisionnel : churn, activation, adoption, recouvrement, support et upsell.

---

## 3. Risques prioritaires

| Niveau | Risque | Impact | Reponse recommandee |
|---|---|---|---|
| Critique | Contrats API non documentes ou divergents entre frontends | Regressions mobile/admin/kiosk | Generer et maintenir OpenAPI + tests contractuels |
| Critique | RBAC incomplet sur modules monetisables | Fuite ou action interdite | Tests Feature par role + policies systematiques |
| Eleve | Billing/paie insuffisamment testes en edge cases | Perte de confiance client | Suite feature paie/billing prioritaire avec donnees multi-pays |
| Eleve | Donnees tenant mal qualifiees | Cross-tenant ou erreurs PostgreSQL | Helpers de table schema-aware + tests PostgreSQL CI |
| Moyen | Dashboard sans SDK type | Fragilite a chaque evolution API | Couche API client centralisee + types generes |
| Moyen | Observabilite sans alerting business | Incidents detectes tard | Alertes health, queue, billing overdue, erreurs 5xx |
| Faible | Dette locale Windows | Productivite agent/dev | DevContainer ou script setup robuste |

---

## 4. Plan d'execution par lots

### Lot 6 - API Contracts & OpenAPI

**Objectif :** rendre les contrats API auditables par humains, frontends et future couche IA.
**Impact business :** reduit les regressions front/mobile et accelere les integrations tierces.
**Effort estime :** 2-4 jours.

Livrables :

- Completer `api/openapi.yaml` pour les contrats plateforme recents : auth, health, plans, subscription, company requests, metrics overview.
- Ajouter une verification CI minimale de validite OpenAPI.
- Documenter les shapes `data`, `meta`, erreurs `401/403/422`.
- Aligner le dashboard admin sur ces contrats et noter les endpoints non documentes restants.

### Lot 7 - Test Coverage Core Business

**Objectif :** augmenter la confiance sur modules qui portent la valeur commerciale.
**Impact business :** evite les incidents chez les clients payants.
**Effort estime :** 5-8 jours par tranche.

Ordre recommande :

1. Billing : subscriptions, invoices, payments, webhooks.
2. Payroll : runs, payslips, bank exports, PDFs, multi-pays.
3. Attendance : anomalies, monthly report, geofence, actions manager.
4. Absences/leave policies : balances, accrual, approval.
5. IA : permissions, audit logs, couts, tool registry read-only.

### Lot 8 - Admin Dashboard Enterprise

**Objectif :** transformer l'admin en cockpit operationnel, pas seulement une vitrine interne.
**Impact business :** support, retention, recouvrement et upsell plus rapides.
**Effort estime :** 8-15 jours.

Livrables prioritaires :

- Vue finance plateforme : MRR/ARR, overdue, encaissements, trials, past due.
- Vue companies : filtres risque/statut/plan, export CSV, actions rapides.
- Vue support : qualification, notes internes, historique decisions.
- Vue audit/logs : erreurs API, activite super-admin, actions sensibles.
- Playwright E2E sur login, cockpit, company detail, subscription update.

### Lot 9 - Mobile & Kiosk Contract Sync

**Objectif :** verifier que mobile et kiosk consomment les memes contrats API que le web.
**Impact business :** stabilite terrain.
**Effort estime :** 6-12 jours.

Livrables :

- Matrice endpoints mobile/kiosk vs backend.
- Tests contractuels JSON pour auth, attendance, absences, payslips, notifications.
- Strategie offline/cache mobile pour i18n, pointage et actions critiques.
- Verification RTL arabe et textes longs sur petits viewports.

### Lot 10 - I18N Enterprise Sync

**Objectif :** centraliser durablement FR/AR/EN/TR sur backend, web, mobile, emails et notifications.
**Impact business :** internationalisation vendable, coherente et AI-ready.
**Effort estime :** 5-10 jours.

Livrables :

- `shared/i18n/locales/*.json` comme source de verite.
- Generation backend Laravel, frontend web/admin, Flutter ARB.
- Validation placeholders, RTL, longueurs, mojibake, checksums.
- Endpoint remote catalog et cache mobile non bloquant.

### Lot 11 - Security & Compliance Hardening

**Objectif :** preparer une plateforme SaaS RH sensible a des audits clients.
**Impact business :** confiance enterprise et contrats plus gros.
**Effort estime :** 6-10 jours.

Livrables :

- MFA et recovery codes super-admin/admin tenant.
- Audit logs pour impersonation, billing, paie, documents, IA.
- Rate limits par surface : auth, AI, exports, webhooks.
- Politique retention logs/documents et runbook incident.
- Secret scan et dependency review deja actifs, a garder obligatoires.

### Lot 12 - IA Ready, mais controlee

**Objectif :** brancher l'IA sans casser le modele classique ni contourner les permissions.
**Impact business :** differenciation premium.
**Effort estime :** progressif.

Regles :

- Tool calling d'abord read-only.
- Toute action write passe par permission, confirmation humaine et audit log.
- Les tools IA consomment les memes APIs documentees que les frontends.
- Les prompts doivent utiliser glossary i18n stable et contexte metier clair.

---

## 5. Recommandations architecture

### Court terme

- Continuer par petites PR verticales : un contrat API, sa consommation frontend, ses tests, sa documentation.
- Mettre a jour OpenAPI a chaque endpoint plateforme, billing, paie ou IA.
- Ajouter des tests qui prouvent l'absence de cross-tenant sur les modules sensibles.
- Garder les dashboards sans mocks des qu'un contrat backend existe.

### Moyen terme

- Introduire un SDK interne TypeScript genere depuis OpenAPI pour `front/admin-dashboard` et `front/web`.
- Ajouter une suite contractuelle mobile/kiosk qui valide les JSON critiques sans lancer toute l'app Flutter.
- Centraliser metrics business dans des services backend testables, puis cacher les agregats couteux.
- Ajouter un environnement staging stable avec donnees de demo anonymisees.

### Long terme

- Event-driven analytics pour churn, activation, adoption et recouvrement.
- Segmentation multi-pays avancee : pays, devise, fiscalite, langue, fuseau horaire.
- Marketplace API tierce avec scopes, quotas, developer portal et sandbox.
- IA conversationnelle avec orchestration tool calling, auditabilite et guardrails.

---

## 6. Optimisations futures

- Cache metrics plateforme avec invalidation courte (60-300 secondes) quand le volume clients augmente.
- Materialized views PostgreSQL pour reporting mensuel attendance/payroll.
- Jobs asynchrones pour PDF lourds, exports bancaires et emails en masse.
- Compression API et pagination stricte sur toutes les listes.
- Observabilite business : alertes trials expirants, impayes, baisse adoption pointage, erreurs webhook.
- Tableaux de bord par pays pour fiscalite, devise et performance commerciale.

---

## 7. Definition of done des prochains lots

Un lot est complet seulement si :

- le contrat API est implemente ou consomme sans duplication locale ;
- les tests pertinents passent dans GitHub Actions ;
- le changelog est mis a jour ;
- les scenarios de gouvernance sont mis a jour ;
- `AGENTS.md` capture toute lecon operationnelle utile ;
- la branche est mergee dans `main`, la branche distante supprimee, et le local realigne sur `origin/main`.

---

## 8. Prochaine sequence recommandee

1. Lot 6 : OpenAPI des contrats plateforme recents.
2. Lot 7A : tests billing/webhooks/invoices.
3. Lot 7B : tests paie multi-pays et exports bancaires.
4. Lot 8A : vue finance admin et filtres clients.
5. Lot 9A : matrice mobile/kiosk vs API.
6. Lot 10A : i18n shared validation et generation.

Le fil directeur reste le meme : **API robuste d'abord, interfaces ensuite, IA uniquement par-dessus des contrats propres et securises.**
