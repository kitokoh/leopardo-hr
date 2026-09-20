# Rapport mensuel d'acquisition — 2026-09 (premier rapport)

> Boucle de mesure de la stratégie d'acquisition (`STRATEGIE_ACQUISITION_2026.md` §10,
> issue #7877). Complète le rapport rituel général `2026-09.md` (issue #7087) sur le volet
> **acquisition** uniquement. Registre des canaux : `REGISTRE_CANAUX.md` · Convention
> UTM : `CONVENTION_UTM.md`.
>
> **Statut** : squelette rempli au 2026-09-20 — plusieurs métriques sont marquées
> « à relever (owner) » car l'accès analytics/Search Console n'est pas encore branché
> (voir §6 « Actions owner restantes »). Aucun chiffre publié sans mesure datée
> (règle `METRIQUES_VITRINE.md`).

**Période couverte** : 2026-09-01 → 2026-09-30 · **Exécutant** : gardien vitrine · **Décideur** : fondateur/PM

---

## 1. Sessions vitrine

| Métrique | Valeur | Méthode de collecte |
|---|---|---|
| Sessions totales vitrine | à relever (owner — analytics non branché) | Analytics vitrine (`front/web`, Vercel) : total sessions du mois. Outil à brancher — cf. §6.3. En attendant : Vercel → projet vitrine → onglet « Analytics » (si activé) donne au minimum les visiteurs. |
| Sessions organiques (SEO) | à relever (owner) | Analytics : filtre canal `organic` ; recoupé avec Search Console → « Performances » → clics du mois. |
| Sessions par `utm_source` | à relever (owner) | Analytics : dimension `utm_source` (convention `CONVENTION_UTM.md`). Sans analytics, aucune session UTM n'est mesurable ce mois-ci. |

## 2. Clics Search Console — requêtes « alternative / vs »

| Métrique | Valeur | Méthode de collecte |
|---|---|---|
| Clics sur requêtes contenant « alternative » ou « vs » | à relever (owner — propriété Search Console non vérifiée) | Search Console → propriété du domaine vitrine → « Performances » → filtre Requête : *contient* `alternative`, puis *contient* `vs` ; période = le mois ; relever clics + impressions + position moyenne. |
| Impressions pages `/alternatives/*` | à relever (owner) | Search Console → « Performances » → filtre Page : *contient* `/alternatives` (pages lot 1 en construction — valeur attendue faible/nulle ce mois). |
| Pages indexées | à relever (owner) | Search Console → « Indexation » → « Pages » après soumission du sitemap (cf. §6.2). |

> Ces données décident du **lot 2** des pages alternatives (critère d'acceptation #7877) —
> décision à prendre au premier rapport où Search Console a ≥ 28 jours de données.

## 3. Trials par source

| Métrique | Valeur | Méthode de collecte |
|---|---|---|
| Inscriptions trial (total) | à relever (owner) | Plateforme super-admin : nombre de tenants créés en essai sur le mois (source interne — pas d'accès depuis ce repo). |
| Trials par `utm_source` | à relever (owner — nécessite propagation UTM jusqu'au signup) | Chaîne à mettre en place : (1) analytics capture les UTM à l'atterrissage ; (2) la vitrine propage les UTM jusqu'au formulaire d'inscription (champ caché ou attribut du tenant) ; (3) rapprochement mensuel trials ↔ `utm_source`. Tant que (2) n'existe pas, noter « non attribuable ». |
| Tenants actifs à J+30 | à relever (owner) | Plateforme super-admin : cohorte des trials du mois précédent encore actifs à J+30. |

## 4. Étoiles GitHub

| Métrique | Valeur | Méthode de collecte |
|---|---|---|
| Étoiles `kitokoh/leopardo-hr` (fin de mois) | 13 (mesuré le 2026-09-20 — re-relever au 30/09) | `curl -s https://api.github.com/repos/kitokoh/leopardo-hr \| jq .stargazers_count` (API publique, reproductible) — relever le dernier jour ouvré du mois. |
| Δ étoiles vs mois précédent | n/a (premier rapport — baseline) | Différence avec la valeur du rapport précédent. |
| Trafic repo (visites/clones 14 j) | à relever (owner — nécessite droits admin repo) | GitHub → repo → « Insights » → « Traffic » (fenêtre glissante 14 jours : relever le dernier jour du mois). |

## 5. Canaux — état du mois

Photo du registre (`REGISTRE_CANAUX.md`) au 2026-09-20 :

- **Actif** : GitHub (repo + Pages).
- **En construction** : vitrine SEO d'interception (pages `/alternatives` lot 1).
- **À publier / préparer** : AlternativeTo, awesome-selfhosted, Product Hunt, Capterra/GetApp,
  G2, OpenAlternative, LibHunt, StackShare, Crunchbase, F6S, VC4A, Wellfound, BetaList, Show HN.
- **À confirmer (comptes fondateur)** : LinkedIn, X, YouTube.
- Fiches publiées ce mois : **0**. Lancements : **0**.
- Livré ce mois (mesure) : registre canaux consolidé, convention UTM (`CONVENTION_UTM.md`),
  premier rapport mensuel (ce fichier).

## 6. Actions owner restantes

Étapes précises, à exécuter par le **fondateur** (accès comptes requis) :

### 6.1 Vérifier la propriété Google Search Console (domaine vitrine)

1. Ouvrir https://search.google.com/search-console et se connecter avec le compte Google
   propriétaire du projet.
2. « Ajouter une propriété » → type **Préfixe d'URL** (le type « Domaine » exige un accès
   DNS — impossible sur `*.vercel.app`) :
   - `https://gestionemployer-backend.vercel.app` (vitrine live actuelle — `docs/ops/DOMAINS.md`)
   - `https://leopardo-prod.vercel.app` (vitrine prod — même procédure)
3. Méthode de validation recommandée : **balise HTML** (meta `google-site-verification`
   dans le `<head>` du layout `front/web`) ou fichier HTML à la racine ; commit + déploiement
   Vercel, puis « Valider ».
4. À la mise en service de `leopardo-rh.com` (#3452) : ajouter une propriété **Domaine**
   (validation DNS TXT) et re-faire §6.2.

### 6.2 Soumettre le sitemap

1. Vérifier que `https://gestionemployer-backend.vercel.app/sitemap.xml` répond en 200 et
   contient les URLs attendues (dont les futures pages `/alternatives/*`).
2. Search Console → propriété vitrine → menu « Sitemaps » → saisir `sitemap.xml` → « Envoyer ».
3. Contrôler sous 48-72 h : statut « Réussite » + pages découvertes dans « Indexation → Pages ».

### 6.3 Brancher l'analytics vitrine

1. Choisir l'outil (décision owner ; candidats compatibles UTM : Vercel Web Analytics —
   activation 1 clic dans le dashboard Vercel du projet, Plausible ou Umami si l'on veut
   les rapports par `utm_source` en standard).
2. Ajouter le script/composant dans `front/web` + variables d'env dans Vercel ; déployer.
3. Vérifier qu'une visite avec `?utm_source=x&utm_medium=social&utm_campaign=growth-2026`
   apparaît bien dans l'outil avec ses UTM.
4. (Pour §3) Décider et planifier la propagation des UTM jusqu'au signup trial (issue à
   ouvrir, label `BC-12 GROWTH`).

### 6.4 Divers

- Confirmer les comptes LinkedIn / X / YouTube (statut « à confirmer » au registre) et
  mettre `REGISTRE_CANAUX.md` à jour.
- Relever les métriques « à relever (owner) » de ce rapport le dernier jour ouvré du mois
  et figer les valeurs (datées) dans ce fichier.

## 7. Décisions à remonter

- Choix de l'outil analytics vitrine (§6.3).
- Lot 2 des pages alternatives : **reporté** au premier rapport avec ≥ 28 jours de données
  Search Console (prérequis §6.1-6.2).

## Verdict du mois

- Boucle de mesure : 🟠 — cadre en place (registre + convention UTM + gabarit de rapport),
  collecte non opérationnelle tant que §6.1-6.3 ne sont pas exécutées.
- Prochain rapport : `2026-10-acquisition.md` (dernier jour ouvré d'octobre).
