# Scripts

## capture_screenshots.py

Playwright script that captures marketing/dashboard screenshots used in `README.md` and `docs/`.
Run from the repo root so relative output paths (`screenshots/<platform>/<name>.png`) resolve correctly:

```bash
python3 scripts/capture_screenshots.py
```

## cleanup-legacy.sh

Script de nettoyage progressif de l'ancienne structure flat.

**PRÉREQUIS** : Tous les tests CI doivent être verts.

```bash
# Voir ce qui serait supprimé (sans rien faire)
./scripts/cleanup-legacy.sh --dry-run --module HR

# Supprimer les fichiers legacy HR (après CI vert)
./scripts/cleanup-legacy.sh --module HR
git commit -m "chore(cleanup): remove legacy HR flat files"
```

**Ordre recommandé** : HR → Payroll → Attendance → Planning → Recruitment → Cabinet → Fleet → Billing

## generate-plan-action2-issues.sh

PA2-AUTO-003 : génère les issues GitHub manquantes à partir du CSV canonique
`docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv`. Dry-run par défaut.

```bash
# Voir ce qui serait créé (aucune écriture)
./scripts/generate-plan-action2-issues.sh --repo kitokoh/leopardo-hr

# Créer réellement les issues manquantes pour un sous-ensemble PA2-I18N-*
./scripts/generate-plan-action2-issues.sh --repo kitokoh/leopardo-hr \
  --label-filter PA2-I18N --apply --owner kitokoh
```

Voir `--help` pour toutes les options (`--milestone`, `--owner`, `--csv`).

## list-stale-branches.sh

PA2-AUTO-009 : liste les branches distantes deja fusionnees dans `main` (safe a supprimer) et les branches non fusionnees inactives depuis N jours (a examiner manuellement). Ne supprime rien par defaut.

```bash
# Lister uniquement (aucune ecriture)
./scripts/list-stale-branches.sh

# Proposer la suppression interactive (confirmation y/N par branche) des branches fusionnees
./scripts/list-stale-branches.sh --delete-merged
```

Voir `--help` pour toutes les options (`--remote`, `--base`, `--stale-days`).
