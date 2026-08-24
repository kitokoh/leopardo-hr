# 🇩🇿 Paie DZ — Documentation (issue #5247)

Espace documentaire du module paie algérienne. Sources de vérité :
**[`DZ_COMPLIANCE.md`](../DZ_COMPLIANCE.md)** (référentiel légal versionné) et
**[`VALIDATION_EXPERTE.md`](../VALIDATION_EXPERTE.md)** (registre des validations).

| Document | Contenu |
|---|---|
| [GUIDE_CONFORMITE.md](GUIDE_CONFORMITE.md) | Guide de conformité consolidé : règles, taux, sources légales, écarts E1-E6 et issues de complétion |
| [RUNBOOK_BULLETIN_VIREMENT.md](RUNBOOK_BULLETIN_VIREMENT.md) | Produire un **bulletin de paie** + un **virement** de bout en bout via l'API |
| [RECETTE_PILOTE.md](RECETTE_PILOTE.md) | Template de **recette pilote** : scénarios, critères d'acceptation, journal de validation |

Références croisées :
- Workflow complet de clôture mensuelle : [`../RUNBOOK_CLOTURE_DZ.md`](../RUNBOOK_CLOTURE_DZ.md) (F-11/#5150)
- Mentions légales du bulletin : [`../BULLETIN_DZ_MENTIONS.md`](../BULLETIN_DZ_MENTIONS.md)
- Audit légal + spec des règles : `.specify/features/payroll-dz-100/spec.md` (#5240)
- Spec programme 100 % : `docs/plan/PLAN_100PCT.md`

> Toute modification de taux/barème = PR dédiée qui met à jour **simultanément**
> ce guide, le référentiel `DZ_COMPLIANCE.md`, les golden tests et le CHANGELOG
> (constitution §III).
