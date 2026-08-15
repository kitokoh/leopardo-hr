# Feature Specification: Sitemap — gate /blog sur NEXT_PUBLIC_ENABLE_BLOG

**Feature Branch**: `fix/2276-web-blog-sitemap`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2276

## Contexte
Le blog est gated par `NEXT_PUBLIC_ENABLE_BLOG` (`blog/layout.tsx` → `notFound()` si off → 404 live), mais `sitemap.ts` annonce toujours `/blog` + posts → erreurs de crawl.

## User Stories & Testing

### User Story 1 — Le sitemap reflète l'état réel du blog (P1)
**Acceptance Scenarios**:
1. Given blog désactivé, When génération du sitemap, Then aucune URL `/blog`.
2. Given blog activé, When génération, Then `/blog` + posts présents.

### User Story 2 — Cohérence robots/hreflang (P2)
**Acceptance Scenarios**:
1. Given le sitemap, When vérification robots.txt/alternates, Then pas de référence à /blog si désactivé.
