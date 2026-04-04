# FEUILLE DE ROUTE — LEOPARDO RH
# Version 4.0.1 | Avril 2026

---

## GESTION DES RISQUES — BUFFER DE RÉALITÉ

### Principe
Le plan de 16 semaines est l'objectif OPTIMISTE.
Le plan réaliste pour une personne seule orchestrant 2 agents IA est 20-24 semaines.

### Règle des sprints (agile léger)
- Sprint = 2 semaines fixes
- Lundi S1 : définir les livrables exacts du sprint
- Vendredi S2 : valider ou invalider chaque livrable
- Ce qui n'est pas livré → sprint suivant (jamais de report silencieux)

### Buffer par type de tâche
| Type | Durée estimée par l'agent | Buffer réaliste à ajouter |
|------|--------------------------|--------------------------|
| Initialisation (CC-01, JU-01) | 4-6h | +50% = 6-9h de supervision |
| Module backend simple (Auth) | 1 semaine | +1 semaine buffer |
| Module backend complexe (Paie) | 1 semaine | +2 semaines buffer |
| Module Flutter | 1 semaine | +1 semaine buffer |
| Frontend Vue.js | 2 semaines | +1 semaine buffer |

### Signaux d'alarme (stop et réévalue si...)
- Un agent bloque plus de 2h sur le même problème → tu interviens
- Un livrable nécessite plus de 3 corrections → revoir la spec, pas le code
- Tu passes plus de 4h/jour à superviser → tu en fais trop en parallèle

### Dates jalons révisées (version réaliste)
| Jalon | Plan optimiste | Plan réaliste |
|-------|---------------|---------------|
| Infrastructure prête | Semaine 1 | Semaine 2 |
| Auth fonctionnelle | Semaine 2 | Semaine 3-4 |
| Pointage mobile fonctionnel | Semaine 7-8 | Semaine 10 |
| MVP complet | Semaine 16 | Semaine 20-24 |
| Beta utilisateurs | Semaine 15 | Semaine 22-26 |
| Launch public | Semaine 16 | Semaine 24-28 |

---

## DEFINITION OF DONE (OBLIGATOIRE PAR MODULE)

Un module est "Done" uniquement si :
- Tous les endpoints du module passent les tests Feature (Pest)
- L'isolation tenant est testee et validee
- Le RBAC du module est implemente et teste
- Les codes d'erreur HTTP et messages i18n sont valides
- Le module est revu et merge sans contredire les specs canoniques
- `ORCHESTRATION_MAITRE.md`, `CHANGELOG.md` et `JOURNAL_RACINE.md` sont mis a jour

Un module n'est PAS "Done" si :
- Il fonctionne localement mais sans tests
- Les tests passent mais sans verification tenant isolation
- Le RBAC est partiel ou remis a plus tard

---

## CADRE ANTI-SCOPE-CREEP

Regle de pilotage :
- Sprint en cours = objectifs de la phase active uniquement.
- Toute idee hors phase est ajoutee en backlog, pas dans le sprint.

Priorite actuelle (Phase 1) :
- Fiabilite pointage
- Calculs rapides de montant du (jour/periode)
- Parcours quickstart petites structures

Sortie de phase :
- Passage en Phase 2 seulement apres validation production MVP (stabilite + retention initiale).
