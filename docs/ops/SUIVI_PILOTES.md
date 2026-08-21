# 👥 SUIVI_PILOTES — Mesure d'usage hebdomadaire des pilotes (issue #5156)

**Version** : 1.0 · **Date** : 2026-08-20 · **Lié** : `docs/pilotes/CARNET_TEMPLATE.md`, `docs/pilotes/KPI_GATE_<date>.md`

« Pilote actif » est **mesuré, pas déclaré** (cible plan : ≥ 2 pilotes
actifs / semaine au gate J60). Sans outil externe : une requête agrégée sur
les données existantes suffit (zéro table supplémentaire).

## Commande

```bash
# Compagnies marquées pilote (metadata.pilot / metadata.is_pilot = true), 7 jours
php artisan pilot:report

# Ciblage explicite par slug, fenêtre 30 j, sortie JSON (pour scripts)
php artisan pilot:report --company=acme-dz --days=30 --json

# Toutes les compagnies (inventaire)
php artisan pilot:report --all
```

### Sortie (markdown, fenêtre par défaut 7 j)

| Compagnie | Pays | Logins | Pointages | Runs paie | Employés actifs | Actifs (fenêtre) | Dernière activité |
|---|---|---|---|---|---|---|---|

### Métriques et sources

| Métrique | Source |
|---|---|
| Logins (fenêtre) | `employees.last_login_at >= début de fenêtre` |
| Pointages créés | `attendance_logs.date >= début de fenêtre` (count) |
| Runs de paie | `payroll_runs.created_at >= début de fenêtre` (count + breakdown par statut) |
| Employés actifs | `employees.status = 'active'` |
| Employés actifs (fenêtre) | `DISTINCT employee_id` sur `attendance_logs` de la fenêtre |
| Dernière activité | `MAX(created_at)` sur attendance_logs ∪ payroll_runs ∪ audit_logs |

## Marquage pilote

Une compagnie est suivie si son `metadata` JSONB contient `pilot: true` ou
`is_pilot: true` (table `public.companies`) :

```sql
UPDATE public.companies
SET metadata = jsonb_set(COALESCE(metadata, '{}'), '{pilot}', 'true')
WHERE slug = 'slug-du-pilote';
```

## Rituel hebdo (vendredi, bilan)

1. `php artisan pilot:report --days=7` (tous les pilotes).
2. Reporter la ligne « Pilotes actifs ≥ 2 » dans le snapshot KPI
   (`dev-hub/tools/kpi-gate.sh`, issue #5158) et le bilan du vendredi.
3. Mettre à jour le carnet de chaque pilote
   (`docs/pilotes/CARNET_TEMPLATE.md`, issue #5152) : usage réel vs promesses,
   top 3 douleurs/manques, bugs bloquants.

## Hors périmètre

- Pas de dashboard web, pas d'outil d'analytics externe.
- Pas de tracking individuel employé (RGPD) : uniquement des compteurs
  agrégés par compagnie.

---
*Issue #5156 (plan 60 jours, Batch 3, Phase 3) — protocole #2400.*
