# Leopardo Edge Sync — Architecture Technique

> Version: 1.0.0 | Phase: 2 (Validée) + Phase 3 (Implémentée)

---

## 1. Vue d'ensemble

```
┌────────────────────────────────────────────────────────────────┐
│                       LEOPARDO CLOUD                           │
│   ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│   │  Laravel API │  │  PostgreSQL  │  │  Queue Worker    │   │
│   │  + EdgeSync  │  │  (multi-ten) │  │  (SyncQueue)     │   │
│   └──────┬───────┘  └──────────────┘  └──────────────────┘   │
└──────────┼─────────────────────────────────────────────────────┘
           │ HTTPS (push/pull delta)
┌──────────┼─────────────────────────────────────────────────────┐
│          ↓            LEOPARDO EDGE (chez le client)            │
│   ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│   │  edge-api    │  │  edge-sync   │  │  edge-ui         │   │
│   │  (Laravel)   │  │  (daemon)    │  │  (Next.js local) │   │
│   └──────┬───────┘  └──────┬───────┘  └──────────────────┘   │
│          │                  │                                    │
│   ┌──────┴──────────────────┘───────┐                          │
│   │           SQLite local          │                          │
│   └─────────────────────────────────┘                          │
│          ↑ WiFi LAN                                             │
│   ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│   │  Mobile emp. │  │  Mobile mgr  │  │  Kiosque ZKTeco  │   │
│   │  (Flutter)   │  │  (Flutter)   │  │  (HTML/JS)       │   │
│   └──────────────┘  └──────────────┘  └──────────────────┘   │
└────────────────────────────────────────────────────────────────┘
```

---

## 2. Modes de Fonctionnement

### Mode Cloud (normal)
- L'app mobile communique directement avec `api.leopardo.app`
- Aucun stockage local requis
- Sync en temps réel
- Connexion Internet obligatoire

### Mode Edge (réseau local)
- L'app mobile communique avec `leopardo.local:7878`
- Sync vers Cloud toutes les N minutes (configurable)
- Fonctionne sans Internet pour les opérations RH quotidiennes
- Connexion Internet optionnelle (pour la sync Cloud)

### Mode Offline (hors ligne total)
- L'app mobile stocke tout localement (Drift SQLite)
- Sync différée dès que connexion disponible (Edge ou Cloud)
- Journal de queue local → push dès reconnexion

### Détection automatique (Flutter SyncService)
```
Try Edge (http://leopardo.local)
  → OK → Mode Edge
  → Fail → Try Cloud (https://api.leopardo.app)
    → OK → Mode Cloud
    → Fail → Mode Offline
```

---

## 3. Structure des Composants

### 3.1 Backend Cloud (Laravel)
```
api/app/Modules/EdgeSync/
├── Domain/Models/
│   ├── EdgeNode.php         # Nœud Edge enregistré
│   ├── SyncLog.php          # Audit des syncs
│   ├── SyncQueue.php        # Queue outbound Edge→Cloud
│   └── EdgeLicense.php      # Licence JWT offline
├── Application/Services/
│   ├── SyncEngineService.php    # Moteur de sync
│   ├── EdgeLicenseService.php   # Gestion licences RS256
│   └── CloudDeltaBuilder.php    # Construit le delta pull
├── Interfaces/Api/V1/
│   └── EdgeNodeController.php   # Endpoints Cloud+Edge
├── Jobs/
│   └── ProcessSyncQueueJob.php  # Job async de traitement
├── Providers/
│   └── EdgeSyncServiceProvider.php
└── routes/
    └── api.php
```

### 3.2 Edge Docker Stack
```
edge/
├── docker-compose.yml    # Stack complète (api+ui+sync+proxy)
├── Dockerfile.edge       # Image PHP 8.4 Alpine + SQLite
├── Caddyfile.edge        # Reverse proxy
├── nginx.edge.conf       # Nginx config interne
├── supervisord.edge.conf # Supervisor (php-fpm + nginx)
├── install.sh            # Script d'installation one-liner
└── .env.example          # Template configuration

Services:
  edge-api    :7878  → API Laravel locale (SQLite)
  edge-ui     :7879  → Interface web locale (Next.js)
  edge-sync   :—     → Daemon de synchronisation
  edge-proxy  :80    → http://leopardo.local
```

### 3.3 Flutter Offline (leopardo_core)
```
lib/offline/
├── database/
│   └── edge_database.dart        # Drift SQLite schema
├── services/
│   ├── sync_service.dart         # SyncService (mode detection + sync)
│   └── offline_token_service.dart # JWT offline validation
└── widgets/
    └── sync_status_banner.dart   # Bandeau statut offline/edge/cloud
```

---

## 4. Base de Données

### 4.1 Tables Cloud
```sql
edge_nodes      -- Nœuds Edge enregistrés par tenant
sync_logs       -- Historique des syncs
sync_queue      -- Queue outbound (Edge→Cloud)
edge_licenses   -- Licences JWT signées
```

### 4.2 SQLite Edge Local (même schéma Laravel)
- Subset des tables : `attendance_logs`, `absences`, `employees` (cache), 
  `departments`, `positions`, `schedules`, `absence_types`
- Migrations identiques au Cloud (réduction de colonnes non-edge)

### 4.3 SQLite Flutter (Drift)
```dart
LocalAttendanceLogs  -- Pointages offline
LocalAbsences        -- Demandes d'absence offline  
LocalEmployees       -- Cache employés (read-only)
LocalSyncQueue       -- Queue de sync locale
LocalDepartments     -- Cache départements
```

---

## 5. Synchronisation

### 5.1 Push (Edge → Cloud)
```
Edge SQLite (sync_queue: pending)
    ↓
SyncEngineService::push()
    ↓
Batch par 100 records
    ↓
POST /api/v1/edge-node/{id}/push
    ↓
ProcessSyncQueueJob (async)
    ↓
applyToCloud() avec résolution de conflits
    ↓
sync_queue: status = synced | conflict | failed
```

### 5.2 Pull (Cloud → Edge)
```
SyncEngineService::pull()
    ↓
GET /api/v1/edge-node/{id}/pull
    ↓
CloudDeltaBuilder::build() → delta depuis last_sync_at
    ↓
Edge applique le delta localement
    ↓
edge_nodes.last_sync_at = now()
```

### 5.3 Flutter Sync
```
SyncService::syncNow()
    ↓
LocalSyncQueue: get pending items
    ↓
Batch POST vers Edge/Cloud API
    ↓
markSynced() | markFailed()
    ↓
pullDelta() → applyDelta() → upsertEmployee() etc.
```

---

## 6. Résolution de Conflits

| Scénario | Règle | Justification |
|----------|-------|---------------|
| Pointage créé offline + doublon external_event_id | `local_wins` → skip duplicate | Les pointages offline sont toujours valides |
| Absence modifiée localement mais déjà approuvée Cloud | `cloud_wins` | Le Cloud est authorité sur les approbations |
| Donnée générique : local.updated_at < cloud.updated_at | `cloud_wins` | Last-write-wins par timestamp |
| Donnée générique : local.updated_at > cloud.updated_at | Appliqué | L'Edge a la version plus récente |

---

## 7. Licence Offline (RS256 JWT)

```
Cloud (clé privée)
  → JWT.sign(payload, RS256)
  → EdgeLicense.signed_payload = "eyJ..."

Edge (clé publique embarquée)
  → JWT.verify(signed_payload, publicKey)
  → payload.exp > now() → valid
  → payload.allowed_features → enforce locally

Renouvellement automatique:
  → Si connexion disponible + expires_at - 7 jours < now()
  → GET /api/v1/edge/{id}/license (Cloud re-signe)
```

---

## 8. Sécurité

| Vecteur | Protection |
|---------|-----------|
| Token Edge → Cloud | Bearer token unique par node (révocable) |
| Licence offline | JWT RS256, expiration 30j, validation locale |
| Isolation multi-tenant | `company_id` sur toutes les tables + FK |
| Données locales | SQLite chiffré (SQLCipher option disponible) |
| Transport | HTTPS obligatoire Cloud, HTTP local autorisé |
| Rotation token | Révocable depuis dashboard → `/api/v1/edge/{id}` |

---

## 9. Multi-Tenant Isolation

- Chaque `EdgeNode` appartient à **un seul** `company_id`
- La migration enforce `FK → companies.id CASCADE DELETE`
- Le `CloudDeltaBuilder` filtre toujours par `company_id`
- Le `EdgeNodeController` vérifie `company_id === request.user.company_id`
- L'Edge local ne contient que les données de son tenant

---

## 10. Roadmap Evolution

| Phase | Feature | Priorité |
|-------|---------|---------|
| v1.1 | Multi-sites (plusieurs Edge par entreprise) | Haute |
| v1.2 | Kiosque ZKTeco natif sur Edge | Haute |
| v1.3 | IA locale (reconnaissance faciale offline) | Moyenne |
| v1.4 | SQLCipher pour Edge SQLite | Moyenne |
| v2.0 | Edge mesh (sync entre Edge nodes) | Basse |
| v2.1 | Edge Kubernetes (déploiement enterprise) | Basse |

---

## 11. Installation Client

```bash
# Sur le serveur local du client (Linux, Docker requis)
sudo bash <(curl -fsSL https://api.leopardo.app/edge/install.sh) \
  --node-id <UUID>   \
  --token   <TOKEN>

# Obtenus depuis Dashboard → Paramètres → Edge Nodes → Nouveau Node
```

Après installation :
- `http://leopardo.local` → Interface web locale
- `http://leopardo.local:7878/api` → API locale
- Apps mobiles détectent automatiquement le mode Edge

---

*Document généré automatiquement — Leopardo Edge Sync v1.0.0*
