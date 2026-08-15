# Feature Specification: Navbar — aria-current restauré (régression #3785)

**Created**: 2026-08-15

**Status**: Ready for implementation

**Input**: Constat d'audit post-merge — le merge de #3785 (locale-url) a retiré `aria-current="page"` des liens de la Navbar (desktop + dropdown), alors que #3728 l'avait ajouté (a11y) et que le test e2e `navigation-and-links.spec.ts:276` l'exige → CI vitrine rouge.

## Contexte technique

- `front/web/src/modules/vitrine/components/Navbar.tsx` : plus aucune occurrence `aria-current` sur main (grep = 0).
- `front/web/e2e/navigation-and-links.spec.ts` — test « should highlight active pricing page in navbar » : `nav a[href="/pricing"]` doit porter `aria-current="page"`.
- La suppression vient de #3785 : `DropdownMenu` n'utilisait plus `usePathname` (dernier usage = aria-current) et les liens desktop ont perdu l'attribut.

## User Scenarios & Testing

### US1 — Le lien actif de la navbar est annoncé (Priority: P2)
Un utilisateur de lecteur d'écran sait quelle page courante est ouverte.

**Acceptance Scenarios**:
1. **Given** la page `/pricing`, **When** la navbar est rendue, **Then** `nav a[href="/pricing"]` porte `aria-current="page"`.
2. **Given** une autre page (`/demo`), **When** la navbar est rendue, **Then** le lien `/pricing` n'a PAS `aria-current`.
3. **Given** le dropdown (ex. Ressources), **When** un item correspond à la page courante, **Then** il porte `aria-current="page"`.

## Requirements

- FR-1: `Navbar.tsx` — lien desktop actif : `aria-current={pathname === entry.href ? 'page' : undefined}`.
- FR-2: `DropdownMenu` — restaurer `usePathname()` et `aria-current` sur les items du dropdown.
- FR-3: Test e2e existant conservé (aucune modification de `navigation-and-links.spec.ts` nécessaire — il verrouille déjà le contrat).
- FR-4: Entrée `CHANGELOG.md` sous `## [Unreleased]` → `### Fixed` (régression #3785, référence #3728).
