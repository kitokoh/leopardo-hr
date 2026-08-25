# Roadmap et backlog de refactoring — Leopardo HR

## Objectif

Réduire la complexité cumulative sans interrompre les parcours métier ni affaiblir la sécurité. Les lots sont conçus pour être livrés séparément, chacun avec un état mesurable et un retour arrière possible.

## Vue d’ensemble

| Lot | Domaine | Priorité | Dépendances | Sortie attendue |
|---|---|---:|---|---|
| R0 | Cartographie et règles de dépendance | P0 | Aucune | Carte versionnée et violations nouvelles bloquées |
| R1 | Isolation tenant et frontière plateforme | P0 | R0 | Accès sensibles centralisés et tests négatifs |
| R2 | Payroll pipeline et règles pays | P0 | R0, golden tests | Moteur découpé, résultats comparables |
| R3 | Contrats API et clients | P1 | R0, R1 | Endpoints et erreurs normalisés |
| R4 | Flutter core/applications | P1 | R0, contrats API | Partage maîtrisé, providers spécifiques préservés |
| R5 | CI/CD et gouvernance | P1 | R0, premiers lots | Checks uniques, SHA cohérent, feedback plus court |
| R6 | Suppression legacy et documentation | P2 | R1–R5 | Adaptateurs retirés et décisions à jour |

## Backlog détaillé

### R0 — Cartographie exécutable

**R0.1 — Générer la carte de dépendances.** Recenser namespaces PHP, imports Dart, imports TypeScript/Vue, endpoints consommés et workflows qui valident les mêmes zones. La sortie doit être déterministe et versionnée.

**R0.2 — Définir les directions autorisées.** Ajouter un guard qui bloque uniquement les nouvelles violations. Les violations historiques sont listées avec propriétaire, justification et date cible.

**R0.3 — Créer le registre des contrats sensibles.** Documenter auth, tenant, payroll, pays, exports, webhooks et notifications avec entrée, sortie, autorisation, invariants et tests de référence.

**Acceptation R0 :** une revue peut localiser le propriétaire d’un module, connaître ses dépendances autorisées et exécuter la commande de génération de la carte sur une machine propre.

### R1 — Isolation tenant et plateforme

**R1.1 — Centraliser la résolution du contexte.** Introduire un objet de contexte immuable pour utilisateur, rôle, tenant, corrélation et origine de la requête. Aucun contrôleur sensible ne construit un tenant à partir d’une valeur non vérifiée.

**R1.2 — Encapsuler les repositories tenant-scoped.** Migrer d’abord exports, notifications, dashboard et recherches ; conserver des adaptateurs pendant la transition.

**R1.3 — Séparer les services plateforme et entreprise.** Les opérations super-admin ne doivent pas être confondues avec les opérations d’un administrateur d’entreprise.

**R1.4 — Sécuriser jobs, caches et événements.** Inclure un contexte minimal vérifiable, éviter les clés de cache sans tenant et rejeter les jobs dépourvus de contexte requis.

**Acceptation R1 :** les tests de lecture inter-tenant, écriture inter-tenant, export inter-tenant, cache inter-tenant, job rejoué et rôle insuffisant échouent proprement ; aucune donnée sensible n’apparaît dans les logs de test.

### R2 — Payroll

**R2.1 — Figer l’oracle actuel.** Créer des jeux golden par pays et par cas limite : salaire normal, absence, prime, exonération, arrondi, plafond, cotisation et bulletin incomplet.

**R2.2 — Extraire la normalisation.** Transformer les entrées HTTP ou importées en un modèle interne validé, sans accès à la base depuis le normalizer.

**R2.3 — Extraire les règles pays.** Définir un port `CountryRuleSet` versionné. Une règle pays ne doit pas lire directement les données d’un autre contexte.

**R2.4 — Extraire les étapes de calcul.** Séparer earnings, deductions, contributions, rounding et résultat. Le service d’orchestration ne fait que composer les étapes.

**R2.5 — Comparer avant activation.** Exécuter l’ancien et le nouveau moteur sur les mêmes entrées ; tout écart est classifié et approuvé avant activation.

**Acceptation R2 :** les résultats déterministes sont identiques, les différences approuvées sont documentées, la version des règles est attachée au résultat et le temps de calcul ne régresse pas au-delà du seuil convenu.

### R3 — API, clients et E2E

**R3.1 — Inventorier les consommateurs.** Pour chaque endpoint, relever clients, rôles, tenant, pagination, erreurs et tests.

**R3.2 — Normaliser les clients.** Un client réseau par application, avec timeout, corrélation, traitement d’erreurs et redaction des secrets ; aucune duplication de logique d’authentification.

**R3.3 — Aligner OpenAPI et implémentation.** Les changements de contrat sont versionnés et accompagnés d’un test de compatibilité.

**R3.4 — Stabiliser les fixtures E2E.** Utiliser une fixture authentifiée commune, des mocks minimaux et des assertions sur le comportement actuel. Les routes supprimées sont testées explicitement en 404 authentifiée.

**Acceptation R3 :** chaque endpoint sensible a un contrat identifiable, les tests E2E ne dépendent pas d’un label non associé ou d’un chemin legacy non décidé, et les erreurs 401/403/404/422/429/5xx sont vérifiées.

### R4 — Flutter

**R4.1 — Classer les symboles.** Chaque provider, repository, modèle et écran est marqué `core`, `application`, `feature` ou `test-support`.

**R4.2 — Extraire les composants purs.** Commencer par modèles, formatters et widgets sans état ; ne déplacer la navigation qu’après stabilisation des contrats.

**R4.3 — Réduire les écrans volumineux.** Scinder UI, état, chargement et actions sans modifier les routes ni les permissions.

**R4.4 — Dédupliquer à l’aide d’un catalogue.** Un symbole ne devient partagé qu’après preuve d’usage identique dans au moins deux applications.

**Acceptation R4 :** chaque application passe formatage, analyse, tests et build sur le même SHA ; les providers spécifiques employee/manager restent accessibles sans alias masqué.

### R5 — CI/CD

**R5.1 — Classer les workflows.** Séparer validation rapide, sécurité, backend, web, mobile, E2E et déploiement.

**R5.2 — Stabiliser les noms de checks.** Un nom obligatoire correspond à une seule gate ; les checks annulés ne sont jamais interprétés comme verts.

**R5.3 — Unifier les conditions.** Documenter `pull_request`, `push`, `workflow_dispatch`, chemins et politique d’annulation.

**R5.4 — Produire un résumé de SHA.** Chaque run publie commit, base, durée, résultat et éventuel blocage externe.

**Acceptation R5 :** une PR possède un tableau lisible sans checks stale provenant d’un autre head, et un rate limit externe est identifié séparément d’un échec de code.

### R6 — Nettoyage contrôlé

**R6.1 — Retirer les adaptateurs legacy.** Supprimer seulement après recherche globale, deux cycles CI verts et validation des consommateurs.

**R6.2 — Archiver les décisions.** Mettre à jour ADR, changelog, carte de dépendances et runbook opérationnel dans le même lot.

**R6.3 — Fermer les branches et issues.** Une branche est supprimée uniquement lorsque son contenu est fusionné ou explicitement abandonné ; une issue est fermée avec lien vers le commit ou l’ADR.

**Acceptation R6 :** aucun chemin legacy non référencé ne reste actif, les owners sont connus et les éléments fermés restent traçables.

## Séquencement recommandé sur 90 jours

| Période | Livraison |
|---|---|
| Jours 1–10 | R0.1–R0.3 et baseline des métriques |
| Jours 11–30 | R1.1–R1.4 sur exports, notifications et dashboard |
| Jours 31–55 | R2.1–R2.5 sur un pays pilote puis extension contrôlée |
| Jours 56–68 | R3.1–R3.4 sur auth, exports et erreurs |
| Jours 69–80 | R4.1–R4.4 sur une feature mobile représentative |
| Jours 81–88 | R5.1–R5.4 et réduction des reruns inutiles |
| Jours 89–90 | R6, bilan métriques et décision de poursuite |

## Règle de découpage des pull requests

Une pull request de refactoring ne doit avoir qu’un objectif architectural principal. Une PR ne mélange pas extraction de code, migration de données, changement de règle pays et refonte visuelle. Toute modification de contrat public possède une section migration et rollback. Les changements générés sont séparés lorsque leur taille empêche une revue fiable.
