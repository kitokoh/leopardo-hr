# P05 — Harmonisation du design (une seule identité visuelle sur toutes les surfaces)

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** gardien design (PM valide les orientations)
> **Portée :** toutes les surfaces d'interface : vitrine `front/web/` (Next.js), admin
> `front/admin-dashboard/` (Vue), apps Flutter `front/mobile_apps/*` (8 apps + `leopardo_core`),
> kiosk, future surface desktop (P06), vitrines tenants BC-27. Hors champ : les contenus
> marketing éditoriaux (P03).
> **Ancrage existant :** `docs/REFERENTIEL_PRODUIT/APV.md` (Lois L.05 couleur = domaine, L.07 grille
> partagée), `docs/REFERENTIEL_PRODUIT/COULEURS.md` (tokens Flutter ↔ Tailwind),
> `docs/specifications/DESIGN_SYSTEM_TOKENS.md` (glassmorphism, émeraude #10B981, navy #0b1326,
> grille 8 px, Inter), `docs/specifications/GUIDE_MIGRATION_DESIGN.md`,
> `docs/specifications/REFONTE_VISUELLE_GLOBALE.md`, `dev-hub/prompts/15_DESIGN_AUDIT_UI.md`.

## 1. Objet

Garantir qu'un utilisateur ne **distingue pas** la technologie derrière l'écran : vitrine, admin,
mobile, kiosk, desktop et vitrines tenant partagent les mêmes tokens, la même grammaire visuelle
et les mêmes lois — et que toute dérive soit détectée (par garde ou par revue) avant de partir en
production.

## 2. Sources de vérité design (dans l'ordre)

1. **`docs/REFERENTIEL_PRODUIT/APV.md`** — les 12 Lois ; en particulier :
   - **L.05 — Couleur = domaine** : Vert = RH, Ambre = Finance, Bleu = Sécurité, Violet = IA
     (associations **immuables**) ;
   - **L.07 — Grille partagée** : `AppColors.dart` + `tailwind.config.js` +
     `docs/REFERENTIEL_PRODUIT/COULEURS.md` bougent **ensemble dans la même PR** ;
   - **L.06** (Leo enseigne, l'interface confirme) et **L.02** (mobile = source de vérité).
2. **`docs/REFERENTIEL_PRODUIT/COULEURS.md`** — tokens canoniques (noms + valeurs hex).
3. **`docs/specifications/DESIGN_SYSTEM_TOKENS.md`** — style : glassmorphism, fonds, élévations,
   typographie Inter, rythme 8 px, sidebar 280 px, responsivité.
4. **Tokens d'implémentation :** `leopardo_core` (Flutter : `AppColors`, `AppTypography`) pour le
   mobile ; tokens `glass-*`, `premium-text`, `shadow-glass-*` pour l'admin ; équivalents Tailwind
   pour la vitrine.

## 3. Règles

1. **Aucune couleur, espacement, rayon ou ombre en dur.** Toute valeur visuelle passe par un token
   déclaré dans la source de vérité (L.07). Les anciennes cartes `rounded-lg bg-white shadow`
   sont interdites dans l'admin (quick card) ; les couleurs hors palette sont interdites partout.
2. **Une modification de token = 3 fichiers dans la même PR** (L.07) + mention dans la description
   de la PR ; le garde couleur mobile le vérifie pour Flutter (`validate-mobile-color-tokens.ps1`).
3. **Couleur = domaine** : une nouvelle verticale (ex. Delivery) se voit attribuer sa couleur par
   le gardien design **avant** tout écran — inscription dans COULEURS.md + APV, jamais improvisée
   en cours de dev.
4. **Mobile-first (L.02)** : toute nouvelle interface est conçue d'abord pour l'écran mobile ;
   une vue web sans équivalent mobile n'est légitime que pour un outil back-office managers.
5. **Toute PR qui touche l'UI** fournit des captures avant/après (ou référence au mockup validé) —
   règle déjà portée par le prompt 15 (mockup Stitch validé **avant** code). Une PR UI sans preuve
   visuelle est incomplète.
6. **Pas de « design jetable »** : un écart visuel constaté (mauvais token, layout cassé, police
   hors système) est une issue de BC concerné avec label design — pas une correction locale
   silencieuse qui recrée une divergence.
7. **Vitrines tenants (BC-27)** : l'éditeur de thème n'expose que des variables déclarées
   (couleurs, logo, sections typées) — jamais de CSS libre (décision BC-27 §4) ; les thèmes par
   défaut respectent la palette plateforme.
8. **Accessibilité** : contrastes et cibles tactiles ≥ normes (audit WCAG existant
   `docs/security/WCAG_ACCESSIBILITY_AUDIT.md`) — un changement de token ne régressionne pas les
   contrastes.

## 4. Définitions de fait

| Niveau | Définition | Preuve |
|---|---|---|
| **DoD design d'une PR UI** | Tokens uniquement, loi L.05/L.07 respectée, captures avant/après, i18n des chaînes visibles, responsive (mobile → desktop) | PR + gardes couleur + revue gardien design |
| **Audit design périodique** | Aucune dérive visuelle entre surfaces | Rapport d'audit (prompt 15 / checklist §7) |

## 5. Gardes & automatisation

| Existant | À créer (issues) | Vérifie |
|---|---|---|
| `validate-mobile-color-tokens.ps1` (Flutter) | — | Tokens mobiles alignés COULEURS.md |
| `check-hardcoded-accented-messages.sh`, i18n gates | — | Chaînes d'UI externalisées |
| Prompt 15 (audit/refonte UI) | — | Process de refonte avec mockup validé |
| — | **Issue : garde « tokens web/admin »** (scan Tailwind/Vue des couleurs hex hors palette et des classes legacy `bg-white shadow`) | Étendre la règle 1 à web/admin |
| — | **Issue : garde « une PR UI = captures »** (checklist PR : si `front/**` UI, captures exigées dans le body) | Règle 5 automatisée |
| — | **Issue : registre des dérives design** (issue label `design` + moisson mensuelle) | Traçabilité règle 6 |

### Plan de non-régression visuelle (issue #7105)

Objectif : rendre une dérive visuelle **détectable avant production**, surface par surface —
en complément des gardes couleur existantes (aucun temps ne remplace les autres).

| Temps | Livrable | Portée & chemins exacts | Exécution | Dépend de |
|---|---|---|---|---|
| **T1 — Diff d'images Playwright** | `toHaveScreenshot` (Playwright) sur les parcours clés vitrine web + admin | `front/web/e2e/**` et `front/admin-dashboard/e2e/**` : landing vitrine, login, dashboard admin, fiche publique (BC-27/28 quand dispo). Références versionnées, masques sur zones dynamiques | Job CI sur PR (workflows e2e existants) | — |
| **T2 — Golden tests d'images Flutter** | Méthodologie + premiers golden sur les widgets partagés du core | `front/mobile_apps/leopardo_core/lib/core/widgets/**` (glass_card, leopardo_badge, empty_state…) ; goldens par plateforme. Méthodologie : `docs/testing/GOLDEN_IMAGES_FLUTTER.md` — ⚠️ distinct du golden **backend** `docs/testing/GOLDEN_TESTS.md` | `flutter test` (job mobile) ; mise à jour volontaire via `--update-goldens`, diff revu comme du code | — |
| **T3 — Garde de synchronisation des tokens** | Script comparant les tokens réels des surfaces à la source canonique (exécute L.07) | Valeurs hex de `front/mobile_apps/leopardo_core/lib/core/theme/app_colors.dart` ↔ `front/web/tailwind.config.ts` ↔ `front/admin-dashboard/tailwind.config.js` ↔ `docs/specifications/DESIGN_SYSTEM_TOKENS.md` | Script `dev-hub/tools/check-design-token-sync.sh` + job CI **non bloquant** sur les PR touchant ces fichiers | — |

Règles :
- toute image de référence T1/T2 est versionnée et revue comme du code (mise à jour = PR dédiée montrant le diff) ;
- un écart constaté en recette (P01) ou à l'audit mensuel devient une issue `design`/`lecon` (règle 6), jamais une correction silencieuse ;
- T1 et T3 sont des implémentations dédiées (issues filles de #7105) ; le présent plan en fixe le contrat.

## 6. Rôles

| Rôle | Responsabilités |
|---|---|
| Gardien design | Attribue les couleurs de domaine, arbitre les évolutions de tokens, valide les refontes, tient l'audit périodique |
| PM | Valide les orientations visuelles (décisions de marque) |
| Agents | Appliquent les tokens ; ne créent jamais de valeur visuelle ad hoc ; fournissent captures avant/après |

## 7. Audit périodique & indicateurs

- **Audit design** (mensuel, couplé à la revue) : comparer chaque surface aux tokens canoniques
  (écrans clés), vérifier L.05/L.07, relever les écarts → issues `design`.
- **Indicateurs :** nombre de couleurs hors palette en CI (cible 0) ; PRs UI sans capture
  (cible 0) ; écarts relevés en audit mensuel (tendance ↓).

## 8. Revue mensuelle — questions spécifiques

- [ ] Nouvelle surface entrée au périmètre ce mois-ci (desktop, BC-27, nouvelle verticale) ?
- [ ] Les couleurs de domaine tiennent-elles (L.05) ou des écrans « empruntent » la couleur d'un autre domaine ?
- [ ] Les gardes couvrent-ils toutes les surfaces ou seulement Flutter ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — consolidation des lois/tokens existants en protocole d'harmonisation |
| v0.2 | 2026-09-09 | §5 : plan de non-régression visuelle T1-T3 (issue #7105) — diff Playwright, golden Flutter, garde tokens |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
