# Note de surface 2026-11-10 — ouverture d'espace d'essai (issues #9305/#9306, PR #9310)

**Origine** : test réel de la plateforme en tant que responsable de restaurant
voulant démarrer immédiatement avec ses collaborateurs. Deux blocages enchaînés
rendaient le parcours impossible ; les correctifs sont livrés par la PR #9310.

## Ce qui change côté web admin

**Rien sur les routes `/admin`.** Aucune vue du dashboard super admin n'est
ajoutée, retirée ou modifiée. Les catalogues `front/admin-dashboard/src/i18n/locales/*.json`
sont réécrits par `node shared/i18n/sync/sync-web.js` (procédure standard du
dépôt) parce qu'une clé `signup.activateElsewhere` a été ajoutée aux quatre
locales de `shared/i18n/locales/*.json`. Cette propagation est mécanique et
n'introduit aucun écran nouveau : les clés admin-only sont préservées par le
merge union de `sync-web.js`.

## Ce qui change côté web public (vitrine + espace employeur)

| Zone | Changement |
|---|---|
| `GET /trial/status` (backend) | expose `activation_url` tant que `password_set` est faux |
| `POST /trial/signup` (backend) | en `self_service`, bascule sur le provisioning guidé si l'OTP ne peut pas partir ; la réponse porte `workflow` + `otp_sent` |
| `/api/forms/trial-status` (proxy Next) | relaie `activation_url` (liste blanche explicite) |
| Tunnel `/signup` | l'écran de suivi affiche une issue de secours vers `/auth/set-password` |

## Scénarios à vérifier

1. **Panne du mailer** — `POST /trial/signup` en `self_service` répond 200 avec
   `workflow: guided_trial` et `otp_sent: false` ; aucun 503. L'espace est
   provisionné (`GET /trial/status` atteint `ready`).
2. **Mailer nominal** — quand l'OTP part, la réponse reste
   `pending_verification` avec `workflow: self_service` et `otp_sent: true` :
   aucune régression du parcours vérifié.
3. **Chemin de connexion garanti** — à `ready` avec `password_set: false`,
   `activation_url` est présent et pointe sur `/auth/set-password?token=…`
   (**pas** `/auth/activate/[token]`, réservé aux invitations collaborateur).
4. **Lien retiré après activation** — une fois le mot de passe défini,
   `activation_url` disparaît du payload.
5. **Issue de secours affichée** — l'écran de suivi propose le lien vers la page
   dédiée tant que le mot de passe n'est pas défini ; la page relit le token
   depuis le stockage local si l'état React l'a perdu.
6. **Aucune régression de surface** — les écrans existants du dashboard et de la
   vitrine ne sont pas touchés ; sur `front/web/src/`, le diff ne contient que
   le proxy `trial-status`, le composant `SignupForm` et les catalogues.

## Vérification

- `php -l` sur les fichiers PHP touchés : OK
- `php /tmp/pint.phar --test` (fichiers touchés) : PASS
- `node shared/i18n/validators/validate.js` : `I18N_VALIDATION_OK (4 locales)`
- `npx tsc --noEmit` sur `front/web` : exit 0
- `npx eslint --max-warnings 0` (fichiers touchés) : exit 0
- `npx jest` sur `trial-status` + `SignupForm` : 33 tests passés
- `api/tests/Feature/TrialOnboardingAccessTest.php` : 3 tests couvrant les
  invariants 1, 3 et 4 ci-dessus
