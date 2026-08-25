# Garde-fous de livraison — Refactoring Leopardo HR

## 1. Matrice des risques

| Risque | Probabilité | Impact | Signal d’alerte | Mesure obligatoire |
|---|---:|---:|---|---|
| Fuite inter-tenant | Moyenne | Critique | Requête sans scope, cache partagé, export inattendu | Tests négatifs, repository scoped, revue sécurité |
| Régression de paie | Moyenne | Critique | Écart golden, arrondi différent, règle pays implicite | Double calcul, golden tests, version de règles |
| Contrat API divergent | Élevée | Élevé | 401/403/422 inattendu, client avec wrapper local | OpenAPI/fixtures, client unique, tests contractuels |
| Déduplication Flutter incorrecte | Élevée | Élevé | Provider introuvable, analyse cassée, route modifiée | Catalogue de symboles, analyse/build par app |
| E2E flaky ou legacy | Élevée | Moyen/élevé | Locator obsolète, route historique, timeout | Fixture commune, locator stable, retries limités |
| CI stale ou annulée | Élevée | Élevé | Checks de plusieurs SHA, `cancelled`, queue longue | Résumé par SHA, noms uniques, politique d’annulation |
| Migration de données irréversible | Faible/moyenne | Critique | Rollback absent, script non idempotent | Dry-run, sauvegarde, rollback testé |
| Documentation contradictoire | Élevée | Moyen | Deux Unreleased, ADR introuvable, runbook divergent | Un seul changelog, owner et lien de décision |
| Log sensible | Moyenne | Élevé | Token, payload RH ou secret dans logs | Redaction automatique et test de non-divulgation |

## 2. Pyramide de tests par lot

Chaque lot doit combiner les niveaux suivants. Les tests unitaires vérifient les transformations pures et les règles déterministes. Les tests d’intégration vérifient base de données, policies, jobs, files et événements avec un contexte tenant explicite. Les tests contractuels vérifient les structures API, codes d’erreur et permissions. Les tests E2E couvrent seulement les parcours critiques et l’assemblage réel ; ils ne doivent pas devenir le seul endroit où une règle métier est spécifiée.

| Niveau | Cible | Exemple | Bloquant |
|---|---|---|---|
| Statique | Direction des dépendances et types | PHPStan, ESLint/TypeScript, Dart analyze | Oui |
| Unitaire | Règle ou transformation pure | CountryRuleSet, normalizer, formatter | Oui |
| Intégration | Frontière module/DB/job | Tenant repository, policy, export | Oui |
| Contractuel | API et événements | OpenAPI, 401/403/422, payload versionné | Oui |
| E2E | Parcours utilisateur critique | Login, export, paie, onboarding | Oui pour parcours listé |
| Smoke production | Disponibilité externe | Vitrine, staging, déploiement | Selon statut du check |

## 3. Tests de sécurité tenant

Pour chaque endpoint migré, le jeu minimal doit couvrir le propriétaire autorisé, un utilisateur du même tenant avec rôle insuffisant, un utilisateur d’un autre tenant, un tenant supprimé ou suspendu, un identifiant de ressource manipulé, un cache hit provenant d’un autre tenant et un job asynchrone rejoué. Les réponses doivent être cohérentes avec la politique du domaine et ne doivent pas révéler l’existence d’une ressource interdite lorsque le contrat impose un 404.

Les tests doivent aussi vérifier les frontières non HTTP : commandes Artisan, listeners, jobs, exports différés, notifications et webhooks. Une correction n’est pas considérée complète si le contrôleur est scoped mais qu’un job ou un cache contourne cette protection.

## 4. Validation Payroll

Chaque changement de moteur doit produire un tableau de comparaison avec entrée canonique, version de règles, résultat ancien, résultat nouveau, différence absolue, différence relative et classification. Les cas d’arrondi sont comparés en valeur décimale exacte, jamais par flottants non contrôlés.

Un pays pilote ne doit pas masquer les risques des autres pays. Après validation d’un pays, les tests de non-régression des autres `CountryRuleSet` doivent toujours s’exécuter. Les changements réglementaires doivent être séparés des extractions de code afin que la revue puisse distinguer correction légale et refactoring mécanique.

## 5. Validation des clients et E2E

Les fixtures authentifiées doivent créer un contexte déterministe et documenter l’utilisateur, le rôle, le tenant, le token et les endpoints mockés. Elles ne doivent pas partager un état mutable entre tests. Les secrets de test viennent de variables CI ou de fixtures explicitement non sensibles.

Les locators doivent préférer `getByRole` et un label effectivement associé. Un ID comme `#email` ou `#password` peut être utilisé lorsque le composant le définit explicitement ; dans ce cas, son existence est vérifiée dans un smoke de structure. Les tests ne doivent pas utiliser une assertion historique uniquement parce qu’elle passait auparavant.

## 6. Gouvernance d’une pull request de refactoring

Une PR doit contenir un objectif unique, le risque traité, le contrat préservé, les fichiers concernés, la stratégie de rollback et les preuves de validation. Les changements générés sont identifiés. Toute modification de sécurité ou de paie doit inclure au moins un test négatif ou une comparaison déterministe.

Avant approbation, le reviewer vérifie que la branche est à jour avec sa base, que tous les checks concernent le même SHA, qu’aucun check obligatoire n’est annulé, qu’aucun secret n’est introduit et que le changelog ou l’ADR est mis à jour si le contrat évolue.

## 7. Observabilité et rollback

Chaque migration doit définir une métrique de succès et une métrique de régression. Pour les flux tenant et paie, les logs structurés portent un identifiant de corrélation, le domaine, la version de contrat et un identifiant tenant pseudonymisé si nécessaire. Ils ne contiennent ni token, ni salaire, ni contenu de document, ni payload complet de webhook.

Le rollback doit être testé avant activation. Pour un changement de code pur, il s’agit d’un retour de commit. Pour une migration de schéma ou de données, il faut un script inverse ou une stratégie de compatibilité. Pour Payroll, le moteur précédent reste sélectionnable pendant la phase de comparaison. Pour les clients mobiles, les anciennes routes ou imports peuvent être conservés par shim temporaire, avec date de suppression explicite.

## 8. Definition of Done

Un lot est terminé uniquement lorsque le code respecte la direction de dépendances, les contrôles statiques sont verts, les tests métier et sécurité sont verts, la couverture des nouveaux chemins est suffisante, les contrats API sont cohérents, les E2E critiques passent sur un seul SHA, la documentation est mise à jour et le rollback est connu.

Un workflow `cancelled`, un check d’une autre tête, un rate limit externe ou un job queued ne doit pas être présenté comme une validation fonctionnelle. Le statut doit rester explicitement bloqué jusqu’à obtention d’une preuve valide ou d’une décision documentée sur le caractère informatif du contrôle.

## 9. Rapport de lot à utiliser

Chaque PR de refactoring doit publier le résumé suivant :

```text
Lot : R__
Objectif :
Risque traité :
Contrat préservé :
SHA validé :
Checks statiques :
Tests unitaires/intégration :
Tests contractuels :
Tests E2E :
Sécurité tenant :
Comparaison Payroll :
Migration/rollback :
Métriques avant/après :
Documentation mise à jour :
Risques résiduels :
Décision : prêt / bloqué / à revoir
```

Ce rapport doit être attaché à la PR ou conservé dans la documentation de release. Il permet de distinguer un refactoring réellement validé d’un simple changement qui compile.
