# ADR-0012 — Programme FOCUS : profondeur du noyau, maintenance du périphérique

- **Statut** : proposé (à valider par le propriétaire du produit)
- **Date** : 2026-08-07
- **Liens** : issue-cadre #1531 (F-01), plan complet `docs/focus/PLAN.md`, issues F-02…F-30

## Décision

Leopardo RH concentre son effort d'approfondissement sur un **noyau dur** — moteur de paie (conformité DZ d'abord), HR core, présence/pointage, confiance/sécurité, qualité/tests — pendant que les **modules périphériques** (Fleet, Caméras, Marketing, Growth, EdgeSync, Training, Recrutement, notifications avancées, apps mobiles non-employee) passent en **mode maintenance assumé**.

**La maintenance n'est pas une fermeture** : les modules périphériques restent dans le repo, fonctionnels, couverts par la CI, et leurs bugs/sécurité restent prioritaires. Seules les **nouvelles fonctionnalités** hors noyau sont dépriorisées (re-planifiées après le programme).

## Pourquoi

1. La paie est le contrat de confiance du produit et son moat ; elle est la zone la moins approfondie (2 fichiers de test, pas de référentiel de conformité documenté).
2. La largeur actuelle (19 modules, 6 apps) dilue l'effort : les P0 récurrents (mobile, staging, secrets) prouvent que la base n'est pas encore en acier.
3. Un pays d'abord (DZ) : une conformité complète vaut mieux que quatre promesses partielles.
4. La sécurité (purge #1472, RGPD) est une licence d'exploitation en HR/paie, pas un accessoire.

## Portée

- **Noyau (approfondir)** : Payroll, HR core, Presence/Attendance, Cabinet (archivage légal), Billing (support), Security/confiance, Quality/tests, leopardo_employee.
- **Périphérique (maintenir)** : Fleet, Cameras, Marketing, Growth, EdgeSync, Training, Recruitment, apps hr/manager/platform_admin/marketing, IA générique (voix, agents) → statut expérimental documenté.

## Conséquences

- Label GitHub `peripheral` sur les issues des modules périphériques ; les PRs de bugfix/sécurité y restent prioritaires.
- Les PRs de nouvelles features périphériques sont re-planifiées après le programme FOCUS (pas refusées).
- Métriques FOCUS suivies (coverage Payroll ≥ 80 %, ≥ 40 golden tests, #1472 clos, 3 pilotes DZ) — voir `docs/focus/PLAN.md`.
- Aucune suppression de code ni de fonctionnalité existante.
