# Mission Farm OS — dossier documentaire

**Branche :** `bc/bc-37-farm-os` · **PR :** #8572 · **Bounded context :** BC-37 AGRICULTURE
**Spec de reference :** [`../specifications/SOLUTION_FARM_OS.md`](../specifications/SOLUTION_FARM_OS.md)

---

## Lire dans cet ordre

| # | Document | Role |
|---|---|---|
| **00** | [`00_audit_report.md`](00_audit_report.md) | Audit du depot : ce qui existe, ce qui manque, ce qui est hypothetique |
| **01** | [`01_tad.md`](01_tad.md) | **TAD** — architecture technique livree, invariants, ADR |
| **02** | [`02_trd.md`](02_trd.md) | **TRD** — exigences fonctionnelles et techniques, statut honnete par exigence |
| **03** | [`03_appflow.md`](03_appflow.md) | **AppFlow** — parcours utilisateurs alignes sur les routes livrees |
| **04** | [`04_uix_ui_design.md`](04_uix_ui_design.md) | **UIX + UI Design** — experience, navigation, composants, design system |
| **05** | [`05_backend_schema.md`](05_backend_schema.md) | **Backend Schema** — 31 tables tenant, colonnes, index, contraintes, contrat API |
| **06** | [`06_implementation_plan.md`](06_implementation_plan.md) | **Implementation Plan** — tranches, matrices, plan de tests, risques, ADR |

---

## Conventions de lecture

- **LIVRE** — implemente et adosse a un chemin de code reel.
- **PARTIEL** — partiellement implemente ; le manque est nomme.
- **NON LIVRE** — absent, avec la raison (souvent : appartient au socle existant).
- **`[existant]`** — capacite presente sur `main`, avec reference de fichier.
- **`[a faire]`** — ecart ouvert.

**Aucune capacite n'est decrite comme implementee sans reference de code.** Aucun test n'est decrit comme reussi sans execution constatee. Aucune integration n'est dite operationnelle sans cle reelle.

---

## Ce que ce dossier ne fait pas

- Il ne remplace pas le code : chaque affirmation renvoie a un fichier.
- Il ne qualifie pas le produit de « MVP ».
- Il ne presente aucun schema theorique comme deja en base : `05_backend_schema.md` decrit les migrations reellement ecrites.
- Il n'annonce aucune publication, campagne ou depense.

---

## Chiffres cles (verifies)

| Element | Valeur |
|---|---|
| Routes Farm OS | 83 (`api/routes/modules/farm_os.php`) |
| Chemins OpenAPI `/farm/*` | 73 (`api/openapi.yaml`, redocly : 0 erreur) |
| Tables tenant | 31 (7 migrations) |
| Enums (registres fermes) | 30 |
| Capacites activables | 13 |
| Activites agricoles | 16 |
| Types d'operation hors ligne | 8 |
| Tests unitaires | 11 fichiers |
| Tests Feature | 6 fichiers |
| Cout ajoute | **0** — aucune API payante, aucun abonnement |
