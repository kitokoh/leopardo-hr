# Coûts, quotas et performance — état mesuré et seuils

> Épic #8670 · issue **#8674** (visibilité coûts/perf avant les bêta-testeurs).
> Toutes les valeurs marquées **MESURÉ** proviennent de l'API du fournisseur le
> **2026-10-18** (render.com/v1, vercel.com/v1-v9, console.neon.tech/v2,
> cloudflare.com/client/v4, api.github.com). Les offres gratuites évoluent :
> re-vérifier avant tout arbitrage budgétaire.

## 1. Résumé exécutif

La plateforme ne coûte **0 €** aujourd'hui, et c'est précisément le problème :
elle a déjà dépassé **deux quotas gratuits** et **perdu la prod deux fois**
(suspension Render 28/09→01/10, prod figée par Vercel depuis le 20/09).

Le coût du plan payant n'est pas le risque principal. Le risque principal est
de croire qu'on peut tenir des bêta-testeurs sur des offres gratuites **tout en
pingant 24/7**. Ce document chiffre le point de rupture.

## 2. Ce qui est facturé, et par quoi

| Ressource | Fournisseur | Offre actuelle | Unité de facturation | Ce qui la consomme chez nous |
|---|---|---|---|---|
| API prod | Render | free | **heures d'exécution**/mois (750 sur le compte) | le service allumé, **ping inclus** |
| API dev (×3 stacks possibles) | Render ×3 | free | idem, **par compte** | stack A + B + C si toutes allumées |
| Web prod/dev | Vercel | **Hobby** | **déploiements/jour (100)**, bande passante 100 Go | chaque push sur main (dev) + chaque tag (prod) + retries |
| Admin prod/dev | Cloudflare Pages | free | **builds/mois (500)**, requêtes illimitées | chaque déploiement admin |
| Base prod | Neon | free | **CU-hours (100/mois)**, stockage 0,5 Go, **transfert 5 Go** | requêtes API, queue `database`, backups |
| Base dev | Neon ×N | free | idem, par projet | tests + seeds |
| KV | Upstash / Render KV | free | commandes/mois, taille | cache/sessions si activé |
| Backups | Backblaze B2 | free 10 Go | stockage + **downloads (classe B)** | `database-backup.yml` (quotidien) |
| CI | GitHub Actions | free 2 000 min/mois (privé) | minutes runner | chaque workflow, **chaque ping de keepalive** |

## 3. Les trois ruptures déjà constatées

### 3.1 Render : quota horaire du compte (MESURÉ)

- `GET /v1/services` : service `leopardo-prod` (free, frankfurt).
- Événements : `suspender_added` le **2026-09-28**, `suspender_removed` le
  **2026-10-01**, motif *Monthly Quota Exceeded*.
- Le compte dev est, lui, au statut `suspended: billing` (constat du
  2026-10-17, service down depuis le 2026-09-20).

**Lecture :** 750 h/mois ≈ 31 jours × 24 h. Un seul service pingé en
permanence consomme **tout** le quota du compte. Deux services sur le même
compte (prod + un secours, ou dev A + dev B) **dépassent le quota par
construction**, même sans utilisateur.

### 3.2 Vercel : quota de déploiements quotidiens (MESURÉ)

- Run `deploy-prod.yml` du tag **v4.33.0** (2026-09-20) : job
  *Deploy Web client to Vercel (prod)* en **échec**, message
  `api-deployments-free-per-day` — **plus de 100 déploiements dans la journée**.
- Conséquence : **aucun tag n'a été publié depuis**, donc la prod tourne
  toujours sur le code de la v4.33.0 alors que `main` a continué d'avancer.

**Lecture :** sur Hobby, 100 déploiements/jour se consomment aussi par les
*retries*, les déploiements de preview et les reconstructions. Un dépôt actif
avec 2 fronts et plusieurs workflows peut y arriver sans aucune release.

### 3.3 Neon : transfert partagé (à surveiller, non encore cassé)

Un projet Neon free offre 100 CU-h et **5 Go de transfert/mois**. Le tier dev
utilise des stacks dont la queue est en base (`QUEUE_CONNECTION=database`) :
**chaque worker qui interroge la queue consomme du transfert**, et deux stacks
branchées sur la même base doublent cette consommation.

## 4. Ce que la bascule change (et ne change pas) au coût

| Scénario | Effet sur le coût |
|---|---|
| Bascule prod A → B (même base) | **neutre** si B remplace A ; **double** si A reste allumée et pingée — c'est pourquoi le keepalive est fenêtré et que A doit repasser en standby (verrous de queue à `false`) |
| Ajout d'une stack dev C | +1 service Render free = **+750 h de quota à trouver** (compte distinct obligatoire) |
| Bascule automatique (watchdog) | neutre en quota, **coûteux en risque** : un faux positif coupe la prod |
| Passage Render Starter sur la prod | ~**7 $/mois** : supprime la suspension pour quota et le cold start de 50 s (le seul vrai levier de disponibilité) |
| Passage Vercel Pro | ~20 $/mois : supprime la limite de 100 déploiements/jour (le blocage actuel de la prod) |
| Passage Neon Launch | ~19 $/mois : supprime la limite de CU-h et le transfert 5 Go |
| Passage Cloudflare Pages Pro | ~20 $/mois : builds illimités |

**Ordre de priorité budgétaire, du meilleur rapport au moins bon :**

1. **Render Starter sur l'API prod** (~7 $) — supprime la cause de la
   suspension ET le cold start ; c'est le meilleur euro dépensé.
2. **Vercel Pro** (~20 $) — supprime le blocage de déploiement prod.
3. **Neon Launch** (~19 $) — utile seulement si les CU-h sont réellement
   atteints (à mesurer, pas à supposer).
4. Cloudflare Pages Pro — le moins urgent (500 builds/mois suffisent si l'on
   ne redéploie pas l'admin à chaque push).

Total « prod réellement disponible » : **≈ 46 $/mois**. À comparer au coût
d'un incident pendant une démo bêta.

## 5. Ce que coûte la plateforme en calcul (algorithmes)

Cette section est volontairement **qualitative** : aucun profiling n'a été
exécuté dans cette mission, et inventer des chiffres serait pire que de ne pas
en avoir.

Points chauds identifiés par lecture du code et de la configuration :

| Zone | Ce qui consomme | Signal à surveiller |
|---|---|---|
| Files d'attente en base (`QUEUE_CONNECTION=database`) | polling constant + lignes mortes | `queue-supervision.yml` (jobs en échec, profondeur) |
| Scheduler dans le conteneur web (`WEB_SCHEDULER_LOOP`) | un process PHP en boucle 24/7 sur un service free | consommation Render, latence API |
| Requêtes multi-tenant (`DB_SEARCH_PATH=shared_tenants,public`) | recherche de schéma à chaque requête | latence p95 sur `/health` vs endpoints métier |
| Backups quotidiens (`database-backup.yml`) | dump complet + upload B2 | durée du job, taille du dump |
| Rendu PDF / exports | CPU conteneur | durée des jobs |

**Recommandation :** avant l'ouverture aux bêta-testeurs, poser trois mesures
minimales — latence p95 de `/api/v1/health`, profondeur de la queue, durée du
backup. Le rapport quotidien (`.github/workflows/stack-consumption-report.yml`)
les collecte déjà ; c'est le socle d'un vrai profiling, pas un substitut.

## 6. Plan B prod : ce qui est réellement disponible

| Option | Disponibilité | Coût | Verdict |
|---|---|---|---|
| Stack B Render (compte ibrahim.ali, **0 service MESURÉ**) | immédiate à provisionner | 0 € (free) | ✅ **recommandé** : un second service + le bouton de bascule donnent 2 h de marge |
| Stack B Render + même base Neon | immédiate | 0 € | ✅ oui — c'est le modèle de `render.prod-b.yaml` |
| Base prod B (second projet Neon) | possible | 0 € | ❌ **non** : données divergentes ; le failover de base est `db-standby-failover.yml` (RPO ≤ 6 h) |
| Vercel prod B | possible | 0 € | ⚠️ possible mais **consomme le quota de déploiements du compte B** — à réserver au jour où A est bloqué |
| Cloudflare Pages B | optionnel | 0 € | ⚠️ faible valeur : les Pages ont peu de limites |
| Plan payant Render seul | immédiate | ~7 $/mois | ✅ supprime la cause racine |

**Verdict :** le plan B gratuit existe (stack B), mais il **ne protège pas
contre la cause réelle** (quota Render du compte). Il protège contre une
suspension *ou* une panne d'un service donné. Les deux ensemble — stack B
gratuite **et** Render Starter sur l'API active — couvrent l'essentiel pour un
budget de 7 $/mois.

## 7. Seuils d'alerte proposés

| Indicateur | Seuil « surveiller » | Seuil « agir » |
|---|---|---|
| Heures Render consommées / 750 | 70 % (525 h) | 90 % (675 h) |
| Déploiements Vercel / jour | 60 | 90 |
| CU-h Neon / 100 | 70 | 90 |
| Transfert Neon / 5 Go | 3,5 Go | 4,5 Go |
| Builds Pages / 500 | 350 | 450 |
| Latence p95 `/health` | 800 ms | 2 s (au-delà : cold start ou saturation) |

Ces seuils sont à poser côté supervision (rapport quotidien). Ils ne sont
**pas** branchés sur une bascule automatique tant que la prod est sur une offre
gratuite : un seuil dépassé est une information, pas un ordre.

---

_Réf. : épic #8670, issue #8674. Inventaire détaillé des comptes :
[`COMPTES_PLATEFORME.md`](COMPTES_PLATEFORME.md)._
