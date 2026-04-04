# JU-01 à JU-04 — Mobile Flutter (Index)

Ce fichier sert de point d'entrée unique pour les prompts mobile.

- `JU-01_INIT_FLUTTER.md`
- `JU-02_ECRANS_CORE.md`
- `JU-03_JU-04_API_ET_ECRANS_RESTANTS.md`

---

## AJOUTS SPRINT - USAGE PETITES STRUCTURES (MURAT)

### 1) DailyCostScreen (vue patron)
- Route: `manager/daily-cost`
- Roles: `manager_principal`, `manager_superviseur`
- Source API: `GET /employees/{id}/daily-summary?date=YYYY-MM-DD`
- UI minimale:
  - Liste employes du jour avec `status`, `hours_worked`, `daily_wage`
  - Total bas de page: "Aujourd'hui vous devez: {montant} {currency}"
  - Tap ligne -> detail employe du jour

### 2) Widget "Gain estime aujourd'hui" (vue employe)
- Ecran: `HomeScreen` employe (sous CTA de pointage)
- Donnees: resume du jour derive de `daily-summary` du user connecte
- Contenu:
  - Heures travaillees
  - Heures sup
  - Gain estime du jour
  - Mention explicite: "Estime - net final calcule en fin de mois"

### 3) QuickEstimateScreen + partage PDF informatif
- Point d'entree: fiche employe (action "Calculer ce que je dois")
- Source API: `GET /employees/{id}/quick-estimate?from=...&to=...`
- UI:
  - Selecteur date debut/fin
  - Carte resultat: brut estime, deductions estimees, net estime
  - Bouton "Partager" -> PDF "recu de periode" (non officiel)

### 4) Contraintes d'implementation
- Pas de confusion avec bulletin officiel de paie.
- Tous les montants affiches doivent reutiliser la devise entreprise.
- Gestion d'erreurs explicite (401, 403, 422) avec messages localises.
