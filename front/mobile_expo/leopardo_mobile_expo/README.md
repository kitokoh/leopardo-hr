# Leopardo Mobile — React Native / Expo

Application mobile **React Native (Expo)** portant l'application web `leopardo-hr`
(dépôt GitHub `Kitokoh/leopardo-hr`), **y compris son architecture de verticales métier**.

> **Version 3 — écriture métier.** La v1 couvrait le socle RH, la v2 l'architecture des
> verticales en lecture. La v3 rend les verticales **opérationnelles** : chaque module
> expose désormais ses **formulaires de création** et ses **actions de transition d'état**,
> branchés sur les endpoints réels du dépôt. **18 modules, 64 opérations d'écriture.**
>
> **Version 2 — parité verticales.** Catalogue de modules identique au web, résolution
> d'accès identique, rail « Mon métier », écrans de module et appels API réels pour chaque
> verticale (CRM, comptabilité, marketing, restaurant, voyage, santé, pharmacie, commerce,
> commerce international, hospitality, VTC, station-service, scolarité, bibliothèque,
> cagnottes, communication, caméras, flotte).

---

## 1. Architecture des verticales — le cœur de la v2

### 1.1 Une seule source de vérité, recopiée du web

Le web définit ses modules dans `front/web/src/lib/client-features.ts`
(`CLIENT_MODULES`). Le mobile **reprend ce catalogue à l'identique** dans
`src/verticals/catalog.ts` : mêmes clés, mêmes `capabilityKeys`, mêmes `featureKeys`,
mêmes `allowedRoles`, mêmes `scope` (`core` / `business`), mêmes `vertical`.

| Fichier mobile | Rôle | Équivalent web |
|---|---|---|
| `src/verticals/types.ts` | Types (`ClientModuleKey`, `BusinessVertical`, `ClientModuleAccess`…) | `client-features.ts` (types) |
| `src/verticals/catalog.ts` | Catalogue des 41 modules + descripteurs de verticales | `CLIENT_MODULES`, `BusinessVertical` |
| `src/verticals/access.ts` | Résolution d'accès (rôle, grants, features, plan) | `getClientModuleAccess`, `buildBusinessRail` |
| `src/verticals/screens.ts` | Registre déclaratif des écrans de module | `ModulePageShell` + pages `/app/(dashboard)/*` |
| `src/api/verticals.ts` | Appels API réels par module | `lib/*-api.ts` du web |

**Conséquence concrète :** un utilisateur voit **exactement les mêmes modules** sur mobile
et sur le web. Un module verrouillé côté web l'est aussi côté mobile, et pour la même raison.

### 1.2 La cascade de gating, portée à l'identique

`src/verticals/access.ts` rejoue la cascade du web, dans le même ordre :

1. **Grant explicite** du principal (`module_grants`) → ouvre le module quel que soit le rôle.
2. **`manager_role = principal`** → voit tout.
3. **Matrice `MANAGER_MODULE_ROLES`** → `'any'` ou liste de rôles ; **refus par défaut**
   pour un module absent de la matrice.
4. **`allowedRoles`** pour les non-managers.
5. **État du module** : plancher `solo` → masquage outils d'équipe `solo` →
   `capabilities` → sélection d'inscription (`company.modules`) → `features` racine →
   `company.features` → `plan.features` → **fail-closed** si des gates existent mais que
   la clé manque.

### 1.3 Les 9 verticales et leurs modules

| Verticale | Modules | Préfixe API |
|---|---|---|
| **Restaurant** | `restaurant`, `restaurant_kitchen` | `/restaurant/*` |
| **Agence de voyage** | `travel`, `travel_portal` | `/travel/*` |
| **Commerce** | `commerce` | `/retail/*` |
| **Santé** | `health`, `health_patients`, `health_appointments`, `health_admissions`, `health_billing`, `health_referential`, `pharmacy` | `/health-manager/*`, `/pharmacy/*` |
| **Scolarité** | `edu_manager` | `/edu-manager/*` |
| **Hospitality** | `hospitality` | `/hospitality/*` |
| **Commerce international** | `trade` | `/trade/*` |
| **VTC & taxi** | `vtc` | `/vtc/*` |
| **Station-service** | `fuel` | `/fuel-station/*` |

Modules transverses (socle) : `dashboard`, `employees`, `attendance`, `attendance_geo`,
`absences`, `contracts`, `recruitment`, `payroll`, `training`, `reports`, `accounting`,
`crm`, `marketing`, `showcase`, `library`, `fundraising`, `partner`, `fleet`, `cameras`,
`communication`, `assistant`, `billing`, `integrations`, `mon_logiciel`.

### 1.4 Navigation entre verticales

```
Onglets
├── Accueil
├── Métier        ← n'apparaît que si ≥ 1 verticale est active
│   ├── Restaurant ▾        (carte dépliable)
│   │   ├── Restaurant
│   │   └── Cuisine          (sous-écran, parentKey)
│   ├── Santé ▾
│   │   ├── Santé
│   │   ├── Patients
│   │   ├── Rendez-vous
│   │   ├── Hospitalisations
│   │   ├── Facturation des soins
│   │   ├── Référentiel
│   │   └── Pharmacie
│   ├── … (une carte par verticale active)
│   ├── Socle                (modules transverses)
│   └── Plateforme           (facturation, intégrations)
├── Pointage
├── Absences
├── Paie
└── Compte
    └── Modules & plan       ← catalogue complet + états (actif / essai / verrouillé)
```

Chaque carte de verticale se déplie pour révéler ses modules ; un module ouvre
`ModuleScreen`, l'écran générique qui rend ses KPI et ses listes.

### 1.5 Un écran de module = une entrée de registre

`src/verticals/screens.ts` décrit chaque module de façon déclarative :

```ts
hospitality: {
  subtitle: 'Occupation, arrivées et départs',
  loadKpis: V.fetchHospitalityKpis,
  kpis: [
    { label: 'Occupation', path: 'occupancy.rate', format: 'percent', accent: '#14B8A6' },
    { label: 'Arrivées',   path: 'arrivals_today', format: 'number' },
  ],
  lists: [
    { title: 'Réservations', load: V.fetchHospitalityReservations, columns: [...] },
  ],
},
```

`ModuleScreen` lit cette entrée, appelle les endpoints réels, et rend le bandeau compact
+ les KPI + les listes. **Ajouter un module = ajouter une entrée**, pas un écran de plus.

Un module sans entrée de registre affiche un état **« Module en préparation »** explicite
(avec la mention que l'écran complet est sur le web) — jamais un écran vide ni une erreur.

---

## 2. Fonctionnalités du socle RH (v1, conservées)

| Module | Écran | Endpoints API |
|---|---|---|
| Authentification | `LoginScreen`, `TwoFactorScreen` | `POST /auth/login`, `POST /auth/2fa/verify`, `GET /auth/me`, `POST /auth/logout` |
| Accueil | `HomeScreen` | `GET /dashboard/summary`, `/dashboard/recent-activity`, `/me/daily-summary`, `/tasks/today` |
| Pointage | `AttendanceScreen` | `GET /attendance/today`, `POST /attendance/check-in`, `/check-out` |
| Absences | `AbsencesScreen` | `GET /absences`, `/me/leave-balances`, `POST /absences` |
| Paie | `PayrollScreen` | `GET /payroll/mobile-summary`, `/me/pay-slips` |
| Avances | `SalaryAdvancesScreen` | `GET /salary-advances`, `POST /salary-advances` |
| Tâches | `TasksScreen` | `GET /tasks/today`, `PATCH /tasks/{id}` |
| Plannings | `SchedulesScreen` | `GET /schedules` |
| Équipe | `TeamScreen` | `GET /employees` |
| Notifications | `NotificationsScreen` | `GET /notifications`, `PATCH /notifications/{id}/read` |
| Compte | `ProfileScreen` | `GET /auth/me` |

**Détails d'implémentation :** jeton Sanctum dans le Keychain/Keystore
(`expo-secure-store`), intercepteur 401 qui purge la session, 2FA native, pointage
géolocalisé qui **fonctionne même si la permission est refusée**, tirer-pour-rafraîchir
partout, états chargement/erreur/vide, accessibilité (`accessibilityRole`/`Label`/`State`),
thème sombre aligné sur la charte web.

---

## 3. Structure du projet

```
leopardo-mobile/
├── App.tsx                     # Racine : providers + navigation
├── app.json                    # Config Expo (permissions, identifiants store)
├── eas.json                    # Profils de build EAS (APK / AAB)
├── .env.example                # Modèle de configuration API
└── src/
    ├── api/
    │   ├── client.ts           # Axios + intercepteurs + helpers
    │   ├── secureStorage.ts    # Keychain / Keystore
    │   ├── AuthContext.tsx     # Contexte d'authentification
    │   ├── auth.ts             # login / 2FA / me / logout
    │   ├── attendance.ts       # pointage + résumés
    │   ├── absences.ts         # absences + soldes
    │   ├── payroll.ts          # paie + avances
    │   ├── hr.ts               # dashboard, notifications, tâches, plannings, équipe
    │   ├── verticals.ts        # ★ API de LECTURE de tous les modules métier
    │   └── verticalsWrite.ts   # ★ API d'ÉCRITURE (67 endpoints, v3)
    ├── verticals/              # ★ ARCHITECTURE DES VERTICALES
    │   ├── types.ts            # Types (miroir du web)
    │   ├── catalog.ts          # Catalogue des 41 modules + 9 verticales
    │   ├── access.ts           # Résolution d'accès (cascade du web)
    │   ├── screens.ts          # Registre déclaratif des écrans de module
    │   └── actions.ts          # ★ Registre des 64 opérations d'écriture (v3)
    ├── models/                 # Types TypeScript (miroir des payloads API)
    ├── components/             # Button, TextField, Card, ScreenState, ScreenHeader
    ├── navigation/             # Stack racine + onglets + stack secondaire
    ├── screens/
    │   ├── auth/               # Login, 2FA, Splash
    │   ├── main/               # 10 écrans du socle RH
    │   └── modules/            # ★ VerticalsScreen, ModuleScreen, ModuleActionScreen, ModulesPlanScreen
    ├── theme/                  # Couleurs, espacements, typographie
    └── utils/                  # Formatage dates / montants
```

---

## 4. Démarrage local

### Prérequis
- Node.js 20+
- Expo Go sur votre téléphone, **ou** un émulateur Android / simulateur iOS

### Installation

```bash
cd leopardo-mobile
npm install
cp .env.example .env
```

### Configuration de l'API

```bash
# Émulateur Android (10.0.2.2 = machine hôte vue depuis l'émulateur)
EXPO_PUBLIC_API_URL=http://10.0.2.2:8000/api/v1

# Appareil physique sur le même Wi-Fi : IP LAN de votre machine
# EXPO_PUBLIC_API_URL=http://192.168.1.42:8000/api/v1

# Production
# EXPO_PUBLIC_API_URL=https://api.leopardo-rh.com/api/v1
```

> ⚠️ L'API Laravel doit tourner (`php artisan serve --host=0.0.0.0 --port=8000`
> depuis `api/`) et accepter les requêtes de l'appareil.

### Lancement

```bash
npx expo start
```

Scannez le QR code avec Expo Go (Android) ou l'appareil photo (iOS).

### Vérification des types

```bash
npm run typecheck     # tsc --noEmit
```

---

## 5. Compiler l'APK sur un compte Expo gratuit

Aucun APK n'est compilé dans cet environnement (pas de SDK Android, pas de keystore).
Le build passe par **EAS Build**, qui fonctionne sur le plan gratuit d'Expo.

```bash
cd leopardo-mobile
npm install
npm install -g eas-cli

eas login                      # compte gratuit sur expo.dev
eas build:configure            # remplit le projectId dans app.json

# APK installable (profil `preview`)
eas build -p android --profile preview
```

Le profil `preview` de `eas.json` produit un **APK** (pas un AAB) : build cloud
10–20 min, puis lien de téléchargement direct.

### ⚠️ L'URL de l'API est figée au build

`EXPO_PUBLIC_API_URL` est **embarquée dans le bundle au moment du build**. Pour un APK
destiné à la production, définissez-la **avant** de lancer `eas build` :

```bash
EXPO_PUBLIC_API_URL=https://api.leopardo-rh.com/api/v1 eas build -p android --profile preview
```

Sinon l'APK pointera vers `http://10.0.2.2:8000` (émulateur) et ne joindra rien.

### Profils disponibles (`eas.json`)

| Profil | Sortie | Usage |
|---|---|---|
| `preview` | **APK** | installation directe, tests terrain |
| `production` | AAB | Google Play |

---

## 6. Écriture métier — ce que la v3 ajoute

### 6.1 Le principe : une action = une entrée de registre

Le web expose, pour chaque verticale, des formulaires de création et des boutons de
transition. Le mobile reprend **les mêmes opérations**, mais de façon déclarative :

| Fichier | Rôle |
|---|---|
| `src/api/verticalsWrite.ts` | Appels API d'écriture, un par endpoint réel |
| `src/verticals/actions.ts` | Registre : champs, construction du payload, appel |
| `src/screens/modules/ModuleActionScreen.tsx` | Écran générique qui rend **toutes** les actions |

**Ajouter une opération = ajouter une entrée dans `MODULE_WRITES`.** Aucun écran à écrire.

Deux familles d'actions :

- **`actions`** — création depuis un formulaire vierge (« Nouvelle commande »,
  « Publier un trajet », « Créer un produit ») ;
- **`rowActions`** — opération sur un élément existant (« Encaisser » une commande,
  « Check-in » une réservation, « Annuler une vente »). L'écran charge la liste des
  éléments éligibles et fait choisir.

### 6.2 Couverture — 18 modules, 64 opérations

| Verticale | Module | Opérations |
|---|---|---|
| Restaurant | `restaurant` | Nouvelle commande · Nouveau produit · Encaisser · Marquer servie · Annuler |
| Restaurant | `restaurant_kitchen` | Démarrer la préparation · Marquer prêt |
| Voyage | `travel` | Publier un trajet · Nouvelle réservation · Mettre en vente · Annuler trajet · Confirmer réservation · Émettre billets |
| Voyage | `travel_portal` | Réserver un trajet |
| Commerce | `commerce` | Créer un produit · Mouvement de stock · Ouvrir caisse · Publier/Dépublier · Clôturer caisse |
| Santé | `health` | Nouveau patient · Planifier un RDV · Changer l'état d'un RDV |
| Santé | `health_patients` | Nouveau patient |
| Santé | `health_appointments` | Planifier un rendez-vous |
| Santé | `health_admissions` | Sortie du patient |
| Pharmacie | `pharmacy` | Nouveau produit · Nouvelle vente · Ajuster le stock · Annuler une vente |
| Commerce international | `trade` | Nouvelle demande de prix · Nouvelle contrepartie · Transition RFQ · Accepter une offre · Transition commande |
| Hospitality | `hospitality` | Nouvelle réservation · Nouvel établissement · Confirmer · Check-in · Check-out · Annuler |
| VTC | `vtc` | Nouvelle course · Action chauffeur · Annuler la course |
| Station-service | `fuel` | Vente · Ouvrir caisse · Planifier équipe · Déclarer incident · Annuler vente |
| Scolarité | `edu_manager` | Inscrire un élève · Créer une classe · Nouvelle admission · Créer une évaluation · Convertir une admission |
| CRM | `crm` | Nouveau lead · Nouveau compte · Convertir un lead · Changer l'étape d'une opportunité |
| Comptabilité | `accounting` | Nouveau document · Nouveau contact · Envoyer un document · Encaisser |
| Marketing | `marketing` | Nouvelle publication · Publier maintenant |

### 6.3 Conformité aux contrats de l'API

Les payloads ne sont pas devinés : ils sont recopiés des **FormRequests** du dépôt
(`app/Modules/<Module>/.../Requests/*.php`). Points de vigilance traités :

- **Montants en mineurs** (`price_minor`, `amount_minor`, `unit_price_minor`) — la saisie
  « 12,50 » est convertie en `1250` par `toMinor()`.
- **Deltas signés** — `retail/stock/movements` attend `quantity_delta` + `reason_code`
  (pas un type + une quantité absolue) ; la conversion est faite dans le `build`.
- **Ajustement pharmacie par LOT** — `pharmacy/stock/adjustments` attend `batch_id`,
  pas `product_id`.
- **`target_platforms` (tableau)** pour les publications marketing, pas `network`.
- **Enums fermés** — `provider_code` ∈ `cash|card|mobile_money`, `payment_method`
  ∈ `cash|card|mobile|insurance`, etc.
- **Idempotence** — les créations sensibles (paiement, réservation, course, vente carburant)
  envoient une `idempotency_key` : un rejeu réseau ne crée pas de doublon.
- **Actions destructives** — annulations et clôtures demandent une confirmation native.

### 6.4 Vérification des endpoints

Un script de contrôle a comparé **chaque chemin appelé** aux routes réellement déclarées
dans `api/routes/modules/*.php` (préfixes `ModuleRoutes::tenantModule(...)` inclus) :

- **66 / 67 chemins d'écriture** correspondent à une route existante ;
- le 67ᵉ est un artefact de l'extraction (`/vtc/driver/rides/{id}/{action}` est un
  template à deux variables — les 5 actions existent individuellement) ;
- **16 / 16 chemins de lecture** (sélecteurs de formulaires) correspondent.

---

## 7. Vérifications réellement effectuées

| Vérification | Résultat |
|---|---|
| `npx tsc --noEmit` (mode strict) | ✅ **0 erreur** |
| `npx expo export --platform android` | ✅ **bundle réussi** (895 modules, 2,74 Mo) |
| Conformité des 67 chemins d'écriture aux routes du dépôt | ✅ **66/67** (1 artefact d'extraction) |
| Conformité des 16 chemins de lecture | ✅ **16/16** |
| Champs des payloads vs FormRequests | ✅ **corrigés** (4 écarts trouvés et fixés) |
| Exécution sur appareil / API live | ❌ **non testé** (pas d'instance Laravel ici) |
| Compilation APK | ❌ **impossible ici** — passer par EAS (section 5) |

---

## 8. Périmètre et limites assumées

**Ce qui est aligné sur le web :** le catalogue des modules, la résolution d'accès,
la hiérarchie des verticales, les préfixes API, les endpoints de lecture **et d'écriture**
de chaque module.

**Ce qui reste sur le web (et pourquoi) :**

- **Édition avancée multi-lignes** — les formulaires mobiles couvrent une ligne de
  commande/document. Les paniers complexes (20 lignes, remises, TVA par ligne) restent
  sur le web : sur un écran de téléphone, ils seraient inutilisables.
- **Édition du site vitrine** — éditeur de contenu, reste sur le web.
- **Flux vidéo des caméras** — diffusé par l'application dédiée `leopardo_cameras`.
- **Modules sans écran mobile dédié** — affichés « Module en préparation » avec renvoi
  explicite vers le web (jamais un écran vide).
- **Push notifications** — dépendance installée, enregistrement du token non câblé.
- **Scan QR d'onboarding** — permission caméra configurée, écran non implémenté.
- **Ouverture des PDF de bulletins** — non implémentée.

---

## 8. Sécurité

Le jeton GitHub partagé en clair dans le chat **doit être révoqué**
(GitHub → Settings → Developer settings → Personal access tokens → Delete).
Le dépôt est déjà cloné : plus aucun accès au token n'est nécessaire.
