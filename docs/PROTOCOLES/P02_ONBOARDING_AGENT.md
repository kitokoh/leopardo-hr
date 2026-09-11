# P02 — Intégration d'un nouvel agent (onboarding sans perte de temps)

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** gardien technique (parcours) + PM (affectation)
> **Portée :** tout agent (humain ou IA) qui arrive sur le dépôt pour la première fois ou revient
> après une absence. Hors champ : la formation métier RH/paie (voir `docs/payroll/`, compliance pays).
> **Ancrage existant :** `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (2 min),
> `dev-hub/prompts/14_ONBOARDING_AGENT.md`, `docs/architecture/AGENT-START-HERE.md`,
> `docs/CONTEXT/` (01→04), `AGENTS.md`, `.specify/constitution.md`, `docs/QUICKSTART.md`, `docs/DEMARRAGE_RAPIDE.md`.

## 1. Objet

Faire qu'un agent qui intègre le projet **produise sans se perdre dès sa première session**, en
capitalisant sur l'expérience passée : le temps perdu, les tentatives d'amélioration, les échecs
récurrents déjà documentés. L'échec sans ce protocole est connu et mesuré (cf. `docs/audits/`,
`docs/qa/`, `docs/archive/AGENTS_HISTORIQUE_UTILE.md`) : PRs dupliquées sur une même issue,
migrations en collision, CI saturée, travail sur des docs archivées, promesses non tenues.

## 2. Déclencheurs

- Premier contact d'un agent avec le dépôt (session 0).
- Retour d'un agent après > 7 jours d'absence (re-synchronisation rapide, §4).
- Affectation à un BC jamais traité par l'agent (parcours BC, §5).

## 3. Parcours d'entrée obligatoire (session 0 — < 30 min)

> Séquences déjà rodées ; ce protocole les rend **obligatoires et dans cet ordre**.

1. **Carte de référence** : lire `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (interdits + obligatoires, 2 min).
2. **Loi fondamentale** : lire `.specify/constitution.md` (spec-first, multi-tenant, paie, sécurité).
3. **Guide complet** : `AGENTS.md` — en particulier anti-doublon #2400 (le nom de branche EST le
   lock), affectation par BC, garde migrations #1962/#5431, méthode de gestion de projet (GitHub Issues).
4. **Contexte produit** : `docs/CONTEXT/01..04` puis `docs/REFERENTIEL_PRODUIT/APV.md` (12 Lois) —
   la vision prime sur le code.
5. **Synchronisation + état réel** (jamais supposé) :
   ```bash
   git fetch origin main && git checkout main && git pull && git stash list
   gh pr list --state open            # PRs en cours
   gh issue list --state open --limit 20
   gh run list --branch main --limit 5   # santé CI : main doit être vert
   ```
6. **Vérifier les verrous avant de toucher au code** : branches et PRs existantes contenant le
   numéro d'issue ciblé (protocole #2400) ; sinon **claim** : self-assign + branche
   `fix/<issue>-<slug>` + commit vide de claim.
7. **Environnement local** : `docs/QUICKSTART.md` / `docs/DEMARRAGE_RAPIDE.md` ; ne jamais inventer
   de clés — `.env.example` est la référence (parité vérifiée par garde
   `check-env-example-parity.sh`).

## 4. Leçons capitalisées — « ce qui a déjà coûté du temps » (à connaître AVANT de coder)

Tableau des erreurs récurrentes et de leur parade (sources entre parenthèses) :

| Erreur passée | Parade (déjà en place) |
|---|---|
| Deux agents implémentent la même issue (#2333 ×3 PRs, #2329 ×2, constat 2026-08-15) | Claim + marker branch #2400 ; vérifier **toutes** les branches, pas seulement les PRs |
| Collision de préfixes de migrations → `main` rouge pour tous (#1962, 3 occurrences 2026-08-24) | `bash dev-hub/tools/check-migration-basename-collisions.sh` avant push ; n° d'issue dans le nom (#5431) |
| CI saturée par des runs orphelins (#2413) | Annuler les runs orphelins avant de pousser (`cancel-orphan-runs.sh`) ; path filters respectés |
| Travailler sur des docs archivées (`docs/archive/`, `docs/PLAN_ACTION2/`, `PILOTAGE.md`) | Backlog = GitHub Issues uniquement (anti-ghost-close #4859) |
| Secrets/clés inventés ou divergents du réel | `.env.example` + gardes de parité ; registre `docs/deployment/KEYS_A_CONFIGURER.md` |
| Promettre en copie publique une capacité non livrée | Règle d'honnêteté P03 (§3) — précédents #3257, #4202, #3863 |
| PR énorme / périmètre non borné | Seuil 40 fichiers / +2 500 lignes (protocoles GOUVERNANCE) ; découpage par issue |
| Merge sur `main` rouge ou auto-merge | Jamais ; attendre checks requis verts (`BRANCH_PROTECTION_REQUIRED.md`) |
| Patterns mobiles interdits (page noire, `.withOpacity`, cast brut) | Quick card + gardes mobile (`mobile-apps-ci.yml`) |

> Un agent qui découvre une **nouvelle** perte de temps la signale immédiatement : commentaire sur
> l'issue + entrée dans la revue mensuelle (README du présent dossier, §4). C'est ainsi que le
> tableau ci-dessus s'enrichit — la capitalisation est un réflexe, pas une formalité.

## 5. Montée en compétence par paliers (affectation progressive)

| Palier | Conditions | Travail confié | Autonomie |
|---|---|---|---|
| **L1 Découverte** | Session 0 faite | Issues `good_first_issue` (`Agent-Ready`, P2/P3), docs, tests | Exécute ; ne crée pas d'issue sans validation |
| **L2 BC** | 1 BC maîtrisé (cartographie + DoD contexte, cf. `AGENT-START-HERE.md`) | Lot d'issues d'un BC (branche `bc/<code>-<slug>`, protocole `BC_BATCH_BRANCH_PROTOCOL.md`) | Crée des issues du BC (P04), propose des specs |
| **L3 Transverse** | Multi-BC prouvé | Migrations, RBAC/Policies, contrats partagés, CI, i18n transverse | Touche les zones à coordination (`AGENT-START-HERE.md` : interdictions) |
| **L4 Gardien** | Historique de revues | Protocoles, gardes CI, revue mensuelle | Propose/modifie des protocoles (revue mensuelle) |

Règle : **un agent n'est jamais laissé seul en L1** — sa première PR est revue par le gardien
technique ou un agent L3+ ; le contrat de sortie de session (ci-dessous) est exigé dès la L2.

## 6. Contrat de sortie (fin de session / fin de contexte)

Tout agent qui termine une session ou un BC laisse une trace exploitable par le suivant :

1. Issues traitées ↔ PRs mergées ↔ branches supprimées (état réel vérifié, pas supposé).
2. Ce qui reste à faire (issues ouvertes ou commentaire sur l'issue parente).
3. **Enseignements** : ce qui a surpris, ce qui a coûté du temps, ce qui devrait changer
   (alimente P04 §6 et la revue mensuelle).
4. Pour un BC entier : rapport de fin de contexte selon `docs/architecture/AGENT-START-HERE.md`
   (« Rapport attendu en fin de contexte ») — un contexte n'est **jamais** déclaré terminé sur la
   seule fermeture de ses issues fonctionnelles.

## 7. Gardes & indicateurs

- **Existant :** prompts 00 et 14, `AGENT-START-HERE.md`, `good_first_issue.md` (template),
  gardes anti-doublon (`check-issue-claim-unique.sh`, `check-no-claim-marker.sh`),
  `check-issues-left-open-by-merged-prs.sh`, `check-issues-closed-without-merge.sh`.
- **À créer (issues) :** checklist de session 0 versionnée (issue « formaliser la session 0 ») et
  template de « rapport de fin de session » dans `dev-hub/prompts/` (16_SESSION_REPORT).
- **Indicateurs :** temps entre arrivée et première PR mergée (cible : 1 session) ; nombre de PRs
  dupliquées / fermées pour cause de doublon (cible : 0) ; % de sessions avec contrat de sortie.

## 8. Revue mensuelle — questions spécifiques

- [ ] Y a-t-il une nouvelle perte de temps récurrente à ajouter au tableau §4 ?
- [ ] Les prompts d'onboarding sont-ils encore alignés avec AGENTS.md et la quick card ?
- [ ] Les paliers L1→L4 correspondent-ils aux profils réellement actifs ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — consolidation des parcours existants + tableau des leçons |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
