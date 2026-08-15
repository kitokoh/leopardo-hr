# Tasks — Navbar aria-current restauré

## T1 — Restaurer aria-current sur les liens top-level (Navbar.tsx)
- [x] `aria-current={pathname === entry.href ? 'page' : undefined}` sur les `<Link>` desktop.
- [ ] Vérifier `pathname` disponible dans le scope Navbar (déjà utilisé pour le select).

## T2 — Restaurer aria-current sur les items de dropdown
- [x] `usePathname()` dans `DropdownMenu`.
- [x] `aria-current` sur les `<Link>` d'items.

## T3 — Validation
- [ ] Test e2e `navigation-and-links.spec.ts:276` inchangé (contrat verrouillé).
- [ ] `npm run lint` / `tsc` dans `front/web`.
- [ ] CHANGELOG `### Fixed`.
