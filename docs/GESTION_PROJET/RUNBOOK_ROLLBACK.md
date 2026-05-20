# RUNBOOK - ROLLBACK PRODUCTION

Version 4.1.120 | 2026-05-10

## 1. Declencheurs

| Condition | Decision |
|---|---|
| Incident P1 apres deploy (taux d'erreur > 5% / 5 min) | Rollback immediat, sans attendre |
| `/api/v1/health` renvoie `fail` sur > 2 min consecutives | Rollback immediat |
| Corruption fonctionnelle bloquante (login KO, paie HS, etc.) | Rollback immediat |
| Bug critique mais contournable (feature secondaire) | Hotfix prioritaire, pas de rollback |

## 2. Option A - Rollback code (rapide, sans DB)

A privilegier si **aucune migration** n'a ete appliquee par le deploy en cours.

### Etapes

1. **Identifier le commit a revert**
   ```bash
   git log --oneline -5 main
   ```

2. **Creer le commit de revert**
   ```bash
   git checkout main && git pull
   git revert --no-edit <commit-sha>
   git push origin main
   ```

3. **Declencher le deploy Render**
   Le push sur `main` declenche `deploy-main.yml` automatiquement.
   Verifier la completion :
   ```bash
   gh run list --workflow=deploy-main.yml --limit 1
   ```
   Si besoin de forcer :
   ```bash
   curl --fail --silent --show-error --request POST "$RENDER_DEPLOY_HOOK_URL"
   ```

4. **Attendre le healthcheck vert**
   ```bash
   for i in {1..30}; do
     curl -fs https://gestionemployerbackend.onrender.com/api/v1/health | grep -q '"status":"ok"' && break
     sleep 20
   done
   ```

5. **Checks fonctionnels minimaux** (dans cet ordre)
   - `GET /api/v1/health` -> `{"status":"ok","checks":{"database":{"ok":true}, ...}}`
   - `POST /api/v1/auth/login` avec un compte pilote -> 200 + token
   - `GET /api/v1/auth/me` avec le token -> 200 + payload employe
   - `GET /api/v1/me/monthly-summary?year=YYYY&month=MM` -> 200 + totaux non zero

6. **Ouvrir un ticket** (meme si resolu) + completer `JOURNAL_RACINE.md`.

### Temps cible : < 10 min depuis la detection

## 3. Option B - Rollback code + DB (majeur, irreversible en avant)

A utiliser si le deploy incriminé **a applique des migrations destructrices**
(drop column, type change, data migration) et que l'option A ne suffit pas.

### Etapes

1. **Passer en maintenance mode**
   - Basculer le switch `APP_MAINTENANCE=on` cote Render (env var)
   - Laravel renverra 503 + page `resources/views/errors/503.blade.php`

2. **Restaurer le dump le plus recent**
   Voir `RUNBOOK_BACKUP_RESTORE.md` section 5 (procedure manuelle).
   ```bash
   age --decrypt --identity $BACKUP_AGE_IDENTITY_FILE \
     --output leopardo.dump \
     leopardo-YYYYMMDD-HHMMSS.dump.age

   pg_restore --clean --if-exists --no-owner --no-privileges \
     --dbname="$DATABASE_URL" leopardo.dump
   ```

3. **Rollback du code** (option A etapes 1-3)

4. **Retirer la maintenance**
   - `APP_MAINTENANCE=off`
   - Relancer le worker queues si necessaire

5. **Verifications renforcees**
   - Tous les checks de l'option A
   - Sanity check SQL : `SELECT COUNT(*) FROM shared_tenants.employees` non zero
   - Sanity check SQL : `SELECT MAX(created_at) FROM shared_tenants.attendance_logs` >= avant-incident
   - Tester le pointage d'un employe pilote sur mobile

6. **Ticket incident P1 obligatoire** + post-mortem sous 48h.

### Temps cible : < 45 min

## 4. Regle de migration (prevention)

- Toute migration en production DOIT avoir un `down()` fonctionnel
- Toute migration destructrice (drop column, drop table) DOIT etre precedee d'une PR de depreciation (lecture only, pas de nouveaux usages) mergee > 7 jours avant
- Le CI `Governance Gates` verifie le `CHANGELOG.md` ; toute migration majeure doit etre annoncee dans l'entree version

## 5. Regle operationnelle

- Aucun rollback sans **ticket d'incident** ouvert au prealable
- Toute execution de ce runbook est tracee dans `JOURNAL_RACINE.md`
- Le post-mortem suit le template de `RUNBOOK_INCIDENT_P1.md`

## 6. Rollback Admin Dashboard (Cloudflare Pages)

Cloudflare Pages conserve un historique de deployments. Chaque push sur `main` cree un nouveau deployment.

### Etapes

1. **Aller sur le dashboard Cloudflare Pages** → Projet admin → Deployments
2. **Identifier le deployment precedent** (avant le deploy en cause)
3. **Cliquer "Rollback to this deployment"** → confirmer
4. **Verifier** : ouvrir l'URL admin et tester login + navigation dashboard
5. **Temps cible** : < 5 min (pas de rebuild necessaire)

### Alternative CLI

```bash
# Lister les deployments recents
npx wrangler pages deployments list --project-name=<project>
# Rollback vers un deployment specifique
npx wrangler pages deployments rollback --project-name=<project> --deployment-id=<id>
```

## 7. Rollback Vitrine (Vercel)

Vercel conserve un historique complet de deployments. Chaque push sur `main` cree un nouveau deployment.

### Etapes

1. **Aller sur le dashboard Vercel** → Projet vitrine → Deployments
2. **Identifier le deployment precedent** (status "Ready", avant le deploy en cause)
3. **Menu "..." → "Promote to Production"** → confirmer
4. **Verifier** : ouvrir la vitrine et tester pages critiques (/, /pricing, /demo, /blog)
5. **Temps cible** : < 3 min (promotion instantanee)

### Alternative CLI

```bash
# Lister les deployments recents
npx vercel ls --scope=<org>
# Promouvoir un deployment precedent en production
npx vercel promote <deployment-url> --scope=<org>
```

## 8. Rollback Mobile (Flutter)

L'APK mobile est distribue via Firebase App Distribution ou le store. Le rollback mobile est different car les utilisateurs ont deja telecharge l'app.

### Option A — Hotfix rapide (prefere)

1. **Corriger le bug** dans une branche hotfix
2. **Pousser** → CI `mobile-ci.yml` valide → `mobile-distribute.yml` distribue
3. **Temps cible** : < 30 min pour un fix simple

### Option B — Rollback version store

1. **Google Play Console** → Release management → choisir le track
2. **Halt rollout** sur la version en cause
3. **Re-publier** la version precedente (ou une nouvelle version corrigee)
4. **Apple App Store** → si version pas encore approuvee, annuler la soumission ; sinon publier un correctif

### Option C — Desactiver la feature via feature flag

Si le bug est lie a une feature specifique couverte par le `FeatureRegistry` :
1. **Basculer le flag** via l'API admin : `PUT /api/v1/feature-flags/matrix`
2. L'app mobile reagit au prochain appel API sans mise a jour necessaire
3. **Temps cible** : < 2 min

## 9. Smoke post-deploy

Apres tout rollback ou deploy, executer le smoke post-deploy :

```bash
./dev-hub/tools/smoke-post-deploy.sh https://gestionemployerbackend.onrender.com
```

Verifie : health live, health ready, auth, tenant read, export, OpenAPI docs, platform login.

## 10. References

- Deploy : `RUNBOOK_DEPLOY.md`
- Backup : `RUNBOOK_BACKUP_RESTORE.md`
- Incident P1 : `RUNBOOK_INCIDENT_P1.md`
- Journal : `JOURNAL_RACINE.md`
- Smoke post-deploy : `dev-hub/tools/smoke-post-deploy.sh`
