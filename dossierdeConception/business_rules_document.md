# BUSINESS RULES – LEOPARDO RH

## 1. Horaires de travail
- Définis par entreprise
- Exemple : 08:00 – 17:00

## 2. Retards
- Tolérance configurable (ex: 5 min)
- Si heure_pointage > heure_début + tolérance → retard

## 3. Absences
- Aucun pointage sur une journée → absence
- Peut être :
  - justifiée
  - non justifiée

## 4. Heures supplémentaires
- Si heure_sortie > heure_fin → heures supp
- Calcul :
  - taux standard ou majoré (configurable)

## 5. Paie
Salaire final =
- salaire_base
+ heures_sup
- pénalités retard
- absences non justifiées

## 6. Jours fériés
- Liste configurable par pays
- Non comptés comme absence

## 7. Fuseaux horaires
- Basés sur entreprise
- Tous les calculs alignés dessus

## 8. Multi-pays
- Chaque entreprise a :
  - devise
  - règles locales (option futur)

## 9. Validation manager
- Toute anomalie doit être validée

## 10. Suppression données
- Soft delete uniquement