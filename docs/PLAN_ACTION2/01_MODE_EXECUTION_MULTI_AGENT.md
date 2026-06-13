# Mode d'execution multi-agent

## Principe

Un agent prend un ticket `PA2-*`, le livre completement, ouvre une PR courte, attend les checks, puis merge. Un agent ne doit pas prendre un second ticket dependant tant que le premier n'est pas merge ou explicitement marque bloque.

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

