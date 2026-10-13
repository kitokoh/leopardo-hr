# APP_FLOW — Flux applicatifs

**Statut :** canonique · **Base :** `main` au 2026-10-13 · **Issue :** #8444

> Les parcours de bout en bout, tels qu'implémentés (ou planifiés, marqués 🔜).
> Le restaurateur sert de fil rouge : le même patron s'applique à toutes les
> verticales.

## 1. Carte des surfaces

```
Vitrine (front/web)          Espace tenant (front/web)        Super-admin (admin-dashboard)
   │  landing, pricing            │  dashboard métier               │  tenants, solutions,
   │  signup/login          ──►   │  onboarding gérant        ──►   │  subscriptions, support
   │  /download (apps)            │  mon logiciel 🔜                │
   └──────────────┬───────────────┴───────────────┬───────────────┘
                  ▼                               ▼
              API Laravel /api/v1  ◄──  Apps Flutter (serveur, employé, manager…)
                  ▲
              Bridge Edge (leopardo.local) — PWA offline, kiosque ZKTeco
```

## 2. F1 — Signup → tenant provisionné

1. **Arrivée vitrine** → CTA « Créer mon espace » (SSO Google ou email).
2. **Onboarding / interview de configuration** (`Modules/Onboarding`,
   `CompleteSetupInterview`, étapes canoniques ADR 0015) : identité de
   l'entreprise, **profil métier**, pays/langue, sites, solutions choisies.
3. **Provisioning** (`TenantProvisioningOrchestrator`) : création tenant,
   installation des rôles/permissions de la solution
   (`SolutionPermissionInstaller`), seeds métier (`SeedDefaultSteps`),
   feature flags — le tout idempotent et audité.
4. **Redirection vers l'espace gérant** de la verticale choisie, avec la
   checklist de mise en service 🔜 (#8451).

En cas d'échec d'étape : reprise depuis le dernier état durable
(`SetupInterviewActivationFailureTest`), jamais de tenant à moitié activé
silencieusement.

## 3. F2 — Mise en service gérant (exemple restaurateur)

Checklist affichée dans l'espace (`/(dashboard)/restaurant`) :

| Étape | Action | Surface |
|---|---|---|
| Établissement | Créer le restaurant, ses infos, ses salles/stations ; multi-restaurants si besoin | `restaurant/referential` |
| Équipe | Ajouter les employés, définir rôles (serveur, cuisinier, caissier, manager) et tâches | `restaurant/team` |
| Produits | Créer plats, menus, catégories, prix, ingrédients/stock | `restaurant/referential`, `restaurant/stock` |
| Rotations | Planifier les shifts et rotations entre sites | `restaurant/rotations` |
| Apps | Installer serveur / cuisine / POS depuis « Mon Logiciel » 🔜 (#8447) | espace dédié |
| Canaux | Activer réservations, livraison, vente en ligne, paiements | `restaurant/reservations`, `delivery`, `online`, `payments` |
| Pilotage | Consulter rapports et suivi d'activité | `restaurant/reports` |

L'équivalent existe pour FuelStation (hub stations/équipe/rotations/stock/
ventes, #8436) ; le patron est généralisé aux autres profils via les
solutions verticales.

## 4. F3 — Flux de service (restaurant)

```
Serveur (app mobile)          Cuisine (KDS 🔜 #8448)           POS (#8449)
   │ prise de commande             │ tickets                        │ encaissement
   ▼                               ▼                                ▼
API /api/v1 ──► file locale si offline ──► sync idempotente au retour réseau
   │
   ▼
Stock décrémenté, rapports alimentés, suivi gérant en temps réel
```

1. Le serveur prend la commande sur l'app mobile (table, plats, options).
2. La commande part en cuisine (statuts : reçu → en préparation → prêt → servi).
3. Le POS encaisse (espèces/carte), même hors-ligne : ticket mis en file locale.
4. Au retour du réseau, le bridge Edge synchronise (déduplication par clé) :
   ventes, stock, rapports.

## 5. F4 — Pointage & RH au quotidien

1. L'employé pointe au kiosque biométrique ZKTeco (offline-capable via Edge)
   ou sur l'app employé.
2. Présences, absences, avances et planning alimentent HR/Payroll.
3. Le gérant suit tâches et évaluations depuis l'espace ou l'app manager.

## 6. F5 — Distribution des apps (« Mon Logiciel », 🔜 #8447)

1. Le gérant ouvre « Mon Logiciel » dans son espace.
2. Il voit **uniquement** les apps de ses solutions actives (ex. restaurateur :
   serveur, cuisine, POS, manager, employé).
3. Chaque app : fiche (rôle, prérequis), téléchargement direct, QR
   d'installation, guide.
4. Scène 3D interactive : constellation des apps du tenant (cohérente avec le
   héro 3D de la vitrine).

## 7. F6 — Offline & sync (pattern général)

```
App/PWA locale ◄──► Bridge Edge (leopardo.local) ◄──► Cloud (quand réseau OK)
        │ file d'attente locale (clé idempotente)            │
        └──────────── rejeu ordonné, dédupliqué ─────────────┘
```

- Coupe internet : le service continue en local (POS, KDS, pointage).
- Réseau de retour : rejeu ordonné et dédupliqué ; indicateur de sync visible.
- Aucun secret dans les traces ; chaque sync porte un correlation ID.

## 8. F7 — Super-admin plateforme

Cycle de vie tenant (suspension, purge classifiée #8426), catalogue de
solutions, abonnements, support — dans `admin-dashboard` (Vue 3), strictement
séparé des espaces tenants.

## 9. Parcours E2E de recette

- `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` — registre des scénarios.
- Cible mission : création de compte → onboarding → espace configuré →
  service hors-ligne → resync, pour chaque profil métier (TRD §5).
