# ISSUE_3970 — release.yml : « aucune check run trouvée » non bloquant

**Statut**: Fixed (PR `fix/3970-release-zero-checks`) · **Priorité**: P3 · **Module**: CI

## Correctif

`.github/workflows/release.yml` :
- `COUNT == 0` → `::error::` + `FAILED+=` (échec dur — une release ne part
  plus sans attestation CI) ;
- pagination des check-runs (per_page=100 × 10 pages max) ;
- agrégation multi-runs par check (un check rejoué plusieurs fois est
  évalué sur l'union des conclusions).

Vérifié : `yaml.safe_load` OK, `bash -n` sur le bloc run OK.
