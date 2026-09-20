# Kits d'exécution — listings, lancements, social (GROWTH 2026 T4)

> Kits **prêts-à-coller** dérivés des briefs `../platform-submissions/`. Chaque kit contient
> les textes finalisés, les réponses aux formulaires, la checklist pas-à-pas et les assets requis.
> **Cadre bloquant** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (pitch canonique, promesses
> interdites), `METRIQUES_VITRINE.md` (chiffres datés uniquement), positionnement
> « suite métier » (#7428 — jamais « logiciel RH » / « HR software » comme catégorie).

## Règles communes (toutes plateformes)

1. **Aucune action externe sans validation fondateur** — comptes, publication, paiements : owner uniquement.
2. **URL canonique stable + démo fonctionnelle + captures fraîches** avant toute publication
   (checklist `../platform-submissions/README.md`).
3. **UTM sur chaque lien sortant** : `?utm_source=<plateforme>&utm_medium=<listing|social|community|press>&utm_campaign=growth-2026`.
4. **Registre** : chaque fiche/profil/lancement publié = une ligne dans `../REGISTRE_CANAUX.md` le jour même.
5. **Promesses interdites** : « conformité légale validée » (règles paie pays = statut **pilot**),
   chiffres d'adoption non sourcés, « disponible sur iOS » tant que TestFlight n'est pas public,
   prix fermes hors page pricing à jour.

## Kits

| Kit | Issue | Canaux |
|---|---|---|
| `annuaires-oss.md` | #7871 | AlternativeTo, OpenAlternative, LibHunt, StackShare |
| `awesome-selfhosted.md` | #7872 | awesome-selfhosted (grille d'éligibilité + procédure PR) |
| `avis-b2b.md` | #7873 | Capterra/GetApp, G2 + campagne d'avis pilotes |
| `launch-ph-hn.md` | #7874 | Product Hunt + Show HN (lancement coordonné) |
| `ecosysteme-startup.md` | #7875 | Crunchbase, F6S, VC4A, Wellfound, BetaList + presse tech Afrique |
| `social-communautes.md` | #7876 | LinkedIn/X + groupes FB/WhatsApp (calendrier 4 semaines) |

## Faits produit vérifiés (à réutiliser tels quels)

| Fait | Valeur | Vérifié le |
|---|---|---|
| Licence | MIT (`LICENSE` à la racine) | 2026-09-20 |
| Dépôt | https://github.com/kitokoh/leopardo-hr | 2026-09-20 |
| Landing | https://kitokoh.github.io/leopardo-hr | 2026-09-20 |
| Premier tag | `v1.0-staging` — 2026-04-16 | 2026-09-20 |
| Première release GitHub | `v4.24.0` — 2026-08-11 | 2026-09-20 |
| Docker Compose | racine, `api/`, `edge/` | 2026-09-20 |
| Modules DDD | 27 (mesuré 2026-09-18) | `METRIQUES_VITRINE.md` |
| Endpoints OpenAPI | 798 (mesuré 2026-09-18) | `METRIQUES_VITRINE.md` |
| Pays paie (API) | 21 — **statut pilot** (mesuré 2026-09-09) | `METRIQUES_VITRINE.md` |
| Apps Flutter | 7 apps + `leopardo_core` (mesuré 2026-09-18) | `METRIQUES_VITRINE.md` |
