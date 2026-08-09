# 🧪 Pilotes clients — checklist & monitoring (F-24)

> Programme FOCUS — objectif : **3 pilotes DZ actifs** (métrique M+6).

## Checklist de préparation (avant le 1er cycle de paie)
- [ ] Compte client créé (tenant), paramètres pays DZ, devise DZD.
- [ ] Structure salariale + composants conformes (référentiel DZ).
- [ ] Employés importés : contrats, matricules, IBAN, dates d'embauche.
- [ ] Politiques de congés (2,5 j/mois), calendrier des jours fériés.
- [ ] Comptes formés : RH, comptable, manager, employé (guide F-25).
- [ ] Pointage : sites/géofences configurés, app employee installée, kiosk testé.
- [ ] Rappel : le run de paie est un **cycle réel** — prévoir une fenêtre de recalcul manuel (filet).

## Monitoring des 3 premiers cycles
| Indicateur | Seuil d'alerte |
|---|---|
| Temps de clôture | > 30 min (F-12) |
| Anomalies de paie (F-28) | > 0 non traitées avant validation |
| Échecs de jobs (pdf, exports) | > 0 |
| Écarts de pointage non corrigés | > 5 % des employés |
| Tickets support ouverts | suivi hebdo |

## Revue post-pilote (template)
1. Ce qui a coincé (données, formation, calculs).
2. Écarts de paie constatés vs attentes (à rejouer en golden tests).
3. Demandes produit priorisées → backlog FOCUS.
4. Décision : étendre / ajuster / arrêter le pilote.

## Boucle de feedback
Chaque revue pilote alimente : golden tests (F-03), référentiel DZ (F-02), docs client (F-25).
