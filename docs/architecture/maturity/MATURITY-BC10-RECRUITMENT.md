# Rapport de maturité — BC-10 RECRUITMENT

> **DEP-BC10 (issue #5886)** — Deep maturity, BC-10 Recruitment.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 10.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-10).

## Périmètre

Candidatures, postes, candidats, entretiens, décisions et onboarding
d'embauche : `api/app/Modules/Recruitment` (DDD complet), surfaces publiques
(carrière) et tenant (gestion des candidatures), lien HR (hiring → employé).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | DDD complet (Application/Actions+DTOs, Domain/Contracts+Exceptions+Models, Infrastructure/Services, Interfaces/Requests). Vocabulaire : JobPosting, CandidateApplication, entretiens, décisions. |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant, FK/index cohérents, garde de schéma vert. |
| D3 | Tenant | 🟡 PARTIEL | Côté gestion tenant-scopé ; **surface publique** (`PublicCareerController`) volontairement ouverte (postes visibles sans auth) — risque contrôlé : les candidatures restent scopées, mais la visibilité des postes publics doit être auditée (quelles données exposées ?). |
| D4 | API | 🟢 PRÉSENT | 4 contrôleurs, 29 routes déclarées, Requests validées, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | Policies + gardes manager/RH ; candidatures soumises côté public avec contrôle de doublon ; décisions d'embauche bornées. |
| D6 | Transactions | 🟡 PARTIEL | Conversion candidat → employé (lien HR) : flux testé (CandidateHiringTest) mais sans événement de contrat explicite (recommandation 2). |
| D7 | Asynchronisme | 🟡 PARTIEL | Aucun job Recruitment dédié (notifications par canal global). |
| D8 | Sécurité | 🟢 PRÉSENT | PII candidat (CV, coordonnées) gérée par le contrat Documents/BC-20 ; pas de secret dans le module. |
| D9 | Frontend | 🟢 PRÉSENT | Page carrière publique (vitrine) + espace RH (gestion candidatures). |
| D10 | Performance | 🟢 PRÉSENT | Listes paginées, index ; volume modéré. |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, audit des décisions RH via canal HR. |
| D12 | Produit | 🟡 PARTIEL | Parcours poste → candidature → décision → embauche testé (24 tests locaux verts) ; pas de golden journey end-to-end dédié ni seed pilote. |

## Vérification locale (preuve)

```
php artisan test --filter="RecruitmentControllerTest|PublicCareerControllerTest|CandidateHiringTest"
→ 24 passed (77 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Audit de la surface publique** (D3) : dresser la liste exacte des champs
   exposés par `PublicCareerController` (RGPD candidat) et la verrouiller par
   test.
2. **Contrat d'embauche versionné** (D6) : remplacer l'appel direct
   candidat → employé par un événement `CandidateHired` (contrat BC-04/BC-10,
   idempotent, audité).
3. **Golden journey** (D12) : seed pilote recrutement + test end-to-end
   poste → candidature → entretien → décision → onboarding.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
