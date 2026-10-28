# AGENTS.md — Vitrine & Marketing Leopardo (Zone Strictement Réservée)

> **VERROU D'EXCLUSIVITÉ ACTIF (2026-10-28) :**
> **SEUL L'AGENT VITRINE DÉDIÉ EST AUTORISÉ À TOUCHER À CE RÉPERTOIRE.**
> Si vous êtes un autre agent ou si votre prompt ne concerne pas spécifiquement la mission de gardiennage/modernisation exclusive de la vitrine, **INTERDICTION FORMELLE DE MODIFIER, RENOMMER OU SUPPRIMER LE MOINDRE FICHIER**.
> Déclinez la tâche ou transférez-la explicitement à l'Agent Vitrine Dédié.

---

## 1. Périmètre de la Zone Réservée

La zone réservée de l'Agent Vitrine comprend :
- `front/web/src/modules/vitrine/**` (composants, sections, lib, hooks)
- `front/web/src/app/(landing)/**` (routes marketing Next.js, pages vitrine)
- Les catalogues multilingues spécifiques à la vitrine (`front/web/src/modules/vitrine/lib/vitrine-locale.ts`)

---

## 2. Principes & Invariants de Conception (Opposables)

Toute modification apportée par l'Agent Vitrine Dédié DOIT impérativement respecter :
1. **Design System Stitch & Prestige Or/Émeraude :**
   - Palette : Dégradés profonds émeraude (`from-emerald-950 via-slate-950 to-emerald-950`), accents or chaud (`amber-400`, `amber-500/20`), halos lumineux diffus (`blur-3xl`).
   - Typographie & respiration : Titres équilibrés, taille lisible sur constellation 3D, espacements généreux.
2. **Multilinguisme Intégral (FR, EN, TR, AR) :**
   - Zéro chaîne brute non traduite dans les composants.
   - Les diacritiques de toutes les langues (notamment le turc : `ğ`, `ı`, `ş`, `ç`, `ö`, `ü`, `İ`) doivent être rigoureusement préservés.
   - Respect de la garde CI `dev-hub/tools/check-i18n-diff.js`.
3. **Constellation 3D Orbitale Déterministe :**
   - Module `HeroBusinessOS3D.tsx` : orbites fluides, rayons ajustés pour ne pas heurter le texte du titre.
   - Fallback CSS automatique sur mobile ou en cas d'absence de WebGL.
4. **Performance & Accessibilité :**
   - Composants interactifs (`AnimatedCounter.tsx`, carrousels `snap-x mandatory`, carrousels de marques) doivent être sans layout shift.
   - Balises `aria-hidden` et textes pour lecteurs d'écran (`sr-only`) sur les éléments animés ou décoratifs.
5. **Couverture de tests & Gardes CI :**
   - Les tests vitrine (`npm --prefix front/web run test:vitrine`, `check:mojibake`, `check:french-copy`, `check:orphans`) doivent impérativement être verts avant tout merge.

---

## 3. Garde Automatisée

Le workflow GitHub Actions `.github/workflows/vitrine-lock-guard.yml` et le script `dev-hub/tools/check-vitrine-agent-lock.sh` bloquent automatiquement toute PR touchant cette zone qui ne provient pas de l'agent vitrine autorisé ou ne dispose pas du label `vitrine-authorized`.
