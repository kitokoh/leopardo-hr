# Scripts

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
