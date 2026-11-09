# Suivi quotidien des quotas ops — plan d'action (#9085)

> Issue : #9085 · Épic : #8670 · Seuils : `docs/ops/COUTS_ET_PERF.md`

L'issue est un **rapport automatique** (2026-10-30) qui constate un état, pas une
demande de code. Ce document en tire le plan d'action concret.

---

## 1. Ce que le rapport du 2026-10-30 dit réellement

| Tier | Stack | HTTP | État |
|---|---|---|---|
| prod | a | **503** | **EN ERREUR** |
| prod | b | — | non provisionnée |
| dev | a | **503** | **EN ERREUR** |
| dev | b | 200 (1524 ms) | OK |
| dev | c | — | non provisionnée |

**Deux 503 sur les stacks `a` (prod ET dev)** — ce n'est pas un problème de
quota, c'est un service arrêté. Le rapport le dit lui-même : le service Render
`srv-dacsr6gae00c73ddk150` est **suspendu** (plan free, 750 h/mois).

### Diagnostic

Un service Render free pingé 24/7 consomme ~730 h/mois. **Deux** services pingés
sur le même compte dépassent 750 h **par construction** — c'est exactement ce qui
s'est produit (1 suspension sur 30 jours, le 2026-10-20).

---

## 2. Actions immédiates (avant tout code)

| # | Action | Responsable | Effet |
|---|---|---|---|
| 1 | **Réveiller** le service prod `srv-dacsr6gae00c73ddk150` | Ops | Prod repasse en 200 |
| 2 | **Réduire à UN seul** service pingé 24/7 par compte Render | Ops | Supprime la cause structurelle |
| 3 | Vérifier que le ping ne vise pas les stacks non provisionnées (`b`, `c`) | Ops | Évite une 3ᵉ consommation inutile |
| 4 | Renseigner `NEON_API_KEY` / `NEON_PROJECT_ID` | Ops | Débloque la mesure Neon (aujourd'hui « non mesuré ») |

> ⚠️ **Aucune de ces actions n'est du code** : ce sont des gestes d'exploitation
> sur des comptes tiers. Elles ne peuvent pas être exécutées depuis le dépôt.

---

## 3. Ce qui est faisable en code — et ce qui existe déjà

| Élément | Fichier | État |
|---|---|---|
| Seuils d'alerte documentés | `docs/ops/COUTS_ET_PERF.md` | ✅ |
| Seuils de tiers/quota | `docs/ops/CHECKS_TIERS_QUOTA.md` | ✅ |
| Quota Vercel | `docs/ops/QUOTA_VERCEL.md` | ✅ |
| Topologie Render dev/prod | `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` | ✅ |
| Alignement dev | `docs/ops/RENDER_DEV_ALIGNMENT.md` | ✅ |
| Rotation de stack | `docs/ops/PROD_STACK_ROTATION.md` | ✅ |

**Conclusion** : la documentation de suivi existe déjà et est à jour. Le rapport
quotidien est produit par un job externe (non versionné ici).

### Amélioration proposée (tranche de code)

Un **garde de cohérence** qui échoue en CI si un seuil documenté n'est plus
cohérent avec la configuration réelle :

1. lire les seuils de `COUTS_ET_PERF.md` ;
2. comparer au nombre de services Render déclarés dans la topologie ;
3. échouer si `services_pingés_24_7 > 1` (la cause exacte de la suspension).

C'est un test pur, sans appel réseau, qui **empêche la récidive** : la
configuration fautive du 2026-10-20 serait refusée en PR.

> ⚠️ **Non implémenté dans cette passe** : le garde doit lire une source de
> vérité machine-lisible (aujourd'hui les seuils sont en Markdown libre). Il faut
> d'abord extraire les seuils dans un fichier structuré (`docs/ops/quotas.json`),
> ce qui est un changement de convention à valider.

---

## 4. Recommandation sur le plan Render

| Option | Coût | Effet |
|---|---|---|
| **A. Un seul service pingé** (recommandé) | 0 € | Reste sous 750 h, supprime la cause |
| B. Passer au plan payant | ~7 $/mois/service | Supprime la limite, mais dépense |
| C. Arrêter le ping 24/7 | 0 € | Le service s'endort (cold start ~30 s) |

**Recommandation : A.** C'est gratuit, immédiat, et corrige la cause
structurelle. L'option B est une dépense qui relève de la porte (c).

---

## 5. Découpage

| Tranche | Contenu | Bloquée ? |
|---|---|---|
| **T0** | Réveiller le service, réduire à un ping | Non — **action ops, à faire maintenant** |
| **T1** | Extraire les seuils dans `docs/ops/quotas.json` | Non |
| **T2** | Garde CI « un seul service pingé 24/7 » | Non (après T1) |
| **T3** | Renseigner les secrets Neon | Non — action ops |
| **T4** | Passage au plan payant | **Oui — porte (c)** |

**T0 et T3 sont les seules actions à effet immédiat** et ne dépendent pas du
dépôt. T1/T2 empêchent la récidive. T4 est une décision de dépense.
