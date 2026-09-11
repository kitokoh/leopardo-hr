# P04 — Issues & tâches par niveau d'expérience, délégation et capitalisation

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** PM (affectation) + gardien technique (qualité des issues)
> **Portée :** création, qualification, affectation, exécution et délégation des issues GitHub ;
> transformation de l'expérience acquise en issues/tâches automatiques ; capitalisation des
> enseignements. Hors champ : les specs (Spec Kit, `.specify/`) qui restent régies par la constitution.
> **Ancrage existant :** `.specify/constitution.md` (spec-first, anti-doublon), `AGENTS.md`
> (labels BC, affectation par BC, protocoles de branche), templates `.github/ISSUE_TEMPLATE/`
> (`feature.yml`, `bug.yml`, `good_first_issue.md`, `pilot_blocker.yml`, `security_vulnerability.md`),
> `dev-hub/prompts/04_CREATE_ISSUES.md`, `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md`,
> registre `dev-hub/governance/bounded-context-registry.json`, `docs/CONTEXT/04_CURRENT_PRIORITIES.md`.

## 1. Objet

Faire en sorte que **tout agent produise du travail exploitable par les autres**, quel que soit son
niveau d'expérience : savoir quand il peut créer une issue, quand il doit l'implémenter lui-même,
quand il doit la déléguer — et **laisser à chaque fois un enseignement réutilisable** (le savoir
tiré de sa propre expérience ne reste pas dans sa tête : il devient issue, spec, garde ou entrée
de protocole).

## 2. Règles générales de création d'une issue

1. **Une issue = un problème atomique** avec critères d'acceptation mesurables, label BC
   (registre `bounded-context-registry.json`), priorité P0-P3, et `Agent-Ready` si exécutable sans
   clarification humaine. Modèle : `dev-hub/prompts/04_CREATE_ISSUES.md`.
2. **Spec-first (constitution §I)** : tout périmètre significatif (nouvelle feature, module,
   refonte, changement de contrat partagé) exige une spec `.specify/features/<id>/` **avant**
   l'implémentation — l'issue y renvoie.
3. **Anti-doublon avant création** : `gh issue list --search "<mots clés>"` ; si une issue similaire
   existe, commenter au lieu de créer.
4. **Toute issue créée par un agent l'est avec son niveau** : une issue née d'un constat terrain
   porte le contexte (« découvert en traitant #N ») pour que la chaîne de valeur soit traçable.
5. **Fermeture prouvée** : une issue ne se ferme que par PR mergée (`Closes #N`) ou décision PM —
   jamais de fermeture « fantôme » (anti-ghost-close #4859, gardes dédiés en CI).

## 3. Matrice décisionnelle selon l'expérience (palier L1→L4 de P02)

| Situation rencontrée | L1 Découverte | L2 BC | L3 Transverse | L4 Gardien |
|---|---|---|---|---|
| Bug ou micro-tâche dans mon BC, périmètre clair | Signaler (issue) | **Implémenter** (claim + branche) | Implémenter | Implémenter |
| Feature ou périmètre flou / significatif | Signaler (issue sans spec) | Créer issue + proposer spec (Spec Kit) | Créer spec complète | Arbitrer + spec |
| Hors de mon BC / hors de mon niveau | **Déléguer** : issue label BC correct + commentaire au coordinateur | Déléguer avec spec si besoin | Créer l'issue de coordination (contrats partagés — `AGENT-START-HERE.md`) | Affecter |
| Découverte transverse (RBAC, migration, secret, perf, i18n) | Signaler (label `security`/`ci`/… + BC) | Signaler + proposition de découpage | Créer issue + plan de lot | Prioriser dans le train |
| Leçon d'expérience (« on aurait dû… », « ça a coûté 2 h ») | Commentaire sur l'issue en cours | Issue `docs:`/`ci:` (capitalisation §6) | Issue + garde CI proposé | Protocole / revue mensuelle |

> **Principe de délégation :** un agent n'implémente **jamais** ce qui dépasse son palier ou son BC
> sans affectation explicite (constitution §I « deux agents ne peuvent pas implémenter la même
> spec » ; AGENTS.md « un seul agent par BC à la fois »). Dépasser = créer/déléguer, pas bricoler.

## 4. Cycle d'exécution standard (rappel opposable)

1. DoR vérifiée (issue qualifiée, spec si requise) → 2. Claim (self-assign + branche
   `fix/<issue>-<slug>` ou `bc/<bc>-<slug>` pour un lot) → 3. Implémentation + tests → 4. PR courte
   (`Closes #N`, CHANGELOG, preuves) → 5. Checks requis verts → 6. Merge → 7. **Retour
   d'expérience** (§6) → 8. Issue fermée par la PR (vérifier `gh pr view`).

## 5. Délégation entre agents (passage de relais)

Quand un agent délègue (issue hors périmètre, session qui se termine, montée en compétence) :

1. L'issue est **qualifiée** (BC, priorité, critères, spec si besoin, contexte de découverte).
2. Le commentaire de relais indique : état d'avancement, branches existantes, risques connus,
   prochaine action recommandée (équivalent du « contrat de sortie » P02 §6).
3. L'affectation se fait par BC (AGENTS.md) — jamais par « premier arrivé » sur une issue sans BC.
4. Un agent qui **reçoit** une issue délégée lit l'historique complet avant de coder ; si la branche
   existe, il contribue dessus (jamais de seconde branche).

## 6. Capitalisation des enseignements (obligatoire, pas facultative)

Tout agent qui termine une tâche laisse **au moins un** des artefacts suivants, selon la nature de
la leçon :

| Leçon | Artefact | Exemple réel dans le dépôt |
|---|---|---|
| Comportement interdit / obligatoire | Entrée quick card ou `AGENTS.md` | `.withOpacity` interdit, `requestWithRetry` obligatoire |
| Règle vérifiable par machine | **Garde CI** (`dev-hub/tools/check-*.sh` + workflow) | `check-migration-basename-collisions.sh` (#1962) |
| Règle de processus | Protocole (`docs/GOUVERNANCE/` ou ce dossier) | `CRM_BRANCH_PROTOCOL.md`, `BC_BATCH_BRANCH_PROTOCOL.md` |
| Décision structurante | Spec ou entrée `docs/REFERENTIEL_PRODUIT/` | Lois APV (L.05, L.07) |
| Connaissance d'exploitation | Runbook / `docs/ops/` | `RUNBOOK_INCIDENT_P1.md` |
| Simple retour | Commentaire d'issue + moisson mensuelle | sessions `docs/qa/`, audits `docs/audits/` |

Règles :
- La PR qui implémente une leçon l'**annonce** dans sa description (« leçon issue de #N »).
- La **moisson mensuelle** (revue des protocoles, README §4) transforme les retours du mois en
  règles durables : ce qui a coûté 2 fois devient garde ; ce qui a coûté 1 fois devient note.
- L'expérience négative est aussi capitale : `docs/archive/AGENTS_HISTORIQUE_UTILE.md` et les
  audits documentent ce qui a échoué — les lire avant de réessayer (P02 §4).

## 7. Gardes & indicateurs

- **Existant :** `pr-issue-guard.yml`, `issue-governance-guard.yml`, `pr-issue-guard`,
  `check-issues-closed-without-merge.sh`, `check-issues-left-open-by-merged-prs.sh`,
  `check-pr-closes-issue.sh`, `check-issue-claim-unique.sh`, `fix-feat-ratio-*` (équilibre
  correctifs/features), labels BC vérifiés (`check-bounded-context-registry.sh`).
- **À créer (issues) :** (a) template d'issue « relais » (délégation) ; (b) moisson mensuelle
  automatisée : workflow listant les issues fermées du mois sans leçon attachée (commentaire de
  rappel) ; (c) label `lecon` pour tracer les issues de capitalisation.
- **Indicateurs :** % d'issues avec BC + priorité + critères (cible 100 % des nouvelles) ;
  % de PRs avec mention de leçon (cible ≥ 80 %) ; doublons fermés par mois (cible 0) ;
  issues fermées sans PR mergée (cible 0).

## 8. Revue mensuelle — questions spécifiques

- [ ] Quelles leçons du mois deviennent gardes/protocoles ? (liste issue par issue)
- [ ] La matrice §3 est-elle conforme aux paliers réellement actifs ?
- [ ] Les délégations du mois ont-elles toutes laissé un relais exploitable ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — matrice expérience/délégation + capitalisation obligatoire |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
