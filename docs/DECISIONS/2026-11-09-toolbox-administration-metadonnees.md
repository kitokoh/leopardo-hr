# Décision — 2026-11-09 — Toolbox : administration des métadonnées d'outils

> Issue : #9157 · Module : `front/web/src/modules/toolbox` · Statut : **tranchée**

## Objet

Les métadonnées d'outils doivent-elles rester un **catalogue statique versionné**
(dans le code) ou devenir une **table administrable** (`toolbox_tools`) pilotée
par un super-admin ?

## Options examinées

| Option | Avantages | Inconvénients |
|---|---|---|
| **A. Catalogue statique** (défaut actuel) | Versionné avec le code, revu en PR, aucun risque | Ajout d'outil = déploiement |
| **B. Table administrable** | Activation/désactivation à chaud, sans déploiement | Nouveau module DDD + permissions + isolation tenant |

## Décision

**Option A — le catalogue reste statique et versionné dans le code.**

Trois raisons décisives :

1. **Un outil n'est pas une donnée, c'est du code.** Chaque entrée de
   `data/catalog.ts` est couplée à une **formule pure testée**
   (`tools/<slug>/logic.ts`) et à un **formulaire** (`components/tools/*Form.tsx`).
   Une ligne en base ne peut pas créer la logique de calcul : l'option B
   n'administrerait que des métadonnées cosmétiques (libellé, ordre, visibilité)
   tout en donnant l'illusion qu'un outil s'ajoute sans déploiement.
2. **La revue en PR est le contrôle qualité du module.** Les formules sont
   vérifiées à la main dans les tests (`logic.test.ts`) ; les faire transiter par
   une table administrable retirerait cette revue du chemin critique.
3. **Aucun besoin d'activation à chaud n'est démontré.** Le rythme d'ajout
   d'outils (2 lots livrés) ne justifie pas un nouveau module DDD, ses
   permissions et son isolation tenant.

### Conséquence de code (livrée)

1. **Champ `enabled` dans le catalogue** — un outil peut être **désactivé** sans
   supprimer son code ni sa copie i18n : `registry.ts` filtre sur `enabled !== false`.
   C'est le seul bénéfice réel de l'option B (masquer un outil à chaud) obtenu
   **sans** table administrable : le masquage se fait par PR, donc reste revu.
2. **Garde de cohérence** — un test vérifie que tout outil `enabled` a bien sa
   copie dans les 4 locales et sa logique enregistrée ; un outil désactivé reste
   résolvable par slug direct (pas de 404 cassant un lien partagé) mais sort du
   catalogue et du sitemap.

### Déclencheur de réexamen (quand passer à B)

L'option B sera réévaluée si **les deux** conditions sont réunies :

- le rythme d'ajout d'outils dépasse ce qu'une PR peut absorber (plusieurs
  outils par semaine, ajoutés par des non-développeurs) ;
- un besoin d'**activation par tenant** apparaît (un tenant veut masquer certains
  outils pour ses utilisateurs) — ce qui exigerait de toute façon une table
  tenant-scoped, pas une table plateforme.

Tant qu'aucune des deux n'est réunie, **B reste non implémentée**.

## Références

- `front/web/src/modules/toolbox/AGENTS.md` — procédure d'ajout d'un outil
- `front/web/src/modules/toolbox/data/catalog.ts` — catalogue déclaratif
- Issue #9157
