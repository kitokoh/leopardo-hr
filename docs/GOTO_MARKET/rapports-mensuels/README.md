# Rapports mensuels — rituel « fin de mois » (vitrine, design, architecture, RETEX, protocoles)

> Cadre : protocoles `docs/PROTOCOLES/` (P01-P07, corpus sur main) — rituels de fin de mois
> proposés par les protocoles P03 (vitrine) / P05 (design) / P07 (architecture 2 volets). Issue #7066.
> **Quand** : dernier jour ouvrable du mois. **Qui** : gardien vitrine (prépare) + PM
> (décide). **Sortie** : un rapport par mois, déposé ici : `YYYY-MM.md`.

## Déroulé du rituel

1. **Vitrine** : parcourir les surfaces V1-V9 (protocole P03) avec sa checklist ; vérifier
   l'alignement vitrine ↔ déploiement et le wording (`docs/REFERENTIEL_PRODUIT/TERMES.md`).
2. **Design (protocole P05)** : parcours visuel des surfaces clés, tokens synchronisés
   (`COULEURS.md` ↔ code — garde `dev-hub/tools/check-design-token-sync.py`), golden tests,
   inventaire `docs/REFERENTIEL_PRODUIT/DESIGN_TOKENS_INVENTAIRE.md` mis à jour.
3. **Architecture 2 volets (protocole P07)** : rejouer sa checklist : registre des
   environnements à jour (`docs/ops/ETAT_DEV_PROD_2026-09-09.md`, `DOMAINS.md`), versions
   dev/prod relevées, secrets/backups/monitoring OK.
4. **RETEX** : tri des issues `retex` en attente (verdict : à faire / à documenter /
   wontfix / à fondre dans un protocole) — cf. `docs/GESTION_PROJET/MOISSON_LECONS.md` (leçons).
5. **Protocoles** : revue du corpus lui-même (les règles sont-elles suivies ? à
   durcir ?) — les modifications passent par des PR `docs:`.
6. Ouverture des issues d'écart (labels `vitrine` / `design` / `infra` / `retex` /
   `protocole`, BC si pertinent) + rédaction du rapport ci-dessous.

## Modèle de rapport

```markdown
# Rapport mensuel — YYYY-MM
État global : 🟢 / 🟠 / 🔴

## Vitrine (V1-V9)
| Surface | État | Écarts | Issue(s) |
|---|---|---|---|
| V1 README | … | … | #… |

Wording validé pour le mois : [2-3 phrases, cf. MESSAGE_MAP.md]
Faits vérifiés : [chiffres / modules / versions contrôlés]

## Design
État : 🟢/🟠/🔴 — tokens OK ? [ ] — golden tests verts ? [ ] — écarts : #…

## Architecture 2 volets (ENV-9)
Registre à jour ? [ ] — versions dev/prod : API …/…, web …/…, admin …/…
Secrets/backups/monitoring : [ ] — écarts : #…

## RETEX triés (RET-6)
| Issue | Verdict | Note |
|---|---|---|
| #… | à faire | … |

## Protocoles
Mises à jour du mois : #… — propositions de durcissement : #…

## Prochaines échéances
[stores, launches, salons, posts, tranches desktop…]
```

## Archives

| Mois | Rapport | État |
|---|---|---|
| 2026-09 | (premier rituel — à produire) | ⏳ |
| 2026-09 (acquisition) | `2026-09-acquisition.md` | 🟠 squelette rempli — valeurs « à relever (owner) » (#7877) |
