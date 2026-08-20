# Kiosque — Méthodes de pointage configurables par tenant (#5119)

**Date** : 2026-08-19  
**Issues** : #5119 (EPIC) · #5120 (API) · #5121 (enforcement) · #5122 (employé badge) · #5123 (UI kiosk) · #5124 (spec-kit)

---

## Vue d'ensemble

Chaque kiosque ZKTeco peut être configuré pour n'accepter que certaines méthodes de pointage :
- `fingerprint` — empreinte digitale
- `face` — reconnaissance faciale
- `card` — badge/carte (saisie du numéro de badge)

Si aucune méthode n'est configurée (`null` / champ absent), **toutes les méthodes sont autorisées** (rétro-compatibilité).

---

## 1. Configuration via l'API — ZktecoDevice.punch_methods

### Créer un device avec des méthodes restreintes

```http
POST /api/v1/zkteco/devices
{
  "serial_number": "ZK-001",
  "name": "Entrée principale",
  "punch_methods": ["face", "card"]
}
```

### Mettre à jour les méthodes d'un device existant

```http
PUT /api/v1/zkteco/devices/{id}
{
  "punch_methods": ["fingerprint"]
}
```

### Réinitialiser (toutes méthodes autorisées)

```http
PUT /api/v1/zkteco/devices/{id}
{
  "punch_methods": null
}
```

**Valeurs valides** : `fingerprint`, `face`, `card`. Toute autre valeur retourne 422.

### Défaut entreprise (optionnel)

Un défaut peut être configuré dans `company_settings` avec la clé `kiosk.punch_methods.default` (valeur JSON, ex. `["fingerprint","face"]`). Si un device n'a pas de `punch_methods` configuré, ce défaut est utilisé.

---

## 2. Badge employé (#5122)

Le pointage par carte nécessite un `badge_number` enregistré sur l'employé.

### Enregistrer un badge

```http
PATCH /api/v1/employees/{id}
{
  "badge_number": "A-1042"
}
```

- `badge_number` est unique par tenant (nullable)
- L'API expose un bloc `enrollment` dans la ressource employé :
  ```json
  {
    "enrollment": {
      "fingerprint": true,
      "face": false,
      "card": true
    }
  }
  ```

---

## 3. Enforcement à la synchronisation (#5121)

Lors du `POST /api/v1/zkteco/sync-attendance/{serialNumber}`, chaque record peut porter un champ `method` :

```json
{
  "records": [
    {
      "user_id": "zk-001",
      "timestamp": "2026-09-01T08:00:00Z",
      "punch_type": 0,
      "method": "card",
      "badge_number": "A-1042"
    }
  ]
}
```

**Codes d'erreur** (dans `errors_count` + log audit) :
| Code | Condition |
|------|-----------|
| `PUNCH_METHOD_NOT_ALLOWED` | La méthode n'est pas dans la liste autorisée du device |
| `EMPLOYEE_METHOD_NOT_ENROLLED` | L'employé n'a pas l'enrollment requis pour cette méthode |

**Rétro-compat** : si `method` est absent, la méthode `fingerprint` est supposée (comportement actuel).

---

## 4. Configuration du bridge local / kiosk web (#5120/#5123)

### config.json

```json
{
  "punch_methods": ["face", "card"]
}
```

Le bridge expose `punch_methods` dans `/local/status`. Si `null` (ou absent), toutes les méthodes sont disponibles dans l'UI.

### UI Kiosk Web

- Le sélecteur de méthode affiche **uniquement** les méthodes autorisées
- Sélectionner `card` affiche un champ de saisie du badge au lieu de l'identifiant biométrique
- État « aucune méthode disponible » affiché si la liste est vide

---

## 5. Flux carte (card) pas à pas

1. Sélectionner **Carte / Badge** dans le sélecteur
2. Saisir le numéro de badge dans le champ dédié
3. Cliquer **Arrivée** ou **Départ**
4. Le bridge envoie `{ method: "card", badge_number: "A-1042", ... }` à l'API
5. L'API résout l'employé par `badge_number` (en plus de `zkteco_id` et `matricule`)
6. Si l'employé n'a pas de `badge_number` enregistré → refus `EMPLOYEE_METHOD_NOT_ENROLLED`
