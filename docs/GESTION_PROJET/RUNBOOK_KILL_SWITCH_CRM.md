# Runbook — Kill switch et feature flags du CRM client

- **Statut :** ratifié (issue #5742, CRM PRE)
- **Périmètre :** module CRM client (`/api/v1/crm/*`), canaux d'intégration
  (WhatsApp, email, SMS)
- **ADR :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md`
  (ADR-CRM-004), `docs/security/WEBHOOK_THREAT_MODEL.md`

---

## 1. Les trois niveaux de coupure (évalués côté serveur)

| Niveau | Mécanisme | Effet | Latence |
|---|---|---|---|
| **Global (tous tenants)** | env `CRM_KILL_SWITCH=true` → `config('crm.kill_switch.enabled')` | Toutes les routes `/api/v1/crm/*` → `403 CRM_KILL_SWITCH_ACTIVE` ; canaux fermés | Redéploiement / rafraîchissement config |
| **Module (global)** | env `CRM_ENABLED=false` | Module coupé partout (hors kill switch) | Redéploiement |
| **Tenant** | `PATCH /api/v1/platform/companies/{company}/features` → `features.crm` | Un tenant précis coupé (`403 CRM_FEATURE_DISABLED`) ; audit `crm.feature.*` | Immédiat |
| **Canal d'intégration** | env `CRM_<CANAL>_ENABLED` (global) + `companies.metadata.crm.integrations.<canal>.enabled` (tenant) | Un canal coupé pour un tenant (ou partout) ; FERMÉ PAR DÉFAUT | Immédiat (tenant), redéploiement (global) |

Le frontend ne peut **jamais** s'auto-autoriser : aucune route tenant n'écrit
ces états. Les écritures passent par le PATCH plateforme super-admin
(`auth:super_admin_api`) ; les lectures publiques passent par
`FeatureFlag::for()` (`/auth/me`) et `CrmFeature::status()`.

## 2. Couper le CRM — procédures

### 2.1 Un seul tenant (incident client)

```bash
# Super-admin → PATCH /api/v1/platform/companies/{company}/features
{ "features": { "rh": true, "crm": false } }
```

Effet immédiat : routes `/api/v1/crm/*` du tenant → `403 CRM_FEATURE_DISABLED`.
Journalisé (`crm.feature.disabled`). Restauration : re-PATCH avec `crm: true`.

### 2.2 Un canal précis (ex. WhatsApp) pour un tenant

1. Global : `CRM_WHATSAPP_ENABLED=false` (si le provider est en incident partout).
2. Tenant : `companies.metadata.crm.integrations.whatsapp.enabled = false`
   (écriture super-admin via outil DB contrôlé — pas encore d'endpoint dédié).

### 2.3 Tous tenants (incident majeur)

```bash
# Render / env de production
CRM_KILL_SWITCH=true
# redéployer (ou forcer le refresh de config)
```

Tout le CRM répond `403 CRM_KILL_SWITCH_ACTIVE` — le code reste déployé, la
gate bloque. Aucune donnée n'est supprimée.

## 3. Restaurer (rollback fonctionnel)

1. Identifier la cause (runbook webhooks si provider, sinon post-mortem).
2. **Tenant** : re-PATCH `crm: true` — vérifier `data.features.crm` dans la
   réponse et la ligne d'audit `crm.feature.enabled`.
3. **Global** : `CRM_KILL_SWITCH=false` (ou `CRM_ENABLED=true`) + redéploiement.
4. **Canal** : réactiver env + métadonnées tenant.
5. Vérification : `GET /api/v1/crm/status` (après activation des routes V0) →
   `enabled: true` ; un appel sur une route CRM réelle → 200.

## 4. Vérifications post-incident

- `FeatureFlag::for($company)` contient `crm` (via `/auth/me`).
- Ligne d'audit `crm.feature.enabled` présente (table `audit_logs`).
- Les canaux reviennent **fermés par défaut** pour tout nouveau tenant
  (aucun héritage d'état).
