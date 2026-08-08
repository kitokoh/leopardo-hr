# RUNBOOK — Rotation des secrets exposés + purge de l'historique git (F-16 / P0 #1472)

> **Programme FOCUS (F-16)** — déclinaison opérationnelle de l'issue #1472 (P0).
> ⚠️ **Ce runbook n'a PAS encore été exécuté.** Toute exécution doit être planifiée
> (fenêtre de maintenance), validée par le propriétaire du repo, et réalisée en
> coordination avec les hébergeurs (Render, Upstash, Firebase).

## Périmètre des secrets exposés (au 2026-08-07)

| Secret | Où | Statut |
|---|---|---|
| Mot de passe Redis Upstash | 4 fichiers .md (dont `docs/audits/AUDIT.md`, `docs/PLAN_ACTION2/08_...`) + historique git | 🔴 #1472 ouvert depuis 2026-07-01 |
| Clés API Google (`google-services.json`) | 4 apps Android | 🔴 #1467 (2 alertes secret scanning) |
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
