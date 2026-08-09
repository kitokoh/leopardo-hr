# Refonte UI/UX Premium — État des lieux & Maquettes (Piliers A-D)

> Issues #1625, #1626, #1627, #1628 — session 2026-08-09.
> Référence : `docs/specifications/REFONTE_VISUELLE_GLOBALE.md` (validée).

## Maquettes de référence (pack 2026-08-09)

Le pipeline Stitch (MCP) n'étant pas disponible dans l'environnement agent de la
session 2026-08-09, les maquettes de référence ont été générées et versionnées
sous `assets/design/mockups/`. Elles servent de cible visuelle pour le Design
System Premium (glassmorphism, contrastes profonds, palette émeraude/cyan,
typographie Inter).

| Pilier | Surface | Maquette |
|---|---|---|
| A | Portail Web Admin/Employé (Vue.js) | `assets/design/mockups/mockup-pilier-a-admin-cockpit.png` |
| B | Vitrine Commerciale (Next.js) | `assets/design/mockups/mockup-pilier-b-vitrine-hero.png` |
| C | Suite Apps Mobiles Flutter (Employee) | `assets/design/mockups/mockup-pilier-c-mobile-employee.png` |
| D | Application Kiosque ZKTeco | `assets/design/mockups/mockup-pilier-d-kiosk.png` |

## État d'avancement réel (audit 2026-08-09)

### Pilier A — Portail Web Admin (`front/admin-dashboard/`, Vue.js) — #1625

- [x] **Surfaces `glass-*` et ombres `shadow-glass`** : tokens dans
  `src/style.css` (`.glass-card`, `.glass-effect`, `.glass-light`,
  `.glass-dark`, `.card`, `.stat-card`, `shadow-glass`, `shadow-premium`).
- [x] **Mode sombre natif** : `src/stores/theme.js` (persistance
  `localStorage`, classe `dark` sur `<html>`, init dans `App.vue`), variants
  `dark:` systématiques.
- [x] **Cockpit refondu** : `DashboardView.vue` (cartes `card` glass, KPI
  `StatsCard` avec glow gradient, table « Priorités Portefeuille », cartes
  workflow `hover:shadow-premium`).
- [x] **Navigation latérale** : `Sidebar.vue` (verre dépoli `backdrop-blur-xl`,
  badge notifications, état actif gradient brand).
- [ ] **Maquettes Stitch** : remplacées par le pack de référence ci-dessus
  (pipeline Stitch à reconnecter si souhaité).

### Pilier B — Vitrine Commerciale (`front/web/`, Next.js) — #1626

- [x] **Hero Banner spectaculaire** : `HeroSection.tsx` + `HeroProductVisual.tsx`
  (aurora gradients, produit flottant, formulaire email rapide
  `source=hero_email_trial`).
- [x] **Micro-animations au scroll** : 23/26 sections utilisent reveal
  (`IntersectionObserver`, `AnimatedCounter`, hooks dédiés).
- [x] **Mise en valeur des 3 apps mobiles + Kiosque** :
  `OperationalProofSection.tsx` (contrat `launch-workflow-contracts.json`).
- [x] **Formulaires fluides** : `SignupForm` (guided trial, sans mot de passe),
  hero email-only.
- [ ] **Maquettes Stitch** : pack de référence ci-dessus.

### Pilier C — Suite Mobile Flutter (`front/mobile_apps/`) — #1627

- [x] **Typographie Inter + palette cohérente** : `leopardo_core`
  `app_typography.dart` (Inter), `app_colors.dart` (AppColors), `app_theme.dart` ;
  apps employee/manager consomment le thème partagé.
- [x] **StartupGate premium** : `startup_gate.dart` (premier rendu immédiat,
  overlay non bloquant, garde anti page noire `validate-mobile-runtime-smoke.ps1`).
- [x] **Tuiles d'actions rapides GlassTile** : `glass_tile.dart` dans
  `leopardo_core/core/widgets/`, généralisable aux écrans d'accueil.
- [ ] **Généralisation GlassTile sur tous les écrans d'accueil** : socle posé,
  poursuite au fil des écrans (suivi F-27 convergence).

### Pilier D — Kiosque ZKTeco (`front/zkteco-kiosk/`) — #1628

- [x] **Dark Mode profond** : design industriel sombre (radial gradients,
  fond `#091425`, accent émeraude/cyan).
- [x] **Gros boutons d'action clairs** : `checkInButton`/`checkOutButton`
  (min-height 52px+, états actif/disabled).
- [x] **Feedback visuel et sonore immédiat** (nouveau 2026-08-09) : module
  `feedback` Web Audio API (double bip ascendant succès / buzz échec) + pulse
  `#statusBox` (`status-pulse-ok/error`), test `tests/feedback.test.mjs`.
- [ ] **Maquettes Stitch** : pack de référence ci-dessus.

## Principes verrouillés (Design System)

- Couleurs : dictionnaire `COULEURS.md` (ex. `#10B981` RH, `#F59E0B` Finance) —
  pas de couleur hardcodée hors design system.
- Composants : boutons/inputs/cartes avec proportions identiques
  (`rounded-xl/2xl`, ombres `shadow-glass`).
- Clarté cognitive : espacements généreux, typographie hiérarchisée (Inter,
  tracking-tight sur les titres).
- Processus : Audit → Maquette → Validation → Code (le pack de référence
  valide la cible visuelle de la session).
