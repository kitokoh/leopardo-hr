# P03 — Vitrine & présentation (« le projet présentable à tout moment, avec les bons mots »)

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** PM (message) + gardiens de surface (exécution)
> **Portée :** toutes les surfaces où Leopardo RH est présenté au monde : vitrine web produit,
> site vitrine des tenants (BC-27 SHOWCASE), README/repo GitHub, stores mobiles, démos, pitchs
> commerciaux et sociaux, dossier de réponse aux appels d'offres, présentations. Hors champ :
> la communication interne (issues, rapports techniques).
> **Ancrage existant :** `README.md`, `docs/REFERENTIEL_PRODUIT/APV.md` (pitch 1 phrase, 4 piliers,
> 12 Lois), `docs/REFERENTIEL_PRODUIT/STATUTS.md` (statuts fonctionnels opposables),
> `docs/specifications/SOLUTION_SITE_VITRINE.md` (BC-27), `docs/GTM/`, `docs/STRATEGIE_COMMERCIALE/`,
> `docs/GOTO_MARKET/` (SOCIAL_MEDIA_PITCHES), `docs/ops/DOMAINS.md`.

## 1. Objet

Garantir qu'à **tout moment**, n'importe quelle surface de présentation tient un discours
**unique, exact et à jour** — les bons termes, sans sur-promesse — et que ce discours est
**audité chaque fin de mois** (revue mensuelle) et à chaque sortie (P01).

Constat moteur : le dépôt a déjà corrigé plusieurs promesses fausses ou prématurées
(#3257 « client desktop téléchargeable » alors qu'aucun installateur n'existe ; #4202 badge
d'économies non prouvé ; #3863 case studies non vérifiables ; #3888 lead marketing fail-closed).
Le coût d'une vitrine inexacte est la confiance — ce protocole la protège.

## 2. Surfaces & messages canoniques

| Surface | Emplacement | Message canonique source |
|---|---|---|
| Site produit (marketing) | `front/web/` (Next.js, Vercel) | `README.md` + APV (positionnement, fonctionnalités, tarifs) |
| Vitrines tenants | BC-27 SHOWCASE (`api/app/Modules/Showcase`, `/vitrine/{slug}`) | Sections/thèmes du tenant — ne contient **aucune** donnée interne |
| Dépôt GitHub | `README.md`, description repo, topics | `README.md` (badges, product map, « One platform. Several business domains ») |
| Stores mobiles + Firebase App Distribution | fiches stores, notes de version | `docs/validation/MOBILE_STORE_READINESS.md`, CHANGELOG |
| Pitchs commerciaux / sociaux / AO | `docs/GTM/`, `docs/STRATEGIE_COMMERCIALE/`, `docs/commercial/` | APV (pitch 1 phrase) + `docs/REFERENTIEL_PRODUIT/STATUTS.md` |
| Démos (compte démo, parcours guidé) | `dev-hub/demo/`, `/signup` vitrine | Honnêteté : démo ≠ inscription (quick card) |

## 3. Règles

1. **Un seul message source.** Toute phrase publique (vitrine, README, pitch, store) dérive du
   message canonique (README/APV/STATUTS). Toute évolution produit commence par mettre à jour la
   source, puis les surfaces — jamais l'inverse (même règle que `APV.md` : « toute décision qui
   contredit ce document doit d'abord modifier ce document »).
2. **Vocabulaire exact (lexique opposable)** — utiliser ces termes, pas de synonymes flous :
   | Terme public | Définition publique |
   |---|---|
   | « plateforme modulaire open-source de gestion des opérations » | multi-sites / terrain ; core + BC isolés |
   | « Leopardo RH » | l'expérience RH & paie, coeur historique de la plateforme |
   | « verticales » | solutions métier complètes (restauration, travel, fuel, éducation, livraison…) |
   | « multi-pays / multi-paie » | uniquement les pays réellement couverts (registre `docs/payroll/*_COMPLIANCE.md`) |
   | « app mobile » | apps Flutter distribuées (Firebase/Android/iOS) — jamais « disponible sur store » si non soumise |
   | « client desktop Windows/macOS » | **interdit en vitrine tant qu'aucun installateur public n'existe** (#3257) |
   | « IA » | Leo IA et fonctionnalités réellement livrées — jamais de roadmap présentée comme existante |
3. **Règle d'honnêteté (zéro sur-promesse).** Une fonctionnalité non livrée (statut ≠ `live`
   dans `STATUTS.md`) ne peut apparaître que comme *roadmap* explicite. Les mentions « bientôt
   disponible », « inclus dans tous les plans », badges de gains, études de cas et logos clients
   exigent une preuve interne référencée dans la PR qui les ajoute.
4. **Langues & SEO :** la vitrine tient FR/EN/TR/AR (i18n, JSON-LD, sitemap, PWA — issues #3260-3262,
   #3807, #4004). Toute chaîne publique ajoutée l'est dans **toutes** les locales dans la même PR.
5. **Chaque surface a un gardien** (défini en revue mensuelle) qui valide les PRs touchant sa copie.
6. **Toute PR qui modifie une promesse publique** (ajout/retrait de capacité annoncée) mentionne
   `STATUTS.md` et la surface concernée dans sa description — et passe par le gardien de surface.

## 4. Définitions de fait

| Niveau | Définition | Preuve |
|---|---|---|
| **DoD copie vitrine** | Texte exact, sourcé au message canonique, i18n complète, aucune sur-promesse, liens vivants | PR + revue gardien de surface |
| **DoMarket communication** | Annonce de sortie (P01) alignée aux surfaces : stores, vitrine, README, CHANGELOG | Issue Release + checklist P03 remplie |

## 5. Gardes & automatisation

| Existant | À créer (issues) | Vérifie |
|---|---|---|
| `web-marketing-ci.yml`, `lighthouse.yml`, `pages-deploy.yml`, `e2e-staging.yml` (liens commerciaux) | — | Qualité technique vitrine |
| `i18n-enterprise.yml`, `check-hardcoded-accented-messages.sh` | — | i18n / chaînes en dur |
| specs « honnêteté » (#3257, #3863, #4202, #3888) | — | Précédents de sur-promesse |
| — | **Issue : registre `docs/REFERENTIEL_PRODUIT/MESSAGE.md`** (message canonique versionné : pitch, positionnement, promesses autorisées/interdites, lexique) | Source unique du discours |
| — | **Issue : garde anti-sur-promesse** (scan des termes à risque « desktop », « bientôt », « tous les plans » sur la vitrine et le README) | Automatiser §3.3 |

## 6. Rôles

| Rôle | Responsabilités |
|---|---|
| PM | Gardien du message : arbitre le lexique, valide toute évolution de positionnement, pilote la revue mensuelle |
| Gardiens de surface (par surface) | Relisent les PRs de copie de leur surface |
| Agents | Ne publient jamais de copie publique sans source canonique ; signalent toute promesse non tenue vue en CI, en test ou en revue |

## 7. Indicateurs & preuves

- **Audit mensuel « vitrine & discours »** (dernier jour ouvré, couplé à la revue des protocoles) :
  1. chaque surface listée §2 est ouverte et relue (liens HTTP 200, pas de page morte) ;
  2. diff du mois sur la copie publique : chaque changement est tracé à une issue/PR ;
  3. contrôle ponctuel de sur-promesse (mots à risque + statuts `STATUTS.md`) ;
  4. SEO/i18n : sitemap, JSON-LD, locales FR/EN/TR/AR, images sociales (#3261) ;
  5. livrable : compte-rendu 1 page `docs/GESTION_PROJET/REVUE_VITRINE_YYYY-MM.md` + issues créées.
- Lighthouse ≥ seuils sur les pages clés (accueil, tarifs, /download, /signup).
- Zéro sur-promesse détectée en relecture ou par signalement (cible : 0).

## 8. Revue mensuelle — questions spécifiques

- [ ] Le positionnement a-t-il changé ce mois-ci ? (ROADMAP, STATUTS, nouvelles verticales)
- [ ] Quelles surfaces ont été oubliées dans la dernière annonce ?
- [ ] Le lexique §3.2 est-il toujours exact (pays couverts, stores, desktop) ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — message canonique + audit mensuel vitrine |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
