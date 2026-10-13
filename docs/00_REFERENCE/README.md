# Référentiels canoniques — Leopardo

> Les 6 documents de référence de la plateforme. Ils **synthétisent** l'existant
> et renvoient vers les sources détaillées (`docs/dossierdeConception/`,
> `docs/specifications/`, `docs/architecture/`) au lieu de les dupliquer.
> En cas de divergence, le code sur `main` fait foi ; ces documents doivent
> alors être mis à jour dans la même PR que le code.

| # | Document | Rôle |
|---|---|---|
| 1 | [TAD.md](TAD.md) | **Technical Architecture Document** — l'architecture technique de la plateforme |
| 2 | [TRD.md](TRD.md) | **Technical Requirements Document** — les exigences techniques (fonctionnelles et non-fonctionnelles) |
| 3 | [APP_FLOW.md](APP_FLOW.md) | **App Flow** — les parcours applicatifs, du signup à l'exploitation quotidienne |
| 4 | [UIX_UI_DESIGN.md](UIX_UI_DESIGN.md) | **UI/UX Design** — design system, principes d'expérience, branding |
| 5 | [BACKEND_SCHEMA.md](BACKEND_SCHEMA.md) | **Backend Schema** — le modèle de données logique et ses conventions |
| 6 | [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) | **Implementation Plan** — la méthode de travail et le plan de livraison |

Documents liés :

- `ARCHITECTURE.md` (racine) — structure du monorepo et règles DDD.
- `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md` — décision
  d'architecture plateforme/solutions verticales et orchestrateur de provisioning.
- `docs/dossierdeConception/` — dossier de conception détaillé (ERD, personas,
  wireframes, contrats API, règles métier).
- `docs/ARCHITECTURE_STATUS.md` — état couche par couche de chaque module.

Ces 6 documents répondent à l'issue #8444.
