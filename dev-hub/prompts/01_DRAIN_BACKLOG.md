# 01 — Drain Backlog & Implémentation Autonome

> **Quand l'utiliser :** Quand tu veux qu'un agent travaille de façon autonome — qu'il identifie ce qui manque, le planifie, et l'implémente sans intervention humaine.
> **Durée estimée :** Long (dépend de la quantité de travail restant)
> **Prérequis :** `main` à jour (`git pull`), avoir lu `AGENTS.md` et `.specify/constitution.md`

---

## Prompt A — Autonome complet (recommandé)

```
Lis `.specify/constitution.md`, `.specify/memory/project-state.md` et `AGENTS.md`.

Le fichier `project-state.md` te dit instantanément ce qui est fait (✅),
en cours (🔄) et à faire (🔴 placeholder). Commence par là avant de scanner le code.



Lance `/speckit-converge` pour évaluer l'état réel du codebase et identifier
ce qui n'est pas encore implémenté.

Concentre-toi en priorité sur :
1. Les pays en `confidenceLevel = 'placeholder'` dans les règles CountryRules
2. Les golden tests manquants (< 6 cas par pays pour CEMAC/CEDEAO, < 20 pour DZ)
3. Les issues ouvertes sans assigné labellisées `payroll`, `focus`, `compliance` ou `P1`
4. Les fonctionnalités documentées dans docs/payroll/*_COMPLIANCE.md mais pas encore codées

Pour chaque lacune identifiée :
- Si la tâche est claire et < 200 lignes → implémente directement
- Si la tâche est complexe → `/speckit-specify` puis implémente
- Si la tâche nécessite validation humaine → crée une issue GitHub avec `/speckit-taskstoissues`

Règles non négociables (constitution) :
- Assigne-toi chaque issue avant de commencer (`gh issue edit <n> --add-assignee "@me"`)
- Vérifie `gh issue list --assignee @me` — ne duplique jamais une tâche déjà prise
- PHPStan strict `[OK] No errors` avant chaque PR
- CHANGELOG.md mis à jour dans chaque PR
- 1 PR = 1 issue = 1 branche `feat/<numero>-slug`

Continue jusqu'à épuisement des tâches identifiables. Rapport final en fin de session.
```

---

## Prompt B — Vider les issues GitHub existantes

```
Lis `.specify/constitution.md` et `AGENTS.md`.

Ton objectif : traiter toutes les issues GitHub ouvertes non assignées, une par une.

Cycle de travail :

1. CHERCHER : `gh issue list --state open --json number,title,labels,assignees --limit 50`
   Prendre la première sans assigné avec label `Agent-Ready` ou `P1`. 
   Si liste vide → rapport final.

2. PRENDRE : `gh issue edit <numero> --add-assignee "@me"`

3. SPÉCIFIER : Si l'issue touche `api/app/Modules/Payroll/` →
   lance `/speckit-specify` avec le titre de l'issue pour générer la spec
   (les presets injectent automatiquement les contraintes légales + tests).
   Sinon → lis `gh issue view <numero>` et procède directement.

4. CODER : Branche `feat/<numero>-slug`, implémente, tests, PHPStan strict.

5. LIVRER : PR avec `Closes #<numero>` dans la description + CHANGELOG.md.

6. VÉRIFIER CI : `gh pr checks <numero_pr>`. Rouge → corriger. Vert → merger + supprimer branche.

7. BOUCLER : Retour étape 1.

Ne pas demander de validation sauf blocage technique majeur.
```

---

## Prompt C — Pays payroll spécifique

```
Lis `.specify/constitution.md`.

Audit les règles paie pour la zone [CEMAC/CEDEAO/Maghreb] :
- Inspecte `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/`
- Pour chaque pays en `confidenceLevel = 'placeholder'` → planifie et implémente
- Pour chaque pays sans golden tests → crée les tests (min 6 cas calculés à la main)
- Référence : `docs/payroll/*_COMPLIANCE.md` pour les valeurs légales

Utilise `/speckit-specify` pour chaque pays avec le preset correspondant.
```

---

## Notes

- Les issues avec `Agent-Ready` sont prioritaires.
- Un ticket trop vague → commenter + passer (ne pas bloquer).
- Vérifier `git stash list` avant de commencer (règle AGENTS.md).
- **Anti-doublon critique** : toujours vérifier `gh issue list --assignee @me` avant de démarrer un nouveau travail.
