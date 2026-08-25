# ADR — Fondations du refactoring de Leopardo HR

- **Statut :** proposé pour validation
- **Date :** 2026-08-25
- **Périmètre :** API Laravel, clients web, applications Flutter, CI/CD
- **Décideurs attendus :** responsables produit, backend, frontend, mobile et sécurité

## Contexte

Le monorepo regroupe plusieurs clients, des domaines métier réglementés et des contrôles CI nombreux. La complexité cumulative provient de contrats implicites, de dépendances intermodules, de code partagé dont le périmètre n’est pas toujours stable et de tests historiques qui ne reflètent pas toujours le comportement courant.

Le refactoring doit donc améliorer les frontières sans modifier silencieusement le calcul de paie, l’autorisation, l’isolation tenant, les contrats publics ou les comportements de sécurité.

## Décisions

### ADR-001 — Les bounded contexts possèdent des frontières explicites

Les contextes `Platform`, `HR`, `Attendance`, `Absence`, `Payroll`, `Billing`, `Notification`, `Accounting`, `Recruitment` et `EdgeSync` sont traités comme des domaines distincts. Un domaine peut exposer un port d’application ou un événement versionné ; il ne doit pas importer directement les détails d’infrastructure d’un autre domaine.

Les dépendances autorisées suivent la direction `Interfaces → Application → Domain → Infrastructure`. Les appels interdomaines directs sont temporairement tolérés uniquement lorsqu’ils sont enregistrés dans la carte de dépendances et accompagnés d’un ticket de réduction.

### ADR-002 — Le contexte tenant est une donnée de sécurité, pas un simple paramètre UI

Toute lecture, écriture, export, notification, job ou cache sensible doit dériver son tenant depuis un contexte authentifié et autorisé. Un identifiant fourni par le client peut sélectionner une ressource, mais ne peut jamais élargir le périmètre autorisé.

Les opérations plateforme et entreprise disposent de services distincts. Les jobs asynchrones transportent un contexte minimal signé ou vérifiable ; les logs ne contiennent pas de données RH inutiles.

### ADR-003 — Payroll est découpé par pipeline, sans réécriture globale

Le calcul est organisé en étapes explicites : normalisation des entrées, règles de rémunération, déductions, contributions, arrondis et résultat. Chaque pays expose un `CountryRuleSet` versionné derrière un contrat commun.

L’ancien moteur reste l’oracle de comparaison pendant la migration. Aucun nouveau moteur ne devient actif pour un pays tant que les golden tests et la comparaison déterministe ne sont pas verts.

### ADR-004 — Le code Flutter partagé doit être réellement stable

`leopardo_core` contient les primitives, services et modèles véritablement communs. Les applications employee, manager, HR et platform admin gardent leurs providers et repositories spécifiques. Les barrels ne réexportent pas des symboles simplement pour masquer une incompatibilité.

Chaque déplacement de fichier met à jour simultanément les imports, les guards, les tests et la documentation. Un shim temporaire est préférable à une duplication silencieuse ou à une rupture de contrat.

### ADR-005 — Les contrats API et E2E sont versionnés par comportement

Les endpoints sensibles ont un contrat OpenAPI ou une fixture contractuelle. Les tests E2E valident le comportement actuellement supporté. Une route supprimée doit être explicitement testée comme supprimée et non remplacée par une assertion historique ambiguë.

Les locators UI utilisent d’abord un rôle ou un label réellement associé. Lorsqu’un ID stable est nécessaire, il devient une partie documentée du contrat de test et ne doit pas être remplacé arbitrairement.

### ADR-006 — La CI valide un seul SHA cohérent

Une PR ne peut être déclarée verte qu’avec des checks correspondant à sa tête courante. Les workflows sont regroupés par famille et leurs noms de checks sont stables. Les annulations de runs obsolètes sont acceptées ; les checks annulés ne sont jamais assimilés à un succès.

Les déploiements externes sont distingués des gates de code. Un rate limit Vercel ou un incident de runner doit être visible comme blocage externe, sans contournement de la protection de branche.

## Conséquences

Ces décisions imposent davantage de contrats et de tests au début du refactoring. En contrepartie, elles rendent les changements localisables, facilitent les revues de sécurité et empêchent qu’un déplacement de code mobile ou backend modifie implicitement un périmètre tenant ou une règle de paie.

## Alternatives rejetées

Une réécriture globale a été rejetée, car elle rendrait simultanées les migrations de données, les règles pays, les clients et la sécurité. La création d’un package partagé universel a également été rejetée : elle déplacerait la complexité vers des abstractions instables. Enfin, l’assouplissement des tests ou guards pour faire passer la CI est explicitement exclu.

## Conditions de réexamen

Cet ADR devra être réexaminé si un nouveau pays exige un modèle de paie incompatible, si un client externe impose un contrat différent, si l’isolation plateforme/entreprise est séparée en services autonomes ou si les performances rendent nécessaire une architecture événementielle plus large.
