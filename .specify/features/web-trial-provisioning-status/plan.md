# Plan technique — volet vitrine suivi provisioning (#2469)

## Architecture

Tout passe par les proxies same-origin `/api/forms/*` (pattern existant, pas de CORS,
pas d'email/token dans l'URL). Le backend reste la seule source de vérité.

```
SignupForm (client)
  │ submitSignupForm() → POST /api/forms/signup
  │                        └─→ POST /api/v1/trial/signup (backend)
  │  ← data.provisioning_token (+status)          [MODIF: pass-through]
  │
  ├─ sessionStorage['leopardo_trial_provisioning_token'] = token
  │
  └─ Écran suivi: fetchTrialStatus(token) toutes les 5 s (max 12)
        └─→ GET /api/forms/trial-status?token=…   [NOUVEAU proxy]
              └─→ GET /api/v1/trial/status?token=… (backend)
             pending → spinner
             ready   → login_url + bouton + copie
             failed  → message générique + contact
             timeout → repli « email sous peu » + bouton réessayer
```

## Fichiers touchés

1. `front/web/src/app/api/forms/signup/route.ts` — pass-through `provisioning_token`.
2. `front/web/src/app/api/forms/trial-status/route.ts` — NOUVEAU proxy GET (validation token 64 chars, timeout 10 s, relay du statut HTTP backend).
3. `front/web/src/modules/vitrine/lib/forms.ts` — helper `fetchTrialStatus(token)` + type du résultat.
4. `front/web/src/modules/vitrine/components/forms/SignupForm.tsx` — step `provisioning` + lien « Suivre l'état » sur l'écran OTP + persistance sessionStorage + cleanup interval.
5. `front/web/src/modules/vitrine/components/forms/__tests__/SignupForm.test.tsx` — tests polling pending→ready, failed, timeout.
6. `CHANGELOG.md` — entrée.
7. `.specify/features/web-trial-provisioning-status/{spec,plan,tasks}.md` — spec kit.

## Risques / garde-fous

- Ne pas casser le step OTP existant : le suivi est un chemin complémentaire (bouton secondaire + nouvel écran), le flux principal reste intact.
- `AbortSignal.timeout(10000)` sur le proxy ; polling stoppé au `unmount` (pas de setState après unmount).
- Les tests Jest existants mockent `submitSignupForm`/`submitVerifyForm` — ajouter le mock `fetchTrialStatus` au même endroit.
