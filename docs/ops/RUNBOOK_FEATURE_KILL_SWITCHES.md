# Feature Kill Switches — Runbook (MAT-010, #5868)

## Objectif

Stopper un module/feature pour **toute la plateforme** en cas d'incident
(provider défaillant, régression critique, abuse), **sans suppression de
données** : la résolution est simplement fail-closed dans
`Company::hasFeature()` — tous les gates (middleware modules, `FeatureFlag`,
resources) héritent du kill switch via ce point unique.

## Contrat

| Élément | Valeur |
|---|---|
| Table | `public.feature_kill_switches` (une ligne par `feature_key`, `is_active`) |
| Résolution | `App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService::isKilled()` |
| Cache | 60 s (une requête pour toutes les clés) ; invalidé à chaque bascule |
| Fail-closed | kill actif → feature coupée pour TOUS les tenants |
| Audit | `toggled_by` / `toggled_at` / `reason` en base + canal d'audit JSON |

## Utilisation (API super-admin)

```bash
# Lister l'état des interrupteurs
GET  /api/v1/platform/feature-kill-switches

# Stopper une feature (idempotent)
POST /api/v1/platform/feature-kill-switches
{ "feature_key": "leo_ai", "reason": "Incident LLM provider" }

# Réactiver une feature (idempotent)
DELETE /api/v1/platform/feature-kill-switches/leo_ai
```

Toutes ces routes sont réservées au super-admin (`auth:super_admin_api`) et
jamais exposées à l'espace tenant (test 401).

## Effet immédiat

- `Company::hasFeature($key)` → `false` pour toutes les companies ;
- gates de modules (ex. `EnsureCameraModuleMiddleware`, `CameraService`,
  `FeatureFlag::enabled`) → feature considérée inactivée ;
- les **données existantes ne sont pas touchées** : réactivation instantanée
  après correction (cache 60 s maximum).

## Rollback

1. `DELETE /api/v1/platform/feature-kill-switches/<key>` (ou commande/service
   `revive`) ;
2. vérifier `GET /api/v1/platform/feature-kill-switches` → `is_active: false` ;
3. attendre ≤ 60 s (TTL cache) puis confirmer le retour de la feature
   (smoke tenant).

## Garde-fous

- Ne pas tuer `rh` sauf urgence absolue : le module RH est la base de l'app.
- Une bascule n'est jamais silencieuse : toujours renseigner `reason` et
  vérifier l'audit.
