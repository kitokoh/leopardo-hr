# Leopardo Edge Sync — Phase 1 : Rapport Architecture
> **Statut :** En attente de validation CTO  
> **Auteur :** Architecte IA — sur base du repo `kitokoh/leopardo-hr`  
> **Date :** 2026-06-29  
> **Aucun code généré à ce stade.**

---

## Table des matières

1. [Modèle de fonctionnement (3 modes)](#1-modèle-de-fonctionnement)
2. [Architecture Leopardo Edge](#2-architecture-leopardo-edge)
3. [Données locales vs Cloud](#3-données-locales-vs-cloud)
4. [Synchronisation — Sync Engine](#4-synchronisation--sync-engine)
5. [Application mobile offline (Flutter)](#5-application-mobile-offline-flutter)
6. [Application web client offline](#6-application-web-client-offline)
7. [Licence et sécurité](#7-licence-et-sécurité)
8. [Multi-tenant](#8-multi-tenant)
9. [Évolutivité](#9-évolutivité)
10. [Décisions techniques — tableau récapitulatif](#10-décisions-techniques)
11. [Risques identifiés](#11-risques-identifiés)
12. [Schéma relationnel — nouvelles tables](#12-schéma-relationnel--nouvelles-tables)
13. [Flux de données](#13-flux-de-données)
14. [Checklist de validation CTO](#14-checklist-de-validation-cto)

---

## 1. Modèle de fonctionnement

### 1.1 Mode Cloud (existant — ne pas toucher)

```
Mobile/Web → HTTPS → Leopardo Cloud API (Laravel)
                          ↓
                     PostgreSQL Cloud
```

**Fonctionnement actuel :**
- Toutes les requêtes transitent par `https://gestionemployerbackend.onrender.com`
- Authentication Bearer JWT via `Core/Auth`
- Multi-tenant via `BelongsToCompany` trait (déjà présent sur tous les modèles)
- `AttendanceLog.synced_from_offline` indique déjà qu'un flux offline était anticipé

**Limites :**
- Dépendance totale à Internet
- Latence réseau même pour le pointage local
- Inutilisable en zone blanche ou coupure FAI

---

### 1.2 Mode Offline Local (nouveau)

```
Mobile/Web → Réseau LAN → Leopardo Edge (local)
                               ↓
                          SQLite/PostgreSQL local
```

**Principe :**
- Un service **Leopardo Edge** tourne chez le client (Docker ou binaire)
- Il expose la même API REST que le Cloud (`/api/v1/...`) sur le réseau local
- Les appareils mobiles détectent automatiquement l'Edge et basculent dessus
- Toutes les opérations RH courantes fonctionnent sans Internet
- Les données sont stockées localement en attente de sync

**Avantages :**
- Continuité de service totale
- Latence quasi nulle (réseau local)
- Sécurité : données sensibles restent sur site

**Limites :**
- Paie finale, abonnements, rapports consolidés nécessitent le Cloud
- Nécessite un matériel de déploiement chez le client (mini-PC, NAS, serveur)

---

### 1.3 Mode Hybride (synchronisation automatique)

```
Offline accumulé → Réseau disponible → Sync Engine → Cloud
                                            ↓
                                     Résolution conflits
```

**Principe :**
- Dès que le réseau est détecté, la sync s'enclenche automatiquement
- Les opérations hors-ligne sont rejouées dans l'ordre chronologique
- Les conflits sont résolus par règle définie (§4)
- L'UI mobile affiche le statut de sync en temps réel

---

### 1.4 Tableau comparatif

| Critère | Mode Cloud | Mode Offline | Mode Hybride |
|---------|-----------|--------------|--------------|
| Internet requis | ✅ Oui | ❌ Non | Optionnel |
| Source de vérité | Cloud | Edge | Cloud (après sync) |
| Pointage | ✅ | ✅ | ✅ |
| Paie finale | ✅ | ⚠️ Partielle | ✅ après sync |
| Rapports temps réel | ✅ | ❌ Local | ✅ après sync |
| Abonnement vérifié | ✅ Temps réel | ✅ Token local | ✅ |
| Latence | Réseau | ~0 | Réseau |

---

## 2. Architecture Leopardo Edge

### 2.1 Analyse des options

#### Option A — Service Docker local
```
docker-compose up leopardo-edge
  → Laravel slim (sans AI, sans billing)
  → PostgreSQL local ou SQLite
  → Redis léger
  → Reverse proxy Caddy (déjà utilisé dans api/Caddyfile)
```
**Pour :** Infrastructure homogène avec le Cloud, mises à jour automatiques via Docker Hub, images testées CI/CD.  
**Contre :** Docker doit être installé chez le client, consomme ~512 MB RAM.

#### Option B — Application desktop (Electron/Tauri)
```
App native → serveur embarqué → SQLite
```
**Pour :** Zéro dépendance Docker, installable comme une app.  
**Contre :** Portabilité limitée (Windows/Mac/Linux séparés), difficile à mettre à jour, pas cohérent avec l'architecture Laravel.

#### Option C — Binaire Go auto-contenu
```
leopardo-edge (binaire) → API REST → SQLite embarqué
```
**Pour :** Go est déjà listé dans TOOLS.md comme disponible, très léger (~30 MB), cross-platform, minimaliste.  
**Contre :** Dupliquer la logique métier Laravel, risque de dérive.

---

### 2.2 Recommandation : **Option A — Docker Compose**

**Justification :**
1. Le repo contient déjà `api/Dockerfile.prod` et `api/Caddyfile` → réutilisables
2. Le module `Modules/Attendance/Application/Actions/SyncZKTeco.php` montre que la codebase supporte déjà les patterns de sync
3. Docker Compose permet de shipper un `leopardo-edge.yml` versionné dans le repo
4. Mises à jour Edge via `docker pull` → même pipeline CI/CD que le Cloud
5. Le tenant IT admin peut gérer ça facilement

**Profil matériel minimal recommandé :** Raspberry Pi 5 (8GB), mini-PC Intel NUC, ou VM dédiée.

---

### 2.3 Structure du service Edge

```
leopardo-edge/
├── docker-compose.yml          # Composition : api + db + caddy
├── .env.edge                   # Config tenant (TENANT_ID, EDGE_SECRET, CLOUD_URL)
├── api/                        # Laravel monorepo existant (subset)
│   └── config/edge.php         # Feature flags Edge (désactive AI, billing, etc.)
├── caddy/Caddyfile.edge        # Reverse proxy local → http://leopardo.local
├── storage/                    # Données persistantes montées en volume
└── scripts/
    ├── install.sh              # Script d'installation one-shot
    ├── sync.sh                 # Déclenchement manuel sync
    └── health.sh               # Healthcheck local
```

**Feature flags Edge** (dans `config/edge.php`) — modules désactivés en local :
- `AI/` → désactivé (trop lourd, nécessite LLM externe)
- `Modules/Billing/` → désactivé (géré Cloud uniquement)
- `Modules/Recruitment/` → optionnel
- `Modules/Cabinet/` → optionnel (selon capacité stockage)

**Modules actifs en mode Edge :**
- `Core/Auth` ✅
- `Modules/Attendance` ✅ (cœur de l'offline)
- `Modules/HR` ✅ (lecture employés)
- `Modules/Planning` ✅
- `Modules/Absence` ✅
- `Modules/Payroll` ⚠️ Calcul local seulement, pas de virements

---

### 2.4 Communication Mobile → Edge

```
Mobile Flutter
  1. Réseau détecté → DNS mDNS : leopardo.local / IP Edge
  2. Ping Edge /api/v1/edge/health → répond 200
  3. Bascule automatique vers Edge URL
  4. JWT Cloud valide → présenté à Edge (Edge vérifie signature localement)
  5. Toutes les requêtes → Edge local
```

**Mécanisme de découverte réseau :**
- mDNS / Bonjour : `leopardo.local` (Avahi sous Linux)
- Fallback : IP statique configurable dans l'app mobile
- Le mobile maintient deux BaseUrls : `cloudBaseUrl` + `edgeBaseUrl`

---

### 2.5 Communication Edge → Cloud (Sync)

```
Edge → POST https://cloud/api/v1/sync/push  (opérations locales)
Edge ← GET  https://cloud/api/v1/sync/pull  (nouveautés Cloud)
     → Mutual auth : EDGE_SECRET + tenant JWT signé
     → TLS obligatoire
     → Compression gzip sur le payload
```

---

## 3. Données locales vs Cloud

### 3.1 Données **locales sur Edge** (source de vérité = Edge pendant l'offline)

| Entité | Modèle Laravel existant | Sync vers Cloud |
|--------|------------------------|-----------------|
| Pointages | `AttendanceLog` | ✅ Oui |
| Demandes de correction | `AttendanceCorrectionRequest` | ✅ Oui |
| Événements calendrier | `CalendarEvent` | ✅ Oui |
| Absences déclarées | `Absence` | ✅ Oui |
| Logs kiosque | `AttendanceKiosk`, `KioskAnnouncement` | ✅ Oui |
| Biométrique ZKTeco | `ZktecoSyncLog` | ✅ Oui |
| Tâches/Commentaires | `Task`, `TaskComment` | ✅ Oui |
| Notifications locales | `Notification` | ✅ Oui |

### 3.2 Données **Cloud uniquement** (jamais téléchargées en entier sur Edge)

| Entité | Raison |
|--------|--------|
| `Subscription` | Sécurité licence — Cloud est seul juge |
| `Invoice`, `Payment` | Données financières sensibles |
| `PartnerReferral`, `PartnerClick` | Marketing, inutile offline |
| `AuditLog` global | Consolidé Cloud uniquement |
| `AIConversation`, `AIAuditLog` | AI tourne Cloud |
| `BankExport` | Virements bancaires = Cloud obligatoire |

### 3.3 Données **répliquées sur Edge** (lecture seule, seed au déploiement)

| Entité | Modèle | Fréquence de réplication |
|--------|--------|--------------------------|
| Employés | `Employee` | À chaque sync |
| Départements | `Department` | À chaque sync |
| Postes | `Position` | À chaque sync |
| Horaires | `Schedule` | À chaque sync |
| Politiques congés | `LeavePolicy` | À chaque sync |
| Composantes salaire | `SalaryComponent`, `SalaryStructure` | À chaque sync |
| Utilisateurs/Rôles | `User` (sans données sensibles) | À chaque sync |
| Paramètres entreprise | `CompanySetting` | À chaque sync |

### 3.4 Source de vérité

```
Règle fondamentale :
  → Le Cloud est TOUJOURS la source de vérité finale.
  → L'Edge est source de vérité TEMPORAIRE pendant l'offline.
  → Après sync réussie, l'Edge marque les enregistrements comme "synced".
```

---

## 4. Synchronisation — Sync Engine

### 4.1 Concept clé : Sync Log

Toute opération locale est tracée dans une table `sync_queue` :

```sql
sync_queue (
  id           UUID PRIMARY KEY,
  tenant_id    UUID NOT NULL,
  entity_type  VARCHAR(64),     -- 'attendance_log', 'absence', etc.
  entity_id    UUID,
  operation    ENUM('create','update','delete'),
  payload      JSONB,           -- snapshot de l'entité au moment de l'op
  local_ts     TIMESTAMP,       -- horodatage local (Edge)
  synced_at    TIMESTAMP NULL,  -- NULL = en attente
  conflict     BOOLEAN DEFAULT false,
  conflict_resolution VARCHAR(32) NULL
)
```

Chaque modèle local implémente un trait `HasSyncLog` :
```php
// Déclenché sur created/updated/deleted
// Insère dans sync_queue automatiquement
```

### 4.2 Stratégie Push (Edge → Cloud)

```
1. Edge collecte sync_queue WHERE synced_at IS NULL ORDER BY local_ts ASC
2. Batch de 100 enregistrements max (configurable)
3. POST /api/v1/sync/push avec batch JSON + HMAC signature
4. Cloud traite, répond : { accepted: [], conflicts: [], errors: [] }
5. Edge marque synced_at = NOW() pour les accepted
6. Edge traite les conflicts selon règle §4.5
```

**Fréquence push :** Toutes les 30 secondes si réseau disponible.

### 4.3 Stratégie Pull (Cloud → Edge)

```
1. Edge envoie son dernier pull_cursor (timestamp dernier pull)
2. GET /api/v1/sync/pull?since={cursor}&tenant={id}
3. Cloud retourne les enregistrements modifiés depuis cursor
4. Edge applique les changements (upsert)
5. Edge met à jour pull_cursor
```

**Fréquence pull :** Toutes les 60 secondes.

### 4.4 Synchronisation automatique

```
SyncScheduler (processus en arrière-plan Edge) :
  → [30s] Tente push si sync_queue non vide
  → [60s] Tente pull depuis Cloud
  → [5min] Healthcheck Cloud (met à jour edge_status)
  → [1h] Validation licence locale (voir §7)
  → [24h] Full reconciliation (comptage global, détection anomalies)
```

**Gestion absence réseau :**
- Edge opère normalement en local
- sync_queue accumule les opérations
- Dès reconnexion → flush automatique de la queue
- Taille max sync_queue : configurable (défaut 10 000 ops / 30 jours)

### 4.5 Résolution de conflits

**Scénario :** Employé modifie pointage localement (Edge), Manager modifie le même pointage dans Cloud.

**Règle de résolution : Last-Write-Wins avec priorité hiérarchique**

```
Priorité (décroissante) :
  1. Action manager/admin dans Cloud  →  gagne toujours
  2. Action superviseur local Edge    →  gagne sur employé
  3. Action employé local             →  perd face à Cloud

Exception :
  → Si la donnée locale n'existe pas encore dans Cloud (create offline) → toujours acceptée
  → Si conflit de présence critique (check-in/check-out) → log de conflit + notification au RH
```

**Table de décision conflits :**

| Auteur Edge | Auteur Cloud | Résolution |
|-------------|--------------|------------|
| Employé | Manager | Cloud gagne, Edge notifié |
| Manager | Manager | Cloud gagne (timestamp plus récent) |
| Admin | Employé Cloud | Edge gagne (admin prioritaire) |
| Système (kiosque) | Manager | Cloud gagne |

**Audit :** Tout conflit résolu est logué dans `sync_conflict_log` avec les deux versions.

---

## 5. Application mobile offline (Flutter)

### 5.1 Architecture locale Flutter

```
leopardo_employee/
├── data/
│   ├── local/
│   │   ├── database/          # Drift (SQLite typé)
│   │   │   ├── app_database.dart
│   │   │   ├── tables/        # attendance, employees, schedules...
│   │   │   └── daos/          # Data Access Objects
│   │   └── preferences/       # flutter_secure_storage (tokens)
│   ├── remote/
│   │   └── api_client.dart    # Dio avec intercepteur EdgeRouter
│   └── repositories/          # Pattern repository : local first
├── services/
│   ├── connectivity_service.dart   # Détection réseau
│   ├── edge_discovery_service.dart # mDNS + fallback IP
│   └── mobile_sync_service.dart    # Push local → Edge
└── providers/                 # Riverpod state management
```

### 5.2 Technologie recommandée : **Drift (SQLite)**

| Option | Typage | Migrations | Réactivité | Verdict |
|--------|--------|------------|------------|---------|
| SQLite brut | ❌ | Manuel | ❌ | Non |
| Hive | ❌ | Limitée | ✅ | Non (pas SQL) |
| Isar | ✅ | Auto | ✅ | Bon mais non-SQL |
| **Drift** | ✅ | ✅ | ✅ Stream | **✅ Recommandé** |

**Justification Drift :** Typage Dart natif, migrations versionnées, Streams réactifs, idéal pour sync différée.

### 5.3 Stratégie offline mobile

```
Repository pattern :
  1. Lecture → Local DB FIRST, refresh depuis Edge/Cloud en arrière-plan
  2. Écriture → Local DB IMMÉDIATEMENT + enqueue sync operation
  3. Sync → Mobile envoie vers Edge local (ou direct Cloud si Edge absent)
```

### 5.4 Sécurité token offline

```
Token JWT Cloud (validité 8h) :
  → Stocké dans flutter_secure_storage (Keychain iOS / Keystore Android)
  → Présenté à Edge local pour validation de signature (clé publique embarquée)
  → Refresh automatique dès réseau disponible

Token Edge local :
  → Durée de vie : 24h max (configurable par le tenant)
  → Révocable : la clé publique Edge peut être rotée lors du prochain pull
  → L'Edge vérifie la signature JWT Cloud sans appeler le Cloud (JWK stocké localement)
```

### 5.5 Permissions offline

```
Permissions RH stockées dans Drift (table cached_permissions) :
  → Synchronisées à chaque login ou pull
  → Le mobile ne fait aucune route sans permission vérifiée localement
  → TTL : 12h (recachées à la reconnexion)
```

---

## 6. Application web client offline

### 6.1 Option 1 — Web via Leopardo Edge local

```
Navigateur → http://leopardo.local → Caddy Edge → Laravel Edge API
```

- URL locale servie par le service Edge
- mDNS (`leopardo.local`) configuré par Avahi sur l'Edge
- Il s'agit du **vrai frontend Next.js** servi localement
- Aucun cache navigateur complexe requis
- Idéal pour les managers sur site qui travaillent depuis un PC fixe

**Avantages :** UI 100% fonctionnelle, pas de PWA à maintenir.  
**Inconvénients :** Nécessite Edge installé, ne fonctionne pas hors réseau local.

### 6.2 Option 2 — PWA Offline

```
Next.js + next-pwa (Workbox) :
  → Service Worker intercepte les requêtes
  → IndexedDB pour cache des données
  → Sync en arrière-plan (Background Sync API)
```

**Avantages :** Fonctionne depuis n'importe où, même sur mobile web.  
**Inconvénients :** Complexité PWA + IndexedDB + conflits de cache, limites iOS Safari.

### 6.3 Recommandation : **Option 1 prioritaire + Option 2 secondaire**

```
Déploiement :
  Phase 1 → Web via Edge local (http://leopardo.local) — rapide, fiable
  Phase 2 → PWA offline pour les managers nomades — évolution future
```

**Justification :** L'Edge Docker expose déjà un serveur web Caddy. Servir le build Next.js statique (`next export`) depuis l'Edge est trivial et réutilise l'infrastructure en place.

---

## 7. Licence et sécurité

### 7.1 Licence locale signée

```
EdgeLicense (fichier JSON signé RSA-256) :
{
  "tenant_id": "uuid",
  "edge_id": "uuid",
  "issued_at": "ISO8601",
  "expires_at": "ISO8601",        // max 30 jours
  "features": ["attendance", "hr", "planning"],
  "max_employees": 500,
  "signature": "BASE64_RSA_SIGNATURE"
}
```

- Signé par la clé privée Cloud (jamais accessible depuis l'Edge)
- La clé publique est embarquée dans l'image Docker Edge (non modifiable sans rebuild)
- Vérification à chaque démarrage Edge + toutes les heures

### 7.2 Cycle de vie de la licence

```
Déploiement initial :
  1. Admin Cloud génère EdgeLicense pour le tenant
  2. License téléchargée dans /storage/license.json lors du premier sync
  3. Edge refuse de démarrer sans license valide

Renouvellement :
  1. Edge renouvelle automatiquement lors de chaque sync Cloud
  2. Grace period : 7 jours après expiration (pour coupure prolongée)
  3. Après grace period → Edge passe en mode lecture seule

Révocation :
  1. Cloud marque license comme révoquée
  2. Au prochain pull → Edge reçoit la révocation
  3. Edge arrête les écritures immédiatement
```

### 7.3 Protection contre falsification

```
Protections :
  - Signature RSA-2048 sur la license (impossible de forger sans clé privée)
  - Hash de l'image Docker (intégrité binary)
  - Variables d'env chiffrées (EDGE_SECRET ne jamais logger)
  - Clé publique hardcodée dans le binary (pas dans .env modifiable)
  - Rate limiting sur les endpoints de sync
  - Audit log de toutes les opérations Edge
```

### 7.4 Sécurité réseau local

```
Caddy Edge :
  → HTTP sur réseau local (acceptable en LAN privé)
  → HTTPS self-signed disponible en option
  → Authentification Bearer JWT sur tous les endpoints
  → IP whitelist configurable (réseau local uniquement)
```

---

## 8. Multi-tenant

### 8.1 Isolation garantie

```
Principe : 1 Edge = 1 tenant unique

Garanties :
  → TENANT_ID hardcodé dans .env.edge à l'installation
  → Toutes les tables locales incluent tenant_id (hérité de BelongsToCompany)
  → L'Edge rejette toute requête avec un JWT appartenant à un autre tenant
  → Aucun partage de base de données entre tenants
  → Synchronisation Cloud filtrée par tenant_id + HMAC secret unique par tenant
```

### 8.2 Configuration initiale par tenant

```
Provisioning (côté Cloud) :
  1. Admin plateforme active "Edge" pour un tenant
  2. Cloud génère : EDGE_SECRET (unique), EdgeLicense (signée), JWK public
  3. Package d'installation généré : leopardo-edge-{tenant_id}.zip
     contenant docker-compose.yml + .env.edge pré-rempli + license.json
  4. Tenant IT installe avec : ./scripts/install.sh
```

### 8.3 Prévention des mélanges

```
Couches de protection :
  Layer 1 : Base de données locale = uniquement les données du tenant
  Layer 2 : JWT contient tenant_id vérifié à chaque requête
  Layer 3 : HMAC sur les payloads de sync (secret unique par tenant)
  Layer 4 : Audit log par tenant sur le Cloud
```

---

## 9. Évolutivité

### 9.1 Plusieurs sites par entreprise

```
Architecture multi-site :
  Tenant
  ├── Site Paris → Edge-Paris
  ├── Site Lyon  → Edge-Lyon
  └── Site Bordeaux → Edge-Bordeaux

Chaque Edge :
  → Même tenant_id
  → site_id différent (nouvelle colonne sur les modèles de présence)
  → Sync indépendante vers Cloud
  → Pas de communication directe entre sites (tout passe par Cloud)
```

**Modification requise :** Ajouter `site_id` sur `AttendanceLog`, `AttendanceKiosk`.

### 9.2 Plusieurs kiosques par site

```
Edge-Site
  ├── Kiosque entrée (IP LAN)
  ├── Kiosque cantine
  └── Kiosque parking

Chaque kiosque :
  → Envoie vers Edge local (HTTP LAN, pas besoin Internet)
  → Edge fait le merge et sync vers Cloud
```

Ce schéma est déjà préparé : `AttendanceKiosk` et `ZktecoDevice` existent.

### 9.3 Synchronisation massive

```
Pour 1000+ employés / site :
  → Chunked sync : batches de 100 ops
  → Priorité sur les données temps réel (présence) vs données froides (paie)
  → Compression gzip sur les payloads sync
  → Backpressure : Edge attend ACK Cloud avant de vider la queue
```

### 9.4 IA locale (évolution)

```
Phase future :
  → Modèle ONNX léger embarqué dans Edge
  → Détection d'anomalie de présence en local (sans Cloud)
  → Le module AI/ existant peut évoluer vers un mode "local inference"
  → Compatible avec le module Predictions/ déjà présent
```

### 9.5 Réseau complexe (VPN, sous-réseaux)

```
Résolution :
  → Edge écoute sur 0.0.0.0:80 (configurable)
  → Wildcard DNS interne possible (ex: leopardo.internal)
  → VPN site-à-site : Edge accessible depuis autre site via VPN
  → Support proxy sortant HTTP configurable pour sync Cloud
```

---

## 10. Décisions techniques

| N° | Décision | Choix | Alternative rejetée | Raison |
|----|----------|-------|---------------------|--------|
| D1 | Runtime Edge | Docker Compose | Binaire Go / Desktop | Cohérence avec Dockerfile.prod existant |
| D2 | DB locale Edge | PostgreSQL (même image) | SQLite | Parité avec Cloud, pas de migration de schéma spécifique |
| D3 | DB locale mobile | Drift (SQLite) | Hive / Isar | Typage Dart, migrations versionnées, Stream réactif |
| D4 | Découverte mobile | mDNS (leopardo.local) | IP statique forcée | UX zéro-config |
| D5 | Résolution conflits | Cloud gagne + LWW hiérarchique | CRDT | Simplicité, cohérence avec rôles existants |
| D6 | Licence offline | JWT + RSA signé | Révocation simple | Sécurité sans connexion requise |
| D7 | Web offline | Edge local (http://leopardo.local) | PWA seule | Réutilise infra existante, priorité phase 1 |
| D8 | Auth mobile offline | JWT Cloud + clé publique locale | Token séparé Edge | SSO cohérent, pas de second système d'auth |
| D9 | Module Edge | `app/Modules/Sync/` nouveau module DDD | Service externe | Cohérence architecture DDD existante |
| D10 | Sync protocol | REST JSON + HMAC | WebSocket / gRPC | Simplicité, firewalls corporate, retry facile |

---

## 11. Risques identifiés

| ID | Risque | Probabilité | Impact | Mitigation |
|----|--------|-------------|--------|------------|
| R1 | Divergence schéma DB Edge vs Cloud | Haute | Critique | Migrations versionnées, Edge-version-check au démarrage |
| R2 | Conflits massifs après longue coupure | Moyenne | Haute | TTL sync_queue + full reconciliation 24h |
| R3 | Client IT ne sait pas gérer Docker | Haute | Haute | Script install.sh one-liner + documentation step-by-step |
| R4 | JWT expiré pendant offline long | Basse | Haute | Refresh proactif + grace period 7 jours |
| R5 | Attaque sur l'Edge local (réseau interne) | Basse | Haute | IP whitelist + JWT sur tous les endpoints |
| R6 | Perte de données si Edge crash avant sync | Moyenne | Haute | Volume Docker persistant + sync_queue transactionnelle |
| R7 | Utilisation Edge sans abonnement valide | Basse | Critique | Licence RSA signée + grace period + read-only fallback |
| R8 | Mauvaise isolation tenant (bug code) | Très basse | Critique | BelongsToCompany déjà partout + tests d'isolation |
| R9 | Performances Edge sur petit matériel | Moyenne | Moyenne | Config Laravel optimisée (Octane?) + cache OPcache |
| R10 | Multi-site sync race condition | Basse | Haute | site_id comme discriminant + clock Lamport simplifiée |

---

## 12. Schéma relationnel — nouvelles tables

> Ces tables sont **ajoutées sans modifier les tables existantes**.

```sql
-- Table de configuration Edge (une ligne par Edge déployé)
edge_nodes (
  id              UUID PRIMARY KEY,
  tenant_id       UUID NOT NULL,  -- FK → companies
  site_id         UUID NULL,      -- pour multi-site
  name            VARCHAR(128),   -- "Site Paris - Edge 1"
  edge_secret     VARCHAR(256),   -- HMAC secret, chiffré en base
  status          ENUM('active','suspended','revoked') DEFAULT 'active',
  last_sync_at    TIMESTAMP NULL,
  last_seen_at    TIMESTAMP NULL,
  created_at      TIMESTAMP,
  updated_at      TIMESTAMP
)

-- Licence Edge locale (répliquée sur l'Edge)
edge_licenses (
  id              UUID PRIMARY KEY,
  edge_node_id    UUID NOT NULL,  -- FK → edge_nodes
  tenant_id       UUID NOT NULL,
  issued_at       TIMESTAMP NOT NULL,
  expires_at      TIMESTAMP NOT NULL,
  grace_ends_at   TIMESTAMP NOT NULL,
  features        JSONB,          -- liste des modules activés
  max_employees   INT,
  signature       TEXT,           -- RSA-2048 base64
  revoked_at      TIMESTAMP NULL,
  created_at      TIMESTAMP
)

-- Queue de synchronisation (sur Edge local)
sync_queue (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL,
  site_id         UUID NULL,
  entity_type     VARCHAR(64) NOT NULL,
  entity_id       UUID NOT NULL,
  operation       VARCHAR(8) NOT NULL,  -- create/update/delete
  payload         JSONB NOT NULL,
  local_ts        TIMESTAMP NOT NULL DEFAULT now(),
  synced_at       TIMESTAMP NULL,
  retry_count     SMALLINT DEFAULT 0,
  last_error      TEXT NULL,
  conflict        BOOLEAN DEFAULT false,
  conflict_resolution VARCHAR(32) NULL,
  INDEX (synced_at, local_ts),
  INDEX (entity_type, entity_id)
)

-- Log des conflits résolus
sync_conflict_log (
  id              UUID PRIMARY KEY,
  tenant_id       UUID NOT NULL,
  entity_type     VARCHAR(64),
  entity_id       UUID,
  local_payload   JSONB,          -- version Edge
  cloud_payload   JSONB,          -- version Cloud
  resolved_by     VARCHAR(32),    -- 'cloud_wins' | 'edge_wins' | 'manual'
  resolved_at     TIMESTAMP,
  notified_user   VARCHAR(64) NULL
)

-- Curseur de synchronisation
sync_cursors (
  id              UUID PRIMARY KEY,
  edge_node_id    UUID NOT NULL,
  direction       VARCHAR(4) NOT NULL,  -- push / pull
  last_cursor     TIMESTAMP,
  last_run_at     TIMESTAMP,
  records_pushed  INT DEFAULT 0,
  records_pulled  INT DEFAULT 0
)

-- Table site (multi-site)
sites (
  id              UUID PRIMARY KEY,
  tenant_id       UUID NOT NULL,  -- FK → companies
  name            VARCHAR(128),
  address         TEXT NULL,
  timezone        VARCHAR(64),
  created_at      TIMESTAMP
)
-- NOTE: Ajouter site_id (nullable) sur attendance_logs, attendance_kiosks
```

---

## 13. Flux de données

### 13.1 Flux nominal — Pointage offline

```
[Employé] → Tap sur app Flutter
   ↓
[Mobile] → Vérifie : Edge disponible sur réseau local ?
   ↓ OUI
[Mobile] → POST http://leopardo.local/api/v1/attendance/check-in
   ↓
[Edge Laravel] → ProcessCheckIn Action (identique au Cloud)
   ↓
[Edge DB] → INSERT attendance_logs (synced_from_offline = true)
[Edge DB] → INSERT sync_queue (operation = 'create', entity = 'attendance_log')
   ↓
[Mobile] → Affiche "✅ Pointage enregistré (mode local)"
   ↓ (réseau disponible plus tard)
[SyncScheduler] → Flush sync_queue → POST Cloud /api/v1/sync/push
   ↓
[Cloud] → Traite + ACK → Edge marque synced_at
```

### 13.2 Flux de sync complète

```
Edge SyncScheduler (background)
  ┌─────────────────────────────────────┐
  │  PUSH (Edge → Cloud)                │
  │  1. SELECT sync_queue WHERE !synced │
  │  2. Batch 100 ops                   │
  │  3. POST /sync/push + HMAC          │
  │  4. Cloud : validate, upsert, ACK   │
  │  5. Edge : mark synced              │
  └─────────────────────────────────────┘
  ┌─────────────────────────────────────┐
  │  PULL (Cloud → Edge)                │
  │  1. GET /sync/pull?since={cursor}   │
  │  2. Cloud : filtre par tenant_id    │
  │  3. Retourne delta JSON             │
  │  4. Edge : upsert local             │
  │  5. Update pull_cursor              │
  └─────────────────────────────────────┘
  ┌─────────────────────────────────────┐
  │  CONFLICT RESOLUTION                │
  │  1. Cloud détecte conflit           │
  │  2. Applique règle LWW hiérarchique │
  │  3. Log dans sync_conflict_log      │
  │  4. Notifie RH si conflit critique  │
  └─────────────────────────────────────┘
```

---

## 14. Checklist de validation CTO

Avant de passer en PHASE 2, valider les points suivants :

### Architecture
- [ ] Approuver le choix Docker Compose pour Leopardo Edge
- [ ] Valider PostgreSQL local (vs SQLite pour l'Edge)
- [ ] Confirmer que Laravel sera le runtime Edge (pas de nouveau service Go/Node)
- [ ] Valider les feature flags Edge (modules ON/OFF)
- [ ] Approuver l'ajout de `site_id` sur `AttendanceLog` et `AttendanceKiosk`

### Synchronisation
- [ ] Valider la règle de résolution de conflits (Cloud gagne sur Manager/Admin)
- [ ] Confirmer la fréquence de sync (30s push, 60s pull)
- [ ] Approuver la taille max sync_queue (10 000 ops / 30 jours)
- [ ] Valider le protocol REST+HMAC (pas WebSocket/gRPC)

### Sécurité
- [ ] Valider la licence RSA-2048 signée côté Cloud
- [ ] Confirmer la grace period (7 jours après expiration)
- [ ] Approuver le comportement read-only après grace period
- [ ] Valider que le JWT Cloud est réutilisé sur Edge (pas de second auth)

### Mobile
- [ ] Valider Drift (SQLite) pour le stockage local Flutter
- [ ] Confirmer mDNS (`leopardo.local`) pour la découverte Edge
- [ ] Approuver le TTL des permissions cachées (12h)

### Multi-tenant
- [ ] Confirmer 1 Edge = 1 tenant (pas de multi-tenant sur un seul Edge)
- [ ] Valider le flow de provisioning (génération package par Admin Cloud)

### Évolutivité
- [ ] Confirmer l'approche multi-site (site_id discriminant)
- [ ] Valider que l'IA locale est une phase future (pas incluse dans MVP)

---

## Prochaine étape

**PHASE 2** (après validation) : Schéma technique détaillé

Contenu de la Phase 2 :
- Diagrammes de séquence complets (Mermaid)
- Schéma de toutes les migrations à créer
- API contracts (OpenAPI) pour les endpoints sync
- Structure complète du module `app/Modules/Sync/`
- Structure Drift (Flutter) — toutes les tables locales
- docker-compose.yml Edge complet

---

*Document généré pour validation — aucun code créé à ce stade.*
