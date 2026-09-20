# Démonstration pilote CRM — parcours complet (issue #5743, CRM-PRE)

- **Statut :** actif — guide de démonstration du CRM client Leopardo
- **Date :** 2026-08-28
- **Seed :** `php artisan db:seed --class=CrmPilotSeeder` (environnements pilote/demo uniquement)
- **Charge :** `php artisan db:seed --class=CrmBenchmarkSeeder` (volumes synthétiques séparés)
- **Prépare :** #5715 (UI web) et le pilote #5731

---

## 1. Environnement

| Élément | Valeur |
|---|---|
| Tenants de démo | `crm-pilot-alpha` (Amina Principal / Karim RH), `crm-pilot-beta` |
| Emails de démo | `principal@alpha.crm-pilot.leopardo.test`, `rh@alpha.crm-pilot.leopardo.test` (idem beta) |
| Données | 100 % fictives, déterministes, aucun secret, réinitialisables |
| Base | `shared_tenants` (mode shared) |

Prérequis : migrations à jour (`php artisan leopardo:migrate`) — les tables CRM
arrivent avec #5708/#5709. Si elles sont absentes, le seeder l'annonce et
saute la partie CRM (jamais de crash).

## 2. Parcours de démonstration (account → contact → lead → opportunity → task)

### Étape 1 — Compte
`crm-pilot-alpha` contient le compte **Alpha Industries** (actif, `ops@alpha.crm-pilot.leopardo.test`).

```http
GET /api/v1/crm/accounts
```

### Étape 2 — Contacts (dont 1 primaire)
Deux contacts rattachés : **Jean Dupont** (primaire, Directeur achats) et **Marie Martin** (logistique).

```http
GET /api/v1/crm/accounts/{account}/contacts
```

### Étape 3 — Leads
Deux prospects : **Sarah Khan** (Global Export, statut `qualified`) et **Omar Benali** (Tech Atlas, `new`).

```http
GET /api/v1/crm/leads
```

### Étape 4 — Conversion lead → account/contact/opportunity (#5717)
```http
POST /api/v1/crm/leads/{sarahLead}/convert
Content-Type: application/json

{ "stage": "negotiation", "amount": 120000, "currency": "DZD", "expected_close_date": "2026-09-11" }
```
→ Sarah Khan passe `converted`, une opportunité **Deal Global Export** est créée.

### Étape 5 — Pipeline & opportunités
Le pipeline pilote (6 étapes whitelistées) porte deux opportunités dont une **gagnée** (Deal Tech Atlas).

```http
GET /api/v1/crm/pipelines
GET /api/v1/crm/opportunities
```

### Étape 6 — Tâches / relances
Deux tâches assignées au principal (relance Sarah Khan, proposition Tech Atlas).

```http
GET /api/v1/crm/tasks
```

### Étape 7 — Déduplication & fusion (#5718, démo rapide)
```http
GET /api/v1/crm/dedup/suggestions?entity=accounts
POST /api/v1/crm/merge
Content-Type: application/json

{ "entity": "accounts", "winner_id": {id}, "loser_id": {id} }
```

### Étape 8 — Import CSV (#5714, démo rapide)
```http
POST /api/v1/crm/imports
Content-Type: multipart/form-data

entity_type=accounts
file=@comptes-demo.csv        # en-têtes autorisés : name,email,phone,notes
POST /api/v1/crm/imports/{import}/commit
```

## 3. Volumes de charge (séparés)

`CrmBenchmarkSeeder` crée une entreprise **dédiée** `benchmark-crm-dz`
(500 accounts, 1500 contacts, 800 leads, 300 opportunités, 600 tâches par
défaut — paramétrables par arguments). Elle ne pollue pas les fixtures
fonctionnelles ni les tenants pilotes. Inserts bruts groupés (rapides),
valeurs déterministes.

## 4. Réinitialisation

```bash
# Tout reconstruire (public + tenant + seeds de base)
php artisan leopardo:migrate --fresh --seed
# Puis le pilote CRM (dev uniquement)
php artisan db:seed --class=CrmPilotSeeder
```

Le seed est réentrant : relancer ne duplique rien (skip si le tenant existe).
