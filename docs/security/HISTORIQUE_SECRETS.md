# Historique des secrets exposés — inventaire et plan de purge

> Spec A-2 (#1680) — Alerte automatisée « secrets exposés dans l'historique git ».
> Complément au `docs/security/RUNBOOK_SECRET_ROTATION_PURGE.md` (procédure) et
> au `docs/security/SECURITY_INCIDENT_REDIS_2026-07.md` (chronologie Redis).
>
> Cet inventaire liste les secrets **réels** restés dans l'historique git
> public du dépôt, leur gravité, et le plan de purge. Il sert de référence au
> scan hebdomadaire `.github/workflows/secret-history-scan.yml` (A-2) : un
> résultat du scan qui n'apparaît PAS dans ce tableau est un **nouveau**
> secret exposé → à traiter immédiatement.

## Statut global

| Date de mise à jour | Statut |
|---|---|
| 2026-08-10 | 🔴 Purge historique NON effectuée — action humaine requise (#1472) |

> ⚠️ La seule vraie résolution est la **purge de l'historique git**
> (`git filter-repo --replace-text` ou BFG, puis `push --force --all`), à
> coordonner avec l'équipe et les agents actifs (rebase des branches ouvertes,
> re-clone obligatoire). Voir `docs/security/RUNBOOK_SECRET_ROTATION_PURGE.md`.

## Inventaire des secrets exposés

| Secret | Où dans l'historique | Gravité | Statut | Issue |
|---|---|---|---|---|
| **Mot de passe Redis Upstash** | `docs/audits/AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/*` (+ historique git) | 🔴 Critique (repo public, Redis accessible depuis Internet) | 🔄 Rotation effectuée (2026-08-10, action humaine) — purge git restante | #1472 |
| **URL PostgreSQL Neon** (`postgresql://neondb_owner:<pass>@ep-odd-morning-abt600ow-pooler…`) | Commit `70ca415c` (2026-04-14), `docs/GESTION_PROJET/RAPPORT_DEPLOIEMENT_RENDER.md` | 🔴 Critique (accès DB) | ⬜ Rotation à vérifier + purge git | #1601 |
| **Clés API Google** (`google-services.json` ×4, projet Firebase `leopardo-rh`) | 4 apps Android (historique git) | 🟠 Élevé | 🔄 Clés retirées de l'arbre (stub, 2026-08-09) — rotation console + purge git restantes | #1467 |

## Détection

- **Scan continu (nouveaux commits)** : `.github/workflows/secret-scan.yml` (TruffleHog v3.96.0, sur PR + push main) — ne couvre que les nouveaux commits.
- **Scan hebdomadaire (historique complet)** : `.github/workflows/secret-history-scan.yml` (A-2, job d'information non bloquant, lundi 06:00 UTC) — couvre TOUT l'historique via `--since-commit` racine.
- **GitHub Secret Scanning** : alertes natives sur les patterns connus (2 alertes `google_api_key` résiduelles tant que l'historique n'est pas purgé).
- **TruffleHog manuel** : `trufflehog git https://github.com/kitokoh/leopardo-hr --results=verified,unknown --exclude-detectors=Lob --since-commit=4b825dc642cb6eb9a060e54bf8d69288fbee4904`

## Plan de purge (coordonné — action humaine)

1. **Rotation** (faite pour Redis 2026-08-10 ; à faire/vérifier pour Neon #1601 et Google #1467).
2. **Purge historique** : `git filter-repo --replace-text` (fichier de remplacement des valeurs réelles) ou BFG, puis `push --force --all` — fenêtre de maintenance, coordination avec les agents (voir runbook).
3. **Rebase des branches ouvertes + re-clone** obligatoire après force-push.
4. **Vérification** : le scan hebdomadaire A-2 ne doit plus référencer les valeurs réelles ; TruffleHog manuel idem ; les alertes GitHub Secret Scanning se résorbent.

## Évolution attendue

| Date | Résultat scan A-2 | Commentaire |
|---|---|---|
| 2026-08-10 | Scan initial — secrets connus détectés | Inventaire créé (Spec A-2) |
