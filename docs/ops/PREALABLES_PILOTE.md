# Prérequis avant onboarding pilote — Checklist ops

> **#R4** — Ce guide liste et explique les 3 prérequis bloquants
> à résoudre **avant** d'envoyer le premier pilote sur l'environnement.
> Sans ces prérequis, le parcours onboarding est irréalisable en autonomie.

---

## B1 — Google OAuth (connexion "Continue with Google")

**Statut au 2026-08-24** : BLOQUÉ — décision produit #5171 en attente.

**Symptôme** : le bouton "Continue with Google" renvoie une erreur 503/500 ou
une page blanche. Le pilote ne peut pas se connecter sans passer par
email+mot de passe.

**Action requise** :

1. Aller dans le tableau de bord [Google Cloud Console](https://console.cloud.google.com/)
   pour le projet Leopardo.
2. Activer l'API **Google Identity Platform** (OAuth 2.0).
3. Créer des identifiants OAuth 2.0 (type : « Application web »).
4. Ajouter les URIs de redirection autorisées :
   - Production : `https://<votre-domaine>/api/v1/auth/google/callback`
   - Staging : `https://<staging-domaine>/api/v1/auth/google/callback`
5. Sur Render, configurer les variables d'environnement suivantes sur le service `leopardo-api` :
   ```
   GOOGLE_CLIENT_ID=<votre-client-id>
   GOOGLE_CLIENT_SECRET=<votre-client-secret>
   GOOGLE_REDIRECT_URL=https://<votre-domaine>/api/v1/auth/google/callback
   ```
6. Redémarrer le service `leopardo-api` sur Render.
7. Vérifier en cliquant sur "Continue with Google" — la redirection vers
   le compte Google doit fonctionner.

**Runbook détaillé** : `docs/GESTION_PROJET/RUNBOOK_GOOGLE_OAUTH_PROD.md`

---

## B2 — Workers de queue Render (emails d'invitation)

**Statut au 2026-08-24** : NON CONFIRMÉ — déploiement workers à vérifier.

**Symptôme** : l'étape `invite_manager` de l'onboarding envoie un email
via la queue Laravel. Sans workers actifs, l'email n'est jamais expédié.
Le pilote attend indéfiniment sans recevoir l'invitation.

**Services requis sur Render** :
- `leopardo-queue-worker` — traite les jobs `default`, `notifications`, `mail`
- `leopardo-scheduler` — exécute les commandes planifiées (incluant `onboarding:send-reminders`)

**Comment vérifier** :
```bash
# Depuis les logs Render du service queue-worker :
# chercher les lignes "[2026-...] Processing job : App\Mail\..."
# ou vérifier le dashboard Laravel Telescope (si activé)
```

**Pour déployer les workers** :
1. Dans le tableau de bord Render, vérifier que `leopardo-queue-worker` est
   en statut **Running** (pas Suspended ou Crashed).
2. Si absent, créer un Background Worker sur Render avec :
   - Start command : `php artisan queue:work --queue=default,notifications,mail --tries=3 --timeout=60`
   - Root directory : `api/`
3. Pour le scheduler, créer un Cron Job Render avec :
   - Schedule : `* * * * *`
   - Command : `php artisan schedule:run`
   - Root directory : `api/`

**Runbook détaillé** : `docs/GESTION_PROJET/RUNBOOK_RENDER_WORKERS.md`

---

## B3 — Signup self-service public (backlog P2)

**Statut** : Backlog — délibérément hors scope des pilotes DZ (phase 1).

Les pilotes DZ sont invités manuellement par l'équipe (invitation envoyée
depuis le super-admin). Le signup self-service public (`POST /platform/signup`)
est en backlog pour la phase 2 du plan 60 jours (issue S1 RETOURS_CLIENTS_PILOTE).

---

## Checklist finale avant session pilote

- [ ] B1 : Google OAuth testé et fonctionnel (connexion Google réussie)
- [ ] B2 : Workers de queue `Running` sur Render (email d'invitation reçu)
- [ ] Compte pilote créé en amont (invitation envoyée, ligne employé existante)
- [ ] Variables d'env Mailgun/SMTP vérifiées (SPF/DKIM actifs)
- [ ] Base de paie DZ vérifiée (pays DZ, barèmes IRG/CNAS actifs)
- [ ] SLA pilotes documenté (`docs/pilotes/SLA_PILOTES.md`)
