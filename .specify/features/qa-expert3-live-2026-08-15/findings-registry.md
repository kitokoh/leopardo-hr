# Findings Registry — QA live expert 3 — 2026-08-15

| ID | Surface | Sévérité | Constat | Résolution |
|----|---------|----------|---------|------------|
| E3-01 | API/Admin | P1 | `GET /platform/users` (et `/admin/users`) → 500 (`toIso8601String()` sur string, SuperAdmin sans casts) | Fix livré (casts datetime) — issue #3385 |
| E3-02 | API/Admin | P1 | Preflight CORS `*.pages.dev` → 500 (glob nu dans `allowed_origins_patterns` passé à `preg_match`) — toutes les previews Cloudflare cassées | Fix livré (regex) — issue #3384 |
| E3-03 | API | P2 | `POST /trial/verify` annonce `days: 30` alors que le tenant est provisionné 14 j (`ends_at` +14) | Fix livré (`days` → 14) |
| E3-04 | API | P1 | `company_requests.status` CHECK interdit `processing` → le parcours trial 503 systématique sur base contrainte | Pré-existant main, PR #3227 |
| E3-05 | Vitrine | P2 | /pricing mélange « 14 jours » (Pilot/Operations) et « 30 jours » (Enterprise) ; home mixte | #3012 + PR #3135/#3218 |
| E3-06 | Vitrine | P3 | /blog 404 local — gating `NEXT_PUBLIC_ENABLE_BLOG` ; nav/footer/sitemap masquent correctement le lien | Voulu ; vérifier le flag en prod |
| E3-07 | API | P3 | Seeder backend « Starter/Business/Enterprise » vs frontend « Free/Pilot/Operations » | #2977 |
| E3-08 | Demo | P3 | Backfill readiness démo : payroll=0, kiosks=0, events=0 (signaux non backfillés au premier seed) | Suivi possible (faible) |
| E3-09 | Vitrine | P2 | /checkout?plan=pilot affiche « 30 jours gratuits » alors que les cartes /pricing et le backend sont à 14 j (incohérence #3012 — carte complète des surfaces : pricing 14j/Enterprise 30j, checkout 30j, home mixte, backend 14j, verify days=30/ends_at=+14) | #3012/#3056/#2909 — arbitrage propriétaire 14 vs 30 requis |
