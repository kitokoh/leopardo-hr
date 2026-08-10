# 🗄️ Inventaire des secrets exposés dans l'historique git

> Créé le 2026-08-10 — Spec A-2 (#1680).
> **Les valeurs ne sont JAMAIS citées ici** (même tronquées — convention #1614) :
> uniquement le type, l'hôte, la gravité et le plan de purge.

## Contexte

Le dépôt `kitokoh/leopardo-hr` est **public**. Des secrets réels ont été
committés en clair ; la documentation a été nettoyée (2026-07-19/21) mais
**l'historique git complet reste récupérable** (`git log -p`) par quiconque
clone le dépôt. Tant que la purge historique (#1472) n'est pas faite, aucun
garde-fou CI ne peut supprimer l'exposition — d'où le **scan hebdo
d'information** (`secret-history-scan.yml`, A-2) qui surveille que rien de
nouveau ne s'ajoute.

## Inventaire

| # | Secret | Où (fichiers d'origine) | Hôte cible (masqué) | Gravité | Statut |
|---|--------|--------------------------|---------------------|---------|--------|
| 1 | Mot de passe Redis Upstash | `docs/audits/AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/*`, `docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md` | `rediss://default:***@noted-tomcat-92597.upstash.io:6379` (8+ occurrences dans l'historique) | **Critique** (P0) | Rotation **non effectuée** — #1472, action humaine |
| 2 | Credentials PostgreSQL Neon | chaînes `postgresql://neondb_owner:***@…` (1 occurrence réelle) | Neon (`neondb_owner`) | Élevée | À confirmer (cf. #1601) |
| 3 | Placeholders génériques (`VOTRE_HOST`, `<votre_host>`, `user:pass@`, `<REDACTED_HOST>`) | docs divers | — | Aucune (exemples) | À conserver tels quels |

> Détection : scan `git log --all -p` masqué (2026-08-10). Le workflow hebdo
> TruffleHog confirme l'absence de **nouveaux** secrets à chaque exécution.

## Surveillance automatisée (A-2)

- `.github/workflows/secret-history-scan.yml` — chaque lundi 06:30 UTC :
  TruffleHog sur **tout l'historique** (depuis la racine), **non bloquant**,
  commente l'issue #1472 si des secrets sont détectés.
- `.github/workflows/secret-scan.yml` (existant) — garde l'arbre de travail
  HEAD propre (convention #1614) + scan des commits nouveaux sur PR/push.
- GitHub Secret Scanning (activation recommandée sur le repo public).

## Plan de purge (coordonné avec #1472 — action humaine)

1. **Rotation d'abord** (l'ordre est critique : purge PUIS rotation laisserait
   une fenêtre où le secret purgé est encore actif) :
   - Upstash Console → Reset Password ; mettre à jour `REDIS_URL`/`REDIS_PASSWORD`
     sur Render (API + Background Worker) ; redeployer ; vérifier
     `/api/v1/health` (`checks.redis.ok == true`). Runbook :
     `docs/security/RUNBOOK_ROTATION_REDIS_1472.md`.
   - Neon : rotation du mot de passe + mise à jour `DATABASE_URL`.
2. **Purge historique** : `git filter-repo --replace-text` (ou BFG) puis
   `push --force --all` — à coordonner avec l'équipe et les agents actifs
   (rebase des branches ouvertes, re-clone obligatoire). Runbook :
   `docs/security/RUNBOOK_SECRET_ROTATION_PURGE.md`.
3. **Vérification** : ancien mot de passe → connexion doit échouer ;
   TruffleHog/Secret Scanning ne référence plus aucun secret de cet inventaire ;
   le scan hebdo A-2 passe au vert (historique propre).
4. **Clôture** : l'issue #1472 ne peut être fermée que sur preuve de rotation
   effective (elle sert de tracker — voir son corps).
