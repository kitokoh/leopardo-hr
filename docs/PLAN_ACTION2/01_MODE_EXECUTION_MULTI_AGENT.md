# Mode d'execution multi-agent

## Principe

Un agent prend un ticket `PA2-*`, le livre completement, ouvre une PR courte, attend les checks, puis merge. Un agent ne doit pas prendre un second ticket dependant tant que le premier n'est pas merge ou explicitement marque bloque.

**Un agent ne doit jamais committer directement sur `main` pour signaler qu'il prend une tache.** `main` reste reserve aux merges de PR completes et verifiees par CI (voir section "Signal de prise de tache" ci-dessous pour le mecanisme correct).

## Signal de prise de tache (claim)

Pour eviter que deux agents travaillent sur le meme ticket, ou qu'un agent perde une tache deja livree, utiliser uniquement les mecanismes GitHub natifs suivants, jamais un commit direct sur `main` :

1. **Prise (claim)** : l'agent s'auto-assigne l'issue GitHub correspondant au ticket `PA2-X` (`gh issue edit <N> --add-assignee <moi>`). C'est le signal officiel "je prends cette tache".
2. **En cours** : l'agent cree sa branche `codex/pa2-<id-court>-<slug>` puis ouvre immediatement une PR en **draft** (`gh pr create --draft`) qui referme l'issue au merge (`Closes #N` dans la description). La PR draft est visible de tous via `gh pr list --draft` et montre l'avancement sans jamais toucher `main`.
3. **Termine** : une fois le travail complet, l'agent passe la PR en "Ready for review" (`gh pr ready`), attend les checks CI obligatoires, puis merge. Le merge sur `main` est la **seule preuve de livraison** valable.
4. **Disponibilite** : un ticket sans issue assignee ni PR draft ouverte est considere disponible pour n'importe quel agent. Avant de prendre un ticket, verifier `gh issue view <N>` (champ assignee) et `gh pr list --search "PA2-X"` pour eviter un doublon.
5. **Abandon/blocage** : si un agent doit abandonner une tache prise, il retire son assignation de l'issue et ferme ou marque la PR draft comme bloquee (commentaire explicite), pour liberer le ticket.

## Format de branche

Utiliser:

```text
codex/pa2-<id-court>-<slug>
```

Exemples:

```text
codex/pa2-mkt-001-hero-conversion
codex/pa2-api-004-error-contract
codex/pa2-sec-002-rbac-tenant-proof
```

## Checklist avant codage

- `git fetch origin main`
- comparer la branche avec `origin/main`
- verifier `git stash list`
- lire le ticket et ses dependances
- identifier les fichiers front/API/docs/tests touches
- verifier si une ligne existe deja dans `dev-hub/tools/launch-workflow-contracts.json`

## Checklist PR

- changement limite au ticket;
- `CHANGELOG.md` mis a jour;
- docs ou matrice mises a jour si route/workflow visible;
- pas de secret, token, APK ou artifact inutile;
- pas de lien mort ni CTA `#`;
- PR description avec:
  - objectif;
  - surfaces touchees;
  - verification;
  - risques residuels.

## Regles produit

- Vitrine: vendre le produit en 30 secondes, pas expliquer la stack.
- Admin web/mobile: chaque bouton critique doit faire une action reelle ou afficher un etat actionnable.
- Mobile: jamais bloquer le premier rendu sur reseau, Firebase, Hive, intl ou auth.
- API: toute reponse nouvelle doit rester compatible avec web, mobile, kiosk et futurs agents IA.
- Multi-tenant: aucune donnee employe/manager ne traverse les entreprises.
- Paiement/avance: audit trail obligatoire.
- Notifications: passer par l'orchestrateur communication existant quand c'est multi-canal.

## Gestion des dependances

Si un ticket depend d'un autre:

- ne pas dupliquer le code provisoirement;
- ajouter un garde degrade si necessaire;
- documenter le blocage dans la PR;
- ne pas creer d'endpoint temporaire qui devra etre supprime.

