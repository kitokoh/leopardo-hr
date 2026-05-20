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

Le dashboard admin est un SPA Vue.js deploye sur Cloudflare Pages.

### Etapes

1. **Identifier le deployment a rollback**
   Aller dans le dashboard Cloudflare Pages > Projet admin > Deployments.
   Chaque deployment est lie a un commit SHA.

2. **Promouvoir un deployment precedent**
   Dans Cloudflare Pages, cliquer sur le deployment precedent vert et choisir "Promote to Production".
   Le rollback est instantane car Cloudflare sert les assets deja construits.

3. **Verification**
   - Ouvrir l'URL admin et verifier que le login fonctionne
   - Verifier que le dashboard principal affiche les KPI
   - Verifier la version dans le footer ou la console (`__APP_VERSION__`)

### Temps cible : < 5 min

## 7. Rollback Vitrine (Vercel)

La vitrine Next.js est deployee sur Vercel.

### Etapes

1. **Via le dashboard Vercel**
   Aller dans Vercel > Projet vitrine > Deployments.
   Cliquer sur le deployment precedent sain et choisir "Promote to Production".

2. **Via CLI** (si acces CLI configure)
   ```bash
   vercel rollback --yes
   ```

3. **Verification**
   - Ouvrir la page d'accueil et verifier le rendu
   - Verifier `/blog`, `/demo`, `/pricing`
   - Verifier que les meta OG et le sitemap sont corrects

### Temps cible : < 5 min

## 8. Rollback Mobile (Firebase App Distribution)

L'APK debug est distribue via Firebase App Distribution.

### Etapes

1. **Supprimer la release defectueuse**
   Aller dans Firebase Console > App Distribution > Releases.
   Supprimer ou desactiver la release problematique.

2. **Redistribuer la version precedente**
   Option A : Depuis Firebase, re-distribuer l'APK de la release precedente.
   Option B : Depuis le CI, relancer le workflow `mobile-ci.yml` sur le commit precedent :
   ```bash
   gh workflow run mobile-ci.yml --ref <commit-sha-sain>
   ```

3. **Pour les stores (Play Store / App Store)**
   Si une version defectueuse a ete publiee sur les stores :
   - Play Store : "Halt rollout" dans la Console Google Play, puis publier un hotfix
   - App Store : contacter Apple pour retirer la version ou publier un patch en review acceleree

4. **Verification**
   - Installer l'APK precedent sur un device de test
   - Verifier le login, le pointage et la consultation des bulletins

### Temps cible : < 15 min (App Distribution), < 24h (stores)

## 9. References

- Deploy : `RUNBOOK_DEPLOY.md`
- Backup : `RUNBOOK_BACKUP_RESTORE.md`
- Incident P1 : `RUNBOOK_INCIDENT_P1.md`
- Journal : `JOURNAL_RACINE.md`
- Smoke post-deploy : `dev-hub/tools/smoke-post-deploy.sh`
