# Cartographie des Bounded Contexts — état réel et reste à faire

**Dépôt :** `kitokoh/leopardo-hr`
**Date de l'audit :** 2026-10-20
**Périmètre :** les 37 entrées du registre canonique `dev-hub/governance/bounded-context-registry.json`
**Nature :** audit constaté (état du code au commit `5bdd2b146` de `main`), pas un plan théorique.

> Ce document répond à deux questions : **ce qui est déjà fait**, et **ce qui reste à faire**.
> Il ne remplace pas `BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md` (qui définit les responsabilités)
> ni `BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (qui définit les 12 dimensions de profondeur D1–D12) :
> il mesure l'écart entre ces référentiels et le code réellement présent.

---

## 1. Ce qu'est un bounded context dans ce projet

Un BC est une **frontière métier et technique** : il possède son vocabulaire, ses modèles, ses migrations
Laravel, ses routes, ses Policies, ses événements et son propriétaire. L'architecture reste un
**modular monolith Laravel dans un monorepo**, pas des microservices.

Concrètement, un BC se matérialise par :

| Artefact | Emplacement |
|---|---|
| Code métier | `api/app/Modules/<Nom>` ou `api/app/Core/<Nom>` |
| Routes | `api/routes/modules/*.php` |
| Migrations | `api/database/migrations/{public,tenant}` |
| Événements | `App\Modules\<Nom>\Domain\Events` |
| Tests | `api/tests/{Unit,Feature}/Modules/<Nom>` |
| Propriété | lignes `CODEOWNERS` |

**Le registre est une autorité machine-readable**, pas une documentation libre : il est contrôlé en CI.

---

## 2. État du référentiel de gouvernance

| Contrôle | Commande | Résultat constaté |
|---|---|---|
| Cohérence registre (structure, dépendances, chemins, CODEOWNERS, routes, migrations) | `bash dev-hub/tools/check-bounded-context-registry.sh .` | ✅ **vert** — 37 BCs |
| Matrice de dépendances inter-contextes | `bash dev-hub/tools/check-bounded-context-dependencies.sh .` | ✅ **vert** — 145 arêtes autorisées, 0 import hors contrat |
| Parité docs / architecture | `bash dev-hub/tools/check-architecture-docs-parity.sh .` | ✅ **vert** — 37 modules ×4, 0 fantôme |
| Domaines canoniques | `bash dev-hub/tools/check-canonical-domains.sh .` | ✅ **vert** |
| Catalogue d'événements (**garde CI réelle**) | `python3 dev-hub/tools/check-event-catalogue.py .` | ✅ **vert** — 27 événements synchronisés |

**Conclusion référentiel : sain.** Les 5 chemins déclarés `planned` et absents (`Modules/Documents`,
`Modules/Reporting`, deux répertoires front `restaurant`, un front `hospitality`) le sont **légitimement** :
la garde n'exige l'existence que des chemins `active`.

> ⚠️ Note d'exploitation : `dev-hub/tools/check-event-catalogue.sh` est **obsolète** (il valide un
> `event-catalogue.yaml` legacy et remonte 24 faux positifs). La CI (`architecture-check.yml`) exécute
> le `.py`. Il ne faut donc pas se fier au `.sh` pour juger l'état du catalogue.

---

## 3. Cartographie des 37 BC

**Règle de statut appliquée** (mesurable, reproductible) :

- **`fait`** — code livré **et** au moins 1 test sous `api/tests` **et** au moins 1 événement au catalogue.
- **`partiel`** — code livré, mais une preuve manque (tests absents, ou événement non catalogué).
- **`manquant`** — aucun code : le BC n'existe qu'à l'état planifié.

Colonnes : `PHP` = fichiers `.php` du ou des modules ; `U/F` = tests unitaires / fonctionnels ;
`Rt` = fichiers de routes déclarés ; `Év` = événements au catalogue possédés par ce BC.

| BC | Nom | Prio | Statut constaté | PHP | U/F | Rt | Év |
|---|---|---|---:|---:|---:|---:|---:|
| BC-01 | PLATFORM | P0 | `partiel` | 133 | 1/36 | 0 | 0 |
| BC-02 | TENANT | P0 | `partiel` | 17 | 0/0 | 0 | 1 |
| BC-03 | IDENTITY | P0 | `partiel` | 83 | 0/0 | 2 | 0 |
| BC-04 | HR | P1 | `fait` | 107 | 4/25 | 3 | 5 |
| BC-05 | WORKFORCE | P1 | `fait` | 192 | 8/47 | 3 | 3 |
| BC-06 | LEAVE | P1 | `partiel` | 6 | 0/0 | 1 | 3 |
| BC-07 | PAYROLL | P0 | `fait` | 160 | 5/65 | 1 | 4 |
| BC-08 | ACCOUNTING | P0 | `partiel` | 131 | 0/45 | 1 | 0 |
| BC-09 | EXPENSE | P2 | `partiel` | 10 | 0/3 | 1 | 0 |
| BC-10 | RECRUITMENT | P2 | `partiel` | 29 | 0/0 | 0 | 1 |
| BC-11 | CRM | P1 | `fait` | 215 | 2/30 | 1 | 1 |
| BC-12 | GROWTH | P1 | `fait` | 94 | 0/30 | 2 | 1 |
| BC-13 | COMMS | P0 | `partiel` | 55 | 0/4 | 0 | 0 |
| BC-14 | INTEGRATION | P0 | `partiel` | 19 | 0/0 | 1 | 0 |
| BC-15 | FUEL | P0 | `fait` | 183 | 1/10 | 0 | 1 |
| BC-16 | EDU | P1 | `partiel` | 174 | 0/41 | 1 | 0 |
| BC-17 | RETAIL | P3 | `fait` | 87 | 0/9 | 0 | 1 |
| BC-18 | FIELD | P2 | `partiel` | 33 | 1/2 | 0 | 0 |
| BC-19 | DEVICE | P1 | `partiel` | 56 | 3/11 | 1 | 0 |
| BC-20 | DOCUMENTS | P1 | `partiel` | 22 | 0/5 | 1 | 0 |
| BC-21 | BILLING | P2 | `fait` | 62 | 0/21 | 1 | 2 |
| BC-22 | ANALYTICS | P1 | `manquant` | 0 | 0/0 | 0 | 0 |
| BC-23 | AI | P2 | `partiel` | 79 | 0/0 | 0 | 0 |
| BC-24 | TRAVEL | P1 | `partiel` | 390 | 0/0 | 1 | 0 |
| BC-25 | RESTAURANT | P1 | `fait` | 406 | 2/75 | 2 | 1 |
| BC-26 | DELIVERY | P1 | `fait` | 71 | 3/19 | 1 | 1 |
| BC-27 | SHOWCASE | P1 | `partiel` | 43 | 1/7 | 0 | 0 |
| BC-28 | CATALOG | P0 | `fait` | 38 | 1/10 | 0 | 2 |
| BC-29 | COMMUNICATION | P1 | `partiel` | 66 | 0/7 | 1 | 0 |
| BC-30 | PHARMACY | P1 | `partiel` | 67 | 0/7 | 1 | 0 |
| BC-31 | HEALTH | P1 | `partiel` | 103 | 0/8 | 1 | 0 |
| BC-32 | HOSPITALITY | P1 | `partiel` | 54 | 0/0 | 1 | 0 |
| BC-33 | FUNDRAISING | P1 | `partiel` | 61 | 1/8 | 1 | 0 |
| BC-34 | GEO | P1 | `partiel` | 19 | 5/7 | 1 | 0 |
| BC-35 | VTC | P1 | `partiel` | 75 | 3/13 | 1 | 0 |
| BC-36 | TRADE | P1 | `partiel` | 80 | 6/6 | 1 | 0 |
| BC-38 | PAIEMENTS | P1 | `partiel` | 41 | 0/3 | 1 | 0 |

### Synthèse chiffrée

| Indicateur | Valeur |
|---|---|
| BC au registre | **37** (numérotation BC-01 → BC-38, **BC-37 absent sur `main`**) |
| BC `fait` | **11** (BC-04, 05, 07, 11, 12, 15, 17, 21, 25, 26, 28) |
| BC `partiel` | **25** |
| BC `manquant` | **1** (BC-22 ANALYTICS) |
| BC ayant au moins un événement catalogué | **13 / 37** |
| Modules métier sur disque couverts par le registre | **37 / 37 — 100 %** |
| Arêtes de dépendance autorisées contrôlées | **145**, 0 violation |
| Rapports de maturité présents sur disque | **34** dans `docs/architecture/maturity/` |

---

## 4. Écarts constatés

### É1 — BC-37 Farm OS livré mais **non mergé** (écart principal)

Le contexte agricole **BC-37 Farm OS** existe intégralement sur la branche `bc/bc-37-farm-os`
(38 commits, **171 fichiers, +24 264 lignes**), avec PR **#8572** ouverte, mais **absent du registre sur `main`**.
Cela explique la numérotation à trou : `… BC-36, BC-38` sans BC-37.

Contenu de la branche :
- module complet `api/app/Modules/FarmOs` (élevage, récoltes, intrants, matériel, finance, offline, assistant)
- contrat OpenAPI des routes `/farm/*`, 47 routes
- **13 fichiers de tests** (Feature + Unit), dont isolation multi-tenant et concurrence de stock
- déclaration BC-37 dans `bounded-context-registry.json` et `bounded-context-dependencies.json`
- dossier de conception Phase 10 (`docs/mission-farm-os/`, TAD, TRD, AppFlow, UIX, schéma, plan)
- `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` mis à jour

**C'est la seule fonctionnalité métier livrée qui n'atteint pas `main`.**

### É2 — Catalogue d'événements : sain, mais couverture inégale

La garde est verte (27 événements). En revanche **24 BC ne possèdent aucun événement de domaine**,
dont des BC structurants : BC-01 PLATFORM, BC-03 IDENTITY, BC-08 ACCOUNTING, BC-13 COMMS, BC-14 INTEGRATION.
C'est un écart de maturité (dimension **D1 — Domaine**), pas une violation CI.

### É3 — Incohérence de propriétaire dans le catalogue

L'entrée `CRM` est déclarée propriétaire `BC-17 CRM`, alors que **BC-17 = RETAIL** et **CRM = BC-11**
dans le registre. Le libellé du catalogue contredit le registre canonique.

### É4 — Index de maturité périmé

`docs/architecture/BOUNDED-DEPTH-ISSUES-INDEX.json` déclare **26 issues**, dont seulement **5** au statut
`audited`. Or **34 rapports de maturité** existent dans `docs/architecture/maturity/`.
L'index ne reflète donc pas l'état réel, et **12 BC n'y figurent pas du tout**
(BC-27 → BC-36, BC-38). Un lecteur de l'index sous-estime massivement le travail réalisé.

### É5 — Issues dupliquées Farm OS

Les issues **#8549 → #8559** (10 issues, **ouvertes**) dupliquent **#8560 → #8570** (**fermées**), qui
portent les mêmes titres T0.1 → T5.1. Le travail correspondant est déjà implémenté sur la branche BC-37.
Elles créent un faux reste-à-faire.

### É6 — BC-22 ANALYTICS inexistant

`api/app/Modules/Reporting` n'existe pas. Seul chemin `planned` parmi les BC P1 sans aucun code.
Tous les indicateurs et read models dépendent encore des BC producteurs.

---

## 5. Ce qui reste à faire — par priorité

| # | Action | Nature | Priorité | Réf. |
|---|---|---|---|---|
| 1 | Merger la PR **#8572** (BC-37 Farm OS) une fois les checks requis verts, puis supprimer la branche | Livraison | **P0** | É1 |
| 2 | Resynchroniser `BOUNDED-DEPTH-ISSUES-INDEX.json` avec les 34 rapports sur disque et couvrir les 12 BC manquants | Gouvernance | **P1** | É4 |
| 3 | Fermer les issues dupliquées #8549–#8559 en renvoyant vers #8560–#8570 | Hygiène | **P1** | É5 |
| 4 | Corriger le propriétaire `CRM` du catalogue (`BC-17` → `BC-11`) | Gouvernance | **P2** | É3 |
| 5 | Doter les BC P0/P1 sans événement d'un vocabulaire d'événements (BC-01, 03, 08, 13, 14) | Domaine (D1) | **P2** | É2 |
| 6 | Créer BC-22 ANALYTICS (`Modules/Reporting`) | Implémentation | **P3** | É6 |
| 7 | Retirer ou réaligner `check-event-catalogue.sh` sur le `.py` | Outillage | **P3** | §2 |

---

## 6. Méthode de reproduction

```bash
# 1. Référentiel
bash dev-hub/tools/check-bounded-context-registry.sh .
bash dev-hub/tools/check-bounded-context-dependencies.sh .
bash dev-hub/tools/check-architecture-docs-parity.sh .

# 2. Catalogue d'événements (garde CI réelle)
python3 dev-hub/tools/check-event-catalogue.py .

# 3. Mesure du code et des tests par BC
python3 - <<'PY'
import json, os
reg = json.load(open('dev-hub/governance/bounded-context-registry.json'))
for bc in reg['bounded_contexts']:
    mods = [os.path.basename(p['path']) for p in bc['paths']
            if p['path'].startswith('api/app/Modules/')]
    php = sum(1 for m in mods for r, _, fs in os.walk(f'api/app/Modules/{m}')
              for f in fs if f.endswith('.php'))
    print(bc['code'], bc['name'], len(bc['paths']), php,
          len(bc['routes']), len(bc['events']))
PY
```

**Limite de mesure assumée :** le décompte de tests est fait par correspondance de répertoire
(`api/tests/{Unit,Feature}/Modules/<Nom>`). Un BC dont les tests sont nommés hors de cette convention
apparaît à 0 alors qu'il peut être couvert — c'est notamment à vérifier pour **BC-24 TRAVEL**
(390 fichiers PHP, 0 test détecté dans `TravelAgency`, alors qu'un audit dédié existe).

---

## 7. Conclusion

Le référentiel de gouvernance est **en bon état** : registre cohérent, 145 arêtes de dépendance
maîtrisées, couverture des modules à 100 %, catalogue d'événements synchronisé.

Le véritable reste-à-faire n'est pas architectural mais **de livraison et de tenue de registre** :
une verticale entière (BC-37 Farm OS) est prête et bloquée en PR, l'index de maturité sous-déclare
le travail accompli, et 10 issues dupliquées brouillent la lecture du backlog.
