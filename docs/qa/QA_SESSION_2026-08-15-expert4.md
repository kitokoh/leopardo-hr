# QA Leopardo RH — Session Expert 4 (runtime + merge campaign) du 2026-08-15

Mission (propriétaire) : merger le max de branches vers `main` (main VERT), tester la
plateforme dans tous les sens (vitrine, web, admin, mobiles, workflows, API, logiques,
onboarding, cohérence), consigner chaque manquement selon la méthode Spec Kit (issue +
spec/plan/tasks), puis implémenter les manquements et le max d'issues ouvertes.

**Contexte** : 3 vagues QA parallèles (agent, expert #2 PR #3116, expert v3 PR #3160)
avaient déjà créé ~70 constats et ~163 issues restaient ouvertes. Le swarm d'agents
mergeait en continu sur main pendant la session. Règle anti-doublon (#2400) appliquée sur
chaque constat (branches + PRs + issues vérifiés).

## Merge campaign (bilan)

- **~25 PRs mergées** pendant la session (docs, fixes API/web/admin/mobile) via boucle
  de merge automatique (checks requis verts uniquement) + coordination avec le swarm.
- **4 branches orphelines** → 3 PRs créées (#3124 admin tables/i18n, #3125 mobile routes,
  #3126 web og-images — mergée). La 4e (`fix/qa-omnichannel-web-2026-08-15`) était
  périmée (contenu déjà dans main via #2891) → suppression planifiée (T-E4-006).
- **Doublons fermés avec renvoi** : #2982 (durée 14j + fichiers `.rej` commités),
  #3112/#3115 (accents FR — canonique conservée), #3132 (redondante, main déjà corrigé).
- **Conflits résolus sur 8 branches** (merge main + résolutions manuelles : i18n JSON ×4,
  app.dart Flutter ×4, cockpit Vue ×2) — zéro perte de contenu vérifiée par import-check.
- **File CI désengorgée** : 84 runs orphelins/supersédés annulés (`cancel-orphan-runs.sh`,
  outil officiel #2413) — la saturation (#2488) reste la cause racine des retards.

## Tests effectués (preuves)

- **Builds locaux** : vitrine Next.js ✅ (0 erreur) ; admin Vue ✅ (0 erreur).
- **Checkers repo** : OpenAPI coverage 0 erreur (après #3121) ; migrations 0 collision ;
  manifest routes mobile OK ; catalogue pays OK ; parité .env.example 272 clés OK ;
  controllers/interface orphelins 0 nouveau ; mojibake/href="#" vitrine 0.
- **Black-box staging** : API **v4.23.5** (stale) — /supported-countries 404,
  /i18n/catalog/fr 404, /demo-users 404, **/api-explorer 500**, /docs 200, /health 200.
  Déploys bloqués en file → relance P1 (F-E4-01).
- **Patterns mobiles** : apiClient.dio direct 0, withOpacity 0, DZD fallback conforme
  (#2741 mergée), routes mortes couvertes par #2801/#3129.

## Implémentation (issues fermées par la session)

| Issue | Correctif | PR |
|---|---|---|
| #3055 [P2] API | Garde de rôle + isolation tenant sur GET /employees/{id}/leave-balances (EmployeePolicy::view, 404 cross-tenant, tests) | #3177 |
| #3034 [P1] Admin | CompanyDetailView crash (kiosk/slug/created_at) — backend complété + accès défensif | #3175 |
| #3036 [P2] Admin | DashboardView « Priorités Portefeuille » mappé sur company.*/subscription.mrr | #3175 |
| #3037 [P2] Admin | DashboardView « Inscriptions » mappé sur company_name/email | #3175 |
| #3038 [P2] Admin | UsersView colonne Inscription (alias created_at) | #3175 |
| #3022 [P2] Web | Signup OTP : traductions réelles au lieu des clés i18n brutes | #3178 |
| #3058 [P2] API | Webhook email-bounce : config secret + .env.example (fail-closed conservé) | #3184 |
| #2697, #2699 | Fermées avec preuve code (déjà corrigées sur main — procédure #2512) | — |

## Constats NOUVEAUX (issues à créer / tâches)

- **F-E4-01 [P1]** Déploiement staging stale (v4.23.5) — relance déploy + smoke post-deploy.
- **F-E4-02 [P2]** Canonicals : defaults divergents `site.ts` (leopardo-rh.com) vs
  `site-url.ts` (www.leopardo-rh.com) → source unique.
- **F-E4-03 [P3]** realtime.js id `Date.now()+Math.random()` non persistant.
- **F-E4-04 [P3]** Branche `fix/qa-omnichannel-web-2026-08-15` périmée → supprimer.
- **D-E4-01 [Décision]** Essai vitrine = **14 jours** (décision propriétaire 594c68f2 +
  PRs #2944/#3135) vs texte initial #2909/#2721 (« 30 jours ») → arbitrage propriétaire.

Spec Kit : `.specify/features/qa-expert4-runtime-2026-08-15/{spec,plan,tasks,findings-registry}.md`.

## État final

- main : merges continus pendant la session ; checks requis en cours sur le dernier
  head (file CI) — le rouge latent PHPStan #3130 est corrigé sur main (479b3c43).
- 4 PRs d'implémentation ouvertes (#3175/#3177/#3178/#3184) en attente de checks verts.
- Token propriétaire : à révoquer en fin de session (jamais persisté hors env de session).
