# QA Session — Expert 14 (2026-08-15)

> Audit 360° + consolidation + implémentation. Spec-kit : `.specify/features/3834-cta-e2e-i18n/`, `.specify/features/3810-exception-renderer/`.

## Phase 1 — Audit (constats vérifiés)

| Constat | Preuve | Statut |
|---|---|---|
| OpenAPI CI rouge sur main — clés dupliquées (3 chemins edge + `/admin/training/courses`) | `npx @redocly/cli lint` → *duplicated mapping key (356:3)* | NOUVEAU → #3856 |
| `sha256.txt` documenté `text/plain` mais réponse JSON (verrouillée par test) | spec vs `EdgeDownloadController::sha256()` | #3856 |
| `edge/install.sh` parse le manifeste JSON en texte → hash jamais extrait → install KO en réel | simulation awk sur JSON réel | NOUVEAU → #4007 |
| E2E `client-feature-gates` : attentes sans apostrophe vs catalogues (`n est` vs `n'est`) | Playwright chromium — 2 tests rouges reproduits | #3834 |
| Renderer `HttpExceptionInterface` expose encore `getMessage()` brut (résiduel #3877) | `git show main:api/bootstrap/app.php` | NOUVEAU → #4044 |
| Gardes dev-hub (hygiène, env, migrations, openapi reverse) | scripts locaux | ✅ positifs |
| Widget « Nectios » sur la landing | screenshot | faux positif (extension navigateur) |

## Phase 2 — Consolidation

- **PRs réalignées sur main** : #3828 (routes manifest HR), #3832 (AI Voice), #3839 (marketing auth) — mergées par l'orchestrateur après mes rebases.
- **Branches de PR mises à jour** (merge main + résolution conflits) : #4027 (checksums i18n — conflit fr.json résolu « union clés, branche gagne »), #4006 (middleware api.manager — union des middlewares cameras.php), #4049 (pricing canonique — conflit FAQ pricing/page.tsx résolu en faveur de `getPricingFaq()`), #4043 (Google Sign-In).
- **Leçon dure** : la PR #4000 (Closes #3834) a été mergée avec **uniquement le commit marker** — un force-push concurrent sur la branche a éclipsé l'implémentation entre ma vérification locale et le merge. Contrôle post-merge systématique : `git show main:front/web/e2e/client-feature-gates.spec.ts | grep "n est pas inclus"` → toujours présent → re-livraison via PR #4051.

## Phase 3 — Implémentation

| PR | Sujet | Statut |
|---|---|---|
| #4016 | OpenAPI valide (doublons purgés, content-type sha256.txt) + install.sh JSON (Closes #3856, #4007) | mergée |
| #4051 | CTA/E2E contrat stable + FeatureLockedPanel localisé 4 locales (Closes #3834) | ouverte |
| #4045 | Renderer HttpException — sanitisation getMessage() (Closes #4044) | ouverte |
| #4058 | Specs docs/specifications (#3834, #3856/#4007, #4044) | ouverte |

## Notes pour les prochaines sessions

- **Vérifier le contenu des merges, pas seulement l'état merged** : les force-push concurrents sur une branche partagée peuvent retirer l'implémentation avant le merge (cas #4000). Après merge, `git show <merge>:<fichier>` sur les fichiers clés.
- CI toujours saturée : les checks peuvent rester pending 30 min+ ; ne pas re-pusher en boucle.
- `npx @redocly/cli lint api/openapi.yaml` est un bon smoke de santé spec avant tout PR touchant openapi.yaml.
- Les conflits i18n JSON se résolvent « union clés, branche gagne » (pattern établi).
