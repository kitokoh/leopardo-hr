# RUNBOOK — Rotation mot de passe Redis Upstash + purge historique git (issue #1472)

> **Statut : ACTION HUMAINE REQUISE** — cette issue ne peut être fermée que par le
> propriétaire, avec preuve de rotation effective. Dernière vérification agent :
> 2026-08-09 (arbre de travail propre, TruffleHog vert sur main).

## Pourquoi

Un mot de passe Redis Upstash réel a été committé en clair dans l'historique git
d'un dépôt **public**. La doc a été nettoyée (placeholders) mais le secret reste
récupérable via `git log -p`. Référence : `docs/security/SECURITY_INCIDENT_REDIS_2026-07.md`.

## Étape 1 — Rotation Upstash (P0, à faire en premier)

1. Aller sur https://console.upstash.com
2. Sélectionner la base Redis concernée (repérer l'instance via Render → API service
   → Environment Variables → `REDIS_URL`, sans réafficher le secret ici).
3. **Settings → Reset Password** (régénère credentials complètes).
4. Sur Render, mettre à jour dans **Environment Variables** :
   - Service **API** : `REDIS_URL`, `REDIS_HOST`, `REDIS_PASSWORD`
   - Service **Background Worker** : idem
5. **Redéployer** API + Worker.
6. Vérifier :
   ```bash
   curl -s https://<votre-api>/api/v1/health | jq '.checks.redis'
   # attendu : { "ok": true }
   ```
   Et confirmer que l'**ancien** mot de passe échoue :
   ```bash
   redis-cli -u rediss://default:<ANCIEN_MDP>@<ancien_host>.upstash.io:6379 ping
   # attendu : WRONGPASS / NOAUTH / timeout
   ```

## Étape 2 — Purge historique git (après rotation, coordination équipe)

> ⚠️ À coordonner : tous les agents/branches ouvertes devront être rebasés, tous
> les clones refaits. `main` est protégé (pas de force-push direct) — prévoir une
> fenêtre de maintenance courte et prévenir les contributeurs.

```bash
# 0. Prérequis : git-filter-repo (pip install git-filter-repo) + un clone frais
git clone --mirror https://github.com/kitokoh/leopardo-hr.git purge.git
cd purge.git

# 1. Recenser les occurrences du secret (NE PAS l'afficher : utiliser un fichier)
#    Le fichier replace-text contient : <ANCIEN_MOT_DE_PASSE>==>REDACTED
cat > /tmp/replace-secret.txt <<'EOF'
<ANCIEN_MOT_DE_PASSE>==>REDACTED
EOF

# 2. Purge : remplace le secret partout dans l'historique + nettoie les refs
git filter-repo --replace-text /tmp/replace-secret.txt --force

# 3. Nettoyage des objets orphelins
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# 4. Re-push (force) de toutes les branches + tags
git push --force --all origin
git push --force --tags origin
```

> Alternative BFG : `bfg --replace-text /tmp/replace-secret.txt` puis
> `git push --force --all`.

Après purge : vérifier que l'ancien secret n'apparaît plus :
```bash
git log -p --all | grep -c "<ANCIEN_MOT_DE_PASSE>"   # attendu : 0
```

## Étape 3 — Vérifications finales

- [ ] Ancien mot de passe → connexion Redis échoue
- [ ] `git log -p --all` ne contient plus le secret
- [ ] TruffleHog Secret Scan vert sur main (workflow `secret-scan.yml`)
- [ ] Branches ouvertes rebasées sur le nouveau main (tous les agents)
- [ ] Tous les membres re-clonent (les anciens clones contiennent le secret)

## Ce que l'agent a déjà fait (2026-08-09)

- Arbre de travail vérifié : uniquement des placeholders (`<REDACTED>`,
  `<NOUVEAU_MDP>`, `<votre_host>`).
- TruffleHog Secret Scan vert sur `main`.
- Ce runbook versionné pour exécution par le propriétaire.

**La rotation Upstash et la purge git nécessitent un accès humain aux consoles
Upstash/Render et une décision de maintenance coordonnée — hors périmètre agent.**
