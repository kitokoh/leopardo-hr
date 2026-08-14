# Release readiness report — 2026-08-14

> Rapport post-vague multi-pays (release manager, revue de la cascade #1811→#1901).
> Complète le rapport du 2026-06-01 (93/100, Go technique contrôlé) et alimente l'issue #1902.

## Décision

**Go conditionnel pour une release technique (tag) — No-Go pour une déclaration commerciale
« production multi-pays » tant que les P1 de validation expert-comptable et les bugs P1
listés ci-dessous ne sont pas fermés.**

La vague multi-pays (30+ PRs mergées sur `main` le 2026-08-14 : fériés #1811, calendrier
islamique #1812, taux #1813, barèmes #1814, cotisations #1815, F-20 #1816, archive bulletin
#1817, régularisations #1818, chômage DZ #1819, golden CM/CI/SN #1822/#1826/#1828,
déclarations CNPS/CNSS/IPRES #1823/#1830, pilots GA/CG/BF/ML/CI/SN #1824/#1829/#1825/#1827,
résolveur #1868, contrat de calcul #1869, isolation #1870, pays obligatoire #1867 et fixes
CI #1839/#1840/#1906/#1909/#1893/#1900/#1901/#1944) est **architecturalement cohérente** :
pattern `CountryRulesResolver` unique, règles pays par `AbstractCountryRules`, isolation
tenant testée, goldens recalculés à la main. Mais la conformité légale multi-pays reste
**à valider par un expert-comptable** (statut `pilot` assumé dans le code).

## Validation exécutée (2026-08-14)

| Élément | Résultat |
|---|---|
| Cascade de merge : 17 PRs initiales → 0 restante (14:00) | ✅ Toutes mergées, branches supprimées, issues `Closes #…` fermées |
| Revue de code indépendante des PR #1891/#1888/#1890/#1892 (2 reviewers) | ✅ Math goldens re-vérifiés à la main ; faiblesses corrigées (#1906, #1909, #1922/PR #1945) |
| Cohérence des taux CNSS CI / IPRES SN (moteur vs déclarations) | ✅ Vérifié sur `main` : 3,2/4,5/5,75/2,0 % cap 1 647 315 (CI), T2 cadres 2,4/3,6 % (SN) alignés |
| Test d'isolation #1870 (`CountryIsolationMatrixTest`) | ✅ Corrigé (#1906 : assertSame instance partagée + contrat ancré) |
| `Employee` `$fillable` (matricules CNSS/IPRES) | ✅ Corrigé (#1909) |
| CSV injection + dérive des totaux déclarations CI/SN | 🔧 Fix #1922 en revue (PR #1945) |
| `release-readiness.ps1 -Strict` (23/23) | ⏳ Non exécutable dans cet environnement (pas de PowerShell) — à rejouer en CI/ops |
| CI `main` (Tests, Payroll CI, coverage, PHPStan, actionlint) | ⏳ File GitHub Actions saturée (#1903) — runs en cours au 14:00 ; à confirmer vert |

## Verrous P1 ouverts (bloquent la release « sans réserve »)

| Issue | Sujet | Statut |
|---|---|---|
| #1911 | SN abattement 30 % appliqué sur le mauvais brut | Fix en cours (branche `fix/sn-abatement-legal`) |
| #1912 | SN validation expert-comptable avant passage `production` | Ouverte |
| #1913 | CI/SN — bases CNSS-AT (CI) et CSS famille (SN) plafonnées ou non | Ouverte |
| #1915 | BF — barème IUTS incomplet (tranche 27,5 % > 6 M FCFA) | Ouverte |
| #1919 | Paie — jours de congés double-déduits (présence réelle F-20) | Ouverte |
| #1929 | Admin « Taux légaux » — endpoints tenant appelés hors contexte | Ouverte |
| #1930/#1931 | Calendrier islamique — dates non confirmées / données en dur 2024-2027 | Ouvertes |
| #1933 | Migrations public/tenant hors pattern F-17 | Ouverte |
| #1942 | Régularisations #1818 — run recalcule les bulletins d'une autre période | Ouverte |
| #1943 | DZ — préavis : jours calendaires vs jours ouvrés (surpaie ~36 %) | Ouverte |
| #1902 | Préparer la release post-vague (rapport, tag, CHANGELOG versionné) | Ouverte — ce rapport la nourrit |

## Recommandations

1. **Immédiat** : confirmer CI `main` verte après épuisement de la file (#1903) ; merger #1945 (CSV) quand vert.
2. **Avant tag** : fermer les P1 ci-dessus (les issues existent toutes avec critères d'acceptation, statut `Agent-Ready`).
3. **Avant promesse commerciale multi-pays** : validation expert-comptable CI/SN/CM/BF/ML/GA/CG (issues #1912/#1913/#1904/#1875), recette mobile device, kiosk matériel.
4. **Tag de release** : `v0.1.0`-équivalent selon PILOTAGE (CODE_VERSION), section CHANGELOG dédiée, via `release.yml` (#1902).

## Conclusion

La vague multi-pays est **techniquement solide et mergeable** — c'est un excellent état pour
une release interne/technique. La publication commerciale « production multi-pays » reste
conditionnelle à la fermeture des P1 conformité/validation listés ci-dessus, tous déjà
tracés en issues. Ce rapport devra être mis à jour quand `release-readiness.ps1 -Strict`
sera rejoué et que la CI de `main` aura confirmé le vert.
