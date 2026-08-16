# QA Session — Expert SWE/QA 2026-08-16 (v3) : Merge drain, audit 360° & implémentations

> Session : 2026-08-16 | Agent : expert SWE/QA (SWEQA-2026-08-16-3) | Repo : kitokoh/leopardo-hr
> Mandat : Phase 0 (issues ouvertes + branches) → Phase 1 (audit 360°) → Phase 3 (implémentation des findings).

---

## Phase 0 — Issues ouvertes & merge drain (✅)

68 PRs ouvertes au départ (issues #4308→#4550, docs/qa-* inclus). Résultat : **plus aucune PR héritée ouverte** à la fin de la phase.

### Merges effectués par cet agent (~40 PRs, squash, revue diff par diff)
- **Docs (7)** : #4361, #4423, #4432, #4438, #4465, #4479, #4544.
- **Web/admin/API/mobile/CI** : #4531→#4547 (lot audit expert-swe-qa), #4421, #4429, #4440, #4434, #4358, #4367, #4543, #4536, #4542, #4535, #4539, #4538, #4541, #4533, #4532, #4545, #4546, #4449, #4460, #4462, #4457, #4390, #4364, #4455, #4471, #4540, #4458, #4452 (fermée superseded), #4445 (fermée superseded), #4356 (fermée dupliquée de #4488), #4354 (dupliquée de #4443).

### Décisions notables
- **#4434 amendé** : la branche `pilot` du CTA PricingSection n'était pas du code mort (funnel essai guidé #3329) → branche restaurée avant merge (hors périmètre #4433).
- **Conflits i18n errors.php ×4 + VerifyTrialSignup** résolus par script : clés main canoniques (`VERIFICATION_CODE_INVALID` etc.) + union des clés branche.
- **#4307 (P0) respecté** : verrou pris par SWEQA-2026-08-16-2 (commentaire « Lock & Isolate ») → non touché (repris par eux : #4552/#4554).
- **Branches polluées** par l'automation de merge d'un autre agent (180 fichiers vs main) : #4445/#4452 reconstruites/vérifiées puis fermées (contenu déjà sur main) au lieu de merger le désordre.

### Régression main corrigée (issue #4564, PR #4570)
TS17001 (aria-label dupliqué docs) + refs ChatView manquantes → tsc/eslint verts.

## Phase 1 — Audit 360° (✅)

4 audits parallèles par sous-agents (API Laravel, vitrine Next.js, admin Vue 3, mobile Flutter + CI), chaque finding vérifié dans le code + vérification manuelle des findings critiques.

**22 issues créées (#4606→#4627)** au format spec-kit (Constat/Cause racine/Fix attendu/Critères d'acceptation), labels `QA` + `qa-audit-2026-08-16` + surface/type :

| Surface | P2 | P3 |
|---|---|---|
| API | 2 (partner link throttle/500, kiosk.show throttle) | 2 (forceFill dupliqué — déjà fixé #4558, DTO fallback latent) |
| Web | 2 (/changelog FR, /blog metadata) | 5 (title.template, /demo labels, navbar aria, sandbox labels, Divider/Icon morts) |
| Admin | 1 (retry non-idempotent api.js) | 4 (HolidaysView undefined, UsersView tokens, ExportsView UTC, api.js FR) |
| Mobile/CI | 1 (filtre app= mobile-distribute) | 5 (garde i18n hr, notifications sans catch, smart attendance, tests.yml refs mortes, CI_CD_SECRETS) |

## Phase 3 — Implémentations (✅ 18/21, reste #4611/#4624/#4625)

| PR | Issue | Sujet | Validation |
|---|---|---|---|
| #4628 | #4617 | HolidaysView : `{year}` sans `.value` (dialog undefined) | eslint |
| #4632 | #4618 | UsersView : placeholders :active/:newToday interpolés | eslint |
| #4633 | #4613 | /demo : labels↔champs (a11y) | tsc+eslint |
| #4635 | #4614 | Navbar : aria-haspopup desktop | tsc+eslint |
| #4636 | #4612 | <title> : marque dupliquée retirée (en/tr/ar) | tsc+eslint |
| #4637+#4640+#4644 | #4615 | checkout sandbox : libellés → clés canoniques cardLabel/expiryLabel/cvcLabel ×4 | tsc+eslint |
| #4645 | #4620 | api.js : retry cold-start GET/HEAD/OPTIONS uniquement | eslint |
| #4647+#4651 | #4626 | tests.yml : refs mobiles mortes (2 essais, vérif pré-push) | YAML |
| #4648 | #4627 | CI_CD_SECRETS.md : deploy-web-vitrine.yml inexistant | grep |
| #4649 | #4623 | garde i18n : surface leopardo_hr (paths + watchedPathPrefixes) | YAML |
| #4653+#4657 | #4607 | kiosk.show : bucket dédié kiosk-show 120/min (1er essai partageait kiosk-punch → corrigé) | revue |
| #4654 | #4606 | /p/{code} : throttle 60/min + user_agent/referrer_url tronqués | revue |
| #4659 | #4622 | mobile-distribute-main : SHOULD_BUILD env (filtre app= bloquant) | YAML |
| #4662 | #4619 | ExportsView : dates locales (fin du décalage UTC+1..+3) | eslint |
| #4665+#4686+#4701 | #4621 | api.js : messages d'erreur localisés ×4 (namespace api.*) | eslint |
| #4681 | #4610 | /changelog : 19 releases localisées ×4 (changelogByLocale + getChangelogReleases) | tsc+eslint |

Régression détectée et corrigée en cours de route : doublons d'attributs /demo (TS17001, PR #4678) + import api.js dupliqué (#4686) + helper t() mort après refactor concurrent (#4701).

**Vérification finale main** : `tsc --noEmit` (web) 0, `eslint --max-warnings 0` (web + admin) 0. Issues audit closes : 18/21 (#4611 blog metadata, #4624/#4625 mobile P3 restent à implémenter — pas de validation Dart locale).

## Leçons (à intégrer dans AGENTS.md)

1. **Merge API + famine CI** : `PUT /pulls/{n}/merge` renvoie « merge conflicts » sur un état de mergeabilité périmé — forcer un `GET` du PR avant chaque merge, et merge-forward local (vérifié) avant de pousser.
2. **Famine CI #3545** : les checks requis sont systématiquement cancelled — validation locale (tsc/eslint/YAML/php -l) obligatoire avant merge ; `enforce_admins=false` permet le merge admin, mais la validation locale est la vraie garantie « main verte ».
3. **Vérifier les pipelines de validation** : `npx tsc | tail -1` masque le code de sortie — toujours capturer `$?`.
4. **Concurrence réelle** : plusieurs agents drainent en parallèle ; toujours `fetch` + merge-forward avant push, `force-with-lease` uniquement pour reconstruire une branche, et vérifier `git diff origin/main origin/<branch>` (branche == main = superseded, pas un merge).
