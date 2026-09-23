# Business / Vente — Leopardo

Dossier **opérationnel de vente** (consolidation #7844 phase 1, 2026-09-23) : fusion des anciens
`docs/STRATEGIE_COMMERCIALE/` et `docs/commercial/`. Pour la **stratégie** go-to-market
(positionnement, pricing, canaux, rapports mensuels), voir [`../../GOTO_MARKET/`](../../GOTO_MARKET/) —
source de vérité business.

> ⚠️ **Décision propriétaire en attente (#7982)** : [`docs/README.md` §3](../../README.md) désigne
> ce dossier candidat n°1 au dépôt privé (`leopardo-internal`). Publication maintenue en
> attendant l'arbitrage.

## Contenu

### Exécution commerciale (ex-`STRATEGIE_COMMERCIALE/`)

- `Leopardo_RH_GoToMarket.pdf` — PDF source de stratégie go-to-market
- `LEOPARDO_RH_GTM_VERSION_AMELIOREE.md` — stratégie go-to-market détaillée
- `LEOPARDO_RH_PLAN_ACTION_30_JOURS.md` — plan d'exécution court terme
- `LEOPARDO_RH_SCRIPTS_COMMERCIAUX.md` — scripts d'approche et relance
- `LEOPARDO_RH_CRM_MODELE.md` — structure de CRM recommandé
- `LEOPARDO_RH_CRM_TEMPLATE.csv` et `LEOPARDO_RH_CRM_EXEMPLE_10_LIGNES.csv` — modèle et exemple de données

### Avant-vente & compétitif (ex-`commercial/`)

- `COMPARATIF_CONCURRENTS.md` — benchmarks concurrents
- `BENCHMARKS_PERFORMANCE.md` — benchmarks de performance
- `DOSSIER_TECHNIQUE_APPELS_OFFRES.md` — dossier technique pour appels d'offres

> **Note (audit 2026-09-20, anonymisation #7982 le 2026-09-23)** : les fichiers CSV de ce dossier sont des **modèles à données entièrement fictives** (chaque ligne est marquée `DONNEES FICTIVES (#7982)`). L'exemple 10 lignes contenait des données ressemblant à de vrais prospects bêta : il a été réécrit avec des identités explicitement fictives (#7982). Aucune donnée client réelle ne doit jamais être commitée dans ce dépôt public — le CRM réel vit hors du repo. Les versions antérieures restent dans l'historique git : une réécriture (BFG) est à évaluer avec la décision propriétaire #7982.

## Règle

Ces documents sont utiles pour le business et la commercialisation.
Ils ne remplacent pas les documents canoniques de produit sous `docs/REFERENTIEL_PRODUIT/`.
