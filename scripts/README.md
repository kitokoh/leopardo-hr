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
