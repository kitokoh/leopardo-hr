# Historique des secrets exposés dans le git public

> Inventaire central (Spec A-2, issue #1680) : secrets RÉELS ayant transité dans
> l'historique du dépôt public `kitokoh/leopardo-hr`. Le workflow hebdo
> `secret-history-scan.yml` scanne l'historique complet et référence cette liste
> — un résultat hors liste est une alerte à traiter immédiatement.
>
> ⚠️ **Ne jamais reproduire une valeur réelle dans ce fichier** (convention
> #1614) : uniquement des références (fichiers, commits, hash court).

## Matrice des secrets exposés

| # | Secret | Où (commits/fichiers) | Gravité | Rotation | Purge historique | Tracker |
|---|--------|------------------------|---------|----------|------------------|---------|
| 1 | Mot de passe Redis Upstash (réel) | `docs/audits/AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/*` (commits 2f3a0042 / 0db29d47) | **Critique** | ✅ FAITE par le propriétaire (confirmé 2026-08-09) | ⏳ À exécuter (action humaine) | #1472 |
| 2 | Hostname Upstash (identifiant d'instance) | idem | Élevée | n/a (rotation mot de passe) | ⏳ À exécuter | #1472 |
| 3 | Secret Neon (base de données) | historique git (rédacté dans l'arbre le 2026-08-09) | Élevée | ✅ FAITE (2026-08-09) | ⏳ À exécuter | #1601 |

## Plan de purge (action humaine)

1. **Coordination** : fenêtre de maintenance sans PR ouvertes (la réécriture
   d'historique impose un rebase de toutes les branches + un re-clone pour tous
   les contributeurs).
2. **Exécution** : `git filter-repo --replace-text` (ou BFG) puis
   `push --force --all` — commandes exactes dans
   `docs/security/RUNBOOK_ROTATION_REDIS_1472.md` et
   `docs/security/RUNBOOK_SECRET_ROTATION_PURGE.md`.
3. **Vérification** :
   - l'ancien mot de passe Upstash ne doit plus fonctionner ;
   - `TruffleHog` (scan racine) et GitHub Secret Scanning ne référencent plus
     les valeurs ;
   - le workflow `secret-history-scan.yml` devient vert SANS les secrets connus.

## Garde-fous actifs en attendant

- `secret-scan.yml` — TruffleHog sur les nouveaux commits (PR + push) : **bloquant**.
- `secret-history-scan.yml` — scan hebdo de l'historique complet : **informatif** (A-2).
- Garde d'arbre de travail `#1614` (patterns ressemblants dans les docs) : **bloquant**.
- Registre GitHub Secret Scanning (alerte sur les valeurs déjà connues).

## Journal

- 2026-07-19/21 : valeurs retirées de l'arbre de travail (placeholders) — nettoyage
  documentaire, l'historique reste exposé.
- 2026-08-09 : rotation Redis Upstash confirmée par le propriétaire (#1472) ;
  secret Neon rédacté dans l'arbre (#1601) ; runbook prêt.
- 2026-08-10 : inventaire centralisé + scan hebdo ajouté (A-2, #1680).
