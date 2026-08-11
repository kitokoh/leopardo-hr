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
| 2026-08-11 (soir) | 🟡 Purge OK + health prod vérifié (database.ok) ; rotations Neon/Google **à attester côté console** par le propriétaire ; plan de purge des 5 forks documenté (#1723) |
| 2026-08-11 | 🟢 Purge historique EFFECTUÉE (git filter-repo --replace-text + force-push) — voir POST_MORTEM_PURGE_2026-08-11.md |

> ✅ **Purge effectuée le 2026-08-11** : toutes les valeurs réelles de l'inventaire
> ont été retirées de l'historique (11/11 vérifié, gitleaks 44→12 findings — les
> 12 restants sont des exemples documentaires). Re-clone obligatoire pour toute
> copie antérieure. Détails : `docs/security/POST_MORTEM_PURGE_2026-08-11.md`.

## Inventaire des secrets exposés

| Secret | Où dans l'historique | Gravité | Statut | Issue |
|---|---|---|---|---|
| **Mot de passe Redis Upstash** | `docs/audits/AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/*` (+ historique git) | 🔴 Critique (repo public, Redis accessible depuis Internet) | ✅ Rotation + purge git effectuées (2026-08-11) | #1472 |
| **URL PostgreSQL Neon** (`postgresql://neondb_owner:<pass>@ep-odd-morning-abt600ow-pooler…`) | Commit `70ca415c` (2026-04-14), `docs/GESTION_PROJET/RAPPORT_DEPLOIEMENT_RENDER.md` | 🔴 Critique (accès DB) | ✅ Purge git effectuée (2026-08-11) — rotation à attester côté console | #1601 |
| **Clés API Google** (`google-services.json` ×4, projet Firebase `leopardo-rh`) | 4 apps Android (historique git) | 🟠 Élevé | ✅ Stub en arbre + purge git effectuée (2026-08-11) — rotation console à attester | #1467 |

## Détection

- **Scan continu (nouveaux commits)** : `.github/workflows/secret-scan.yml` (TruffleHog v3.96.0, sur PR + push main) — ne couvre que les nouveaux commits.
- **Scan hebdomadaire (historique complet)** : `.github/workflows/secret-history-scan.yml` (A-2, job d'information non bloquant, lundi 06:00 UTC) — couvre TOUT l'historique via `--since-commit` racine.
- **GitHub Secret Scanning** : alertes natives sur les patterns connus (2 alertes `google_api_key` résiduelles tant que l'historique n'est pas purgé).
- **TruffleHog manuel** : `trufflehog git https://github.com/kitokoh/leopardo-hr --results=verified,unknown --exclude-detectors=Lob --since-commit=4b825dc642cb6eb9a060e54bf8d69288fbee4904`

## Plan de purge — ✅ exécuté le 2026-08-11

1. Rotation Redis faite (2026-08-10) ; Neon/Google à attester côté console par le propriétaire.
2. **Purge historique exécutée** : `git filter-repo --replace-text` (11 valeurs réelles → placeholders `REDACTED_*`), puis force-push `main` + tag `v1.0-staging` (aucune branche ouverte).
3. Re-clone obligatoire pour toute copie antérieure (dont `RepoBirdBot`).
4. **Vérification faite** : 0/11 valeurs dans `git log --all -p` ; gitleaks 44→12 (résiduels = exemples doc) ; alerte Secret Scanning à résoudre ; prochain run A-2 à confirmer.

Détails et risques résiduels (forks) : `docs/security/POST_MORTEM_PURGE_2026-08-11.md`.

## Attestations de rotation (issue #1723)

| Secret | Rotation | Attestation console | Preuve / action requise |
|---|---|---|---|
| Redis Upstash (#1472) | ✅ Fait (2026-08-10) | ✅ Attesté | Rotation attestée 2026-08-10 (issue #1472) |
| PostgreSQL Neon (#1601) | 🔄 À attester | ⏳ **Action propriétaire** : reset du mot de passe en console Neon, MAJ `DATABASE_URL` Render (API + workers) | Health prod vérifié le 2026-08-11 18:01 UTC : `GET https://gestionemployerbackend.onrender.com/api/v1/health` → `checks.database.ok=true` (latence 39 ms) — l'URL actuelle fonctionne ; l'attestation de la rotation console reste à tracer ici |
| Clés API Google / Firebase (#1467) | 🔄 À attester | ⏳ **Action propriétaire** : révoquer/restreindre `AIzaSyCYauGS…` et `AIzaSyAkWnXd…` en console Google Cloud/Firebase, MAJ du secret Actions `GOOGLE_SERVICES_JSON` | Historique purgé (0/11 valeurs) ; 2 alertes Secret Scanning résiduelles à résoudre après révocation |
| 5 forks publics | 🍴 Plan documenté | ⏳ **Action propriétaire** : contacts + takedown GitHub Support si sans réponse | `docs/security/PLAN_PURGE_FORKS_2026-08-11.md` (inventaire + messages types) |

> Checklist propriétaire (#1723) : ① console Neon → reset password + MAJ `DATABASE_URL` Render ;
> ② console Google/Firebase → révocation des 2 clés + MAJ `GOOGLE_SERVICES_JSON` ;
> ③ contact des 5 propriétaires de forks (§3 du plan) ; ④ re-vérif health + scan forks ;
> ⑤ clôture de l'issue #1723 avec les attestations datées.

## Évolution attendue

| Date | Résultat scan A-2 | Commentaire |
|---|---|---|
| 2026-08-10 | Scan initial — secrets connus détectés | Inventaire créé (Spec A-2) |
| 2026-08-11 | Purge effectuée — 0/11 valeurs réelles dans l'historique | Post-mortem + force-push (POST_MORTEM_PURGE_2026-08-11.md) |
