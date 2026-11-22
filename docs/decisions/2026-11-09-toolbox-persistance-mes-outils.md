# Décision — 2026-11-09 — Toolbox : persistance « Mes outils » (favoris, historique, synchronisation)

> Issue : #9156 · Module : `front/web/src/modules/toolbox` · Statut : **tranchée**

## Objet

Faut-il une persistance **serveur** des favoris et de l'historique de la Toolbox,
ou `localStorage` suffit-il ?

## Options examinées

| Option | Avantages | Inconvénients |
|---|---|---|
| **A. `localStorage` seul** (défaut actuel) | Aucun backend, aucun risque, RGPD trivial (aucune donnée ne quitte le navigateur) | Pas de synchronisation multi-appareils |
| **B. API + table tenant `toolbox_user_state`** | Synchronisation, espace personnel | Nouveau module DDD, migrations tenant, tests cross-tenant, touche les données utilisateur |

## Décision

**Option A — `localStorage` reste la source de vérité pour les visiteurs anonymes.**

La Toolbox est une catégorie **publique et sans authentification** (principe non
négociable n°1 de `AGENTS.md` : « aucune donnée personnelle collectée — les
saisies ne quittent pas le navigateur »). Introduire une table serveur pour des
favoris d'outils contredirait ce principe pour un bénéfice non démontré : aucun
usage mesuré ne justifie aujourd'hui la synchronisation multi-appareils.

### Conséquence de code (livrée)

1. **Clé de stockage versionnée** — `leopardo_toolbox_state_v1` (objet unique
   `{ favorites, history }`) au lieu de deux clés non versionnées. Une future
   migration de schéma pourra lire `_v1` et écrire `_v2` sans ambiguïté.
2. **Façade de stockage isolée** (`lib/storage.ts`) — toute lecture/écriture
   passe par ce module. Le jour où l'option B est retenue, seule cette façade
   change : les composants n'ont pas à être touchés.
3. **Repli silencieux** — SSR, mode privé et quota dépassé n'échouent jamais
   (même contrat que `library/lib/progress.ts`).

### Déclencheur de réexamen (quand passer à B)

L'option B sera réévaluée **sur données**, si l'un de ces seuils est atteint :

- un compte authentifié demande explicitement la synchronisation de ses favoris ;
- la Toolbox est rattachée à un espace utilisateur (comme la Library) ;
- une mesure d'usage montre que les favoris sont recréés sur un second appareil.

Tant qu'aucun seuil n'est atteint, **B reste non implémentée** — et le présent
document est la trace de cette décision (exigence mission : les décisions
structurantes touchant les données utilisateur sont présentées avant
implémentation).

## Références

- `front/web/src/modules/toolbox/AGENTS.md` — principes non négociables
- `front/web/src/modules/library/lib/progress.ts` — même contrat de repli
- Issue #9156
