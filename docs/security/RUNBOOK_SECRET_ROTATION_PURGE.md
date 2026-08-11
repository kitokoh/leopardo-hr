# RUNBOOK — Rotation des secrets exposés + purge de l'historique git (F-16 / P0 #1472)

> **Programme FOCUS (F-16)** — déclinaison opérationnelle de l'issue #1472 (P0).
> ✅ **EXÉCUTÉ le 2026-08-11** : purge historique réalisée (git filter-repo --replace-text,
> 11 valeurs réelles → placeholders, force-push main + tag v1.0-staging). Post-mortem :
> `docs/security/POST_MORTEM_PURGE_2026-08-11.md`. Ce runbook reste la procédure de référence
> pour toute purge future.

## Statut 2026-08-09 — Clés Google (issue #1467, partiel)

Les 4 `google-services.json` committés (projet Firebase `leopardo-rh`, une seule
clé API partagée `AIzaSyCYauGS…`) ont été **retirés de l'arbre git** et remplacés
par un **stub à clés factices** versionné (`AIzaSyREPLACE_WITH_REAL_FIREBASE_KEY_0000`).
Les builds CI restaurent le fichier réel depuis le secret Actions
`GOOGLE_SERVICES_JSON` (mobile-apps-ci.yml, mobile-distribute.yml,
mobile-distribute-main.yml).

Restant (action humaine) :
1. **Rotation de la clé API Google** dans la console Firebase (la clé committée
   reste exploitable depuis l'historique git et les alertes Secret Scanning).
2. Purge de l'historique git (cf. section purge plus bas) — les 4 fichiers
   concernés + le secret Redis (#1472).
3. Après purge, résoudre les 2 alertes GitHub Secret Scanning (google_api_key).

Local : `dev-hub/tools/install-mobile-firebase-configs.ps1` (télécharge les
fichiers réels depuis Firebase) puis build — les fichiers réels ne doivent
jamais être commités.


## Périmètre des secrets exposés (au 2026-08-07)

| Secret | Où | Statut |
|---|---|---|
| Mot de passe Redis Upstash | 4 fichiers .md (dont `docs/audits/AUDIT.md`, `docs/PLAN_ACTION2/08_...`) + historique git | 🔴 #1472 ouvert depuis 2026-07-01 |
| Clés API Google (`google-services.json`) | 4 apps Android | 🔴 #1467 (2 alertes secret scanning) |
| **Mot de passe PostgreSQL Neon** (URL `postgresql://neondb_owner:<pass>@ep-odd-morning-abt600ow-pooler…`) | Historique git — commit `70ca415c` (2026-04-14, « Kitokoh patch 6 (#37) »), fichier `docs/GESTION_PROJET/RAPPORT_DEPLOIEMENT_RENDER.md` | 🔴 **#1601** — plus dans HEAD, mais historique public → à inclure dans la purge coordonnée avec #1472 |
| Autres (à inventorier) | scan TruffleHog fetch-depth 0 | À faire |

## Étape 1 — Inventaire complet

```bash
# Scan de TOUT l'historique (pas seulement HEAD)
gh secret-scan --repo kitokoh/leopardo-hr   # ou alertes GitHub
# TruffleHog sur l'historique complet :
trufflehog git https://github.com/kitokoh/leopardo-hr --results=verified,unknown
# Recherche manuelle des patterns connus :
git log -p --all | grep -nE "redis|upstash|api[_-]?key|google-services|AKIA|sk_live" | head -50
```

## Étape 2 — Rotation (avant la purge)

1. **Upstash** : régénérer le mot de passe de la base Redis (console Upstash) ; mettre à jour le secret GitHub `REDIS_PASSWORD` et l'env Render.
2. **Firebase** : régénérer/restreindre les clés API Google (console Google Cloud) ; mettre à jour `google-services.json` dans un canal privé (ne plus committer) ou via secret build.
3. **Neon (PostgreSQL)** : régénérer le mot de passe de la base (console Neon → Settings → Reset password), mettre à jour l'URL de connexion sur Render (service API + workers) — cf. issue #1601.
3. **Vérifier** que l'app tourne avec les nouveaux secrets (staging d'abord).

## Étape 3 — Purge de l'historique git

```bash
# 1. Cloner un miroir bare
git clone --mirror https://github.com/kitokoh/leopardo-hr.git leopardo-mirror.git
cd leopardo-mirror.git

# 2. Réécriture (BFG recommandé — remplace les valeurs par ******)
#    bfg --replace-text ../secrets.txt --no-blob-protection
#    (chaque secret = une ligne dans secrets.txt)

# 3. Nettoyage et vérification
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git log -p --all | grep -c "LE_SECRET"   # → 0 attendu

# 4. Force-push coordonné (toutes les branches + tags)
git push --force --all
git push --force --tags
```

⚠️ **Après force-push** : tous les clones/forks existants contiennent encore le secret →
1. invalider les forks publics ou prévenir leurs propriétaires,
2. documenter que tout clone local doit être re-cloné,
3. surveiller les alertes GitHub Secret Scanning (elles se résolvent après rotation).

## Étape 4 — Vérifications (gates)

- [ ] Scan TruffleHog historique = 0 secret
- [ ] Alertes GitHub Secret Scanning résolues
- [ ] detect-secrets baseline (CI, issue #1503) verte
- [ ] L'app fonctionne en staging puis prod (2 cycles de paie de contrôle)
- [ ] #1472 fermé avec post-mortem dans `docs/security/`

## Étape 5 — Politique durable

1. **Aucun secret dans le repo** : `google-services.json` → généré en CI depuis des secrets (install-mobile-firebase-configs.ps1 existe).
2. `.secrets.baseline` bloquant en CI (#1503) + TruffleHog fetch-depth 0 (déjà en place).
3. Rotation planifiée : secrets de production ≥ 1×/an, immédiate en cas de suspicion.
4. Docs `docs/security/CI_CD_SECRETS.md` tenue à jour (vérification automatisée à ajouter).

## Chronologie recommandée

1. **J1** : inventaire + plan de fenêtre (15 min de downtime acceptable).
2. **J1** : rotation Upstash + Firebase, validation staging.
3. **J2** : purge BFG + force-push + invalidation forks + re-clonage équipe.
4. **J3** : gates de vérification + post-mortem + fermeture #1472.
