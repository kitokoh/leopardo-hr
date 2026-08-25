# Leopardo HR — Zones de complexité cumulative et plan de refactoring

**Auteur :** Manus AI  
**Périmètre :** monorepo Laravel 12 / PHP 8.4, dashboard Vue, vitrine web, applications Flutter, contrats OpenAPI, workflows GitHub Actions.  
**Principe directeur :** réduire le couplage et rendre les contrats explicites sans modifier les règles métier, l’isolation tenant ou les contrôles de sécurité par effet de bord.

## 1. Synthèse exécutive

Leopardo HR dispose d’une base technique solide et de mécanismes de qualité avancés, mais la complexité cumulative devient le principal risque architectural. Elle se manifeste moins par un unique composant défectueux que par la multiplication des frontières implicites : règles métier partagées entre plusieurs modules, clients qui réimplémentent les mêmes flux, fichiers mobiles dupliqués, contrats E2E historiquement divergents et workflows CI nombreux qui se déclenchent ou s’annulent selon des chemins différents.

Le hotspot le plus critique est **Payroll**. Il concentre les règles nationales, les cycles, les calculs et de nombreux appels intermodules. Le deuxième est la frontière **mobile core / applications spécialisées**, où la déduplication récente a révélé des providers et repositories réellement spécifiques mélangés à des services communs. Le troisième est la frontière **contrat API / clients / tests E2E**, comme l’ont montré les locators de login et les routes historiques attendues par les tests mais absentes du routeur actuel.

La recommandation est de ne pas lancer un grand « rewrite ». Il faut procéder par **refactorings verticaux, réversibles et mesurables**, en commençant par les contrats et les dépendances, puis en traitant Payroll et l’isolation tenant, avant de rationaliser les clients mobiles et la CI.

## 2. Cartographie observée

| Zone | Indice observé | Risque principal | Priorité |
|---|---:|---|---:|
| `api/app/Modules/Payroll` | 119 fichiers faisant référence à d’autres modules ; `PayrollCalculator.php` dépasse 70 000 octets | Calcul erroné, dépendances circulaires, régression pays | P0 |
| `api/app/Modules/HR` | 83 fichiers avec références intermodules | Mélange RH générique / contexte entreprise / autorisations | P1 |
| `Platform`, `Attendance`, `Billing`, `Marketing`, `Notification` | 39 à 51 fichiers intermodules selon le module | Orchestration dispersée et effets de bord | P1 |
| `front/mobile_apps` | 1 115 fichiers ; écrans homonymes dupliqués entre apps | Divergence UX, logique dupliquée, corrections incomplètes | P1 |
| `leopardo_core` | Fichiers l10n générés très volumineux et bases offline générées | Temps de build, conflits générés, bruit de revue | P2 |
| Écrans Flutter | `attendance_screen.dart`, `team_screen.dart`, `settings_screen.dart` et autres très volumineux | UI, état, appels API et règles métier mélangés | P1 |
| `front/web/src` | `i18n.ts` et contenus vitrine très volumineux | Couplage contenu / rendu / SEO / localisation | P2 |
| Dashboard admin | 90 fichiers repérés avec appels API ou wrappers | Contrats réseau et gestion d’erreur dispersés | P1 |
| CI/CD | 44 workflows actifs | Annulations, queues, duplication des gates, feedback lent | P1 |
| Documentation | Environ 850 fichiers sous `docs/` | Décisions difficiles à retrouver, contrats contradictoires | P2 |

Ces mesures sont des signaux de risque, non des preuves suffisantes pour extraire automatiquement du code. Elles doivent guider les audits ciblés et être complétées par une analyse de dépendances PHP/Dart/TypeScript avant chaque refactoring.

## 3. Hotspots à haut risque

### 3.1 Payroll et règles pays — priorité P0

Le calcul de paie est le point où la complexité métier, réglementaire et technique se superpose. La présence d’un calculateur très volumineux et de règles CEDEAO spécifiques indique un risque de classe « God service » : un même composant peut connaître les lignes de paie, les périodes, les exonérations, les cotisations, les arrondis, les contrats, les absences et les règles nationales.

Le risque n’est pas seulement la maintenabilité. Une extraction mal séquencée peut modifier subtilement les arrondis, l’ordre des déductions ou les cas limites pays. La première étape doit donc être de figer les invariants par des tests golden et contractuels avant toute séparation.

**Cible de conception :** séparer le pipeline en `PayrollInputNormalizer`, `CountryRuleSet`, `EarningsEngine`, `DeductionEngine`, `ContributionEngine`, `RoundingPolicy` et `PayrollResult`. Le service d’orchestration ne doit plus contenir les règles détaillées ; il doit composer ces ports et produire un résultat versionné.

### 3.2 Isolation multi-tenant et contexte d’exécution — priorité P0

L’isolation tenant traverse les contrôleurs, policies, services, requêtes, exports, notifications, jobs, caches et clients. Cette transversalité crée un risque de « sécurité par convention » : chaque nouveau point d’entrée doit se souvenir d’appliquer le bon scope, le bon rôle et le bon tenant.

**Cible de conception :** rendre le contexte explicite et immuable dans les services sensibles, imposer des repositories tenant-scoped pour les agrégats concernés, et séparer les opérations plateforme des opérations entreprise. Les contrôleurs ne doivent pas choisir eux-mêmes des identifiants tenant fournis par le client sans vérification d’autorisation.

Les refactorings devront prouver trois propriétés : un utilisateur ne peut pas lire un autre tenant, un job asynchrone conserve le tenant d’origine de manière sûre, et un export ou webhook ne peut pas élargir son périmètre par un paramètre manipulé.

### 3.3 Mobile core et applications spécialisées — priorité P1

La structure Flutter comporte un package `leopardo_core` et plusieurs applications employee, manager, HR, marketing et platform admin. La déduplication récente a montré que les mêmes noms d’écrans et providers existent dans plusieurs applications, alors que certains repositories restent spécifiques à employee ou manager.

Le risque actuel est double. Sans convention, la duplication entraîne des corrections divergentes. Avec une déduplication agressive, les providers spécifiques peuvent être remplacés par des exports communs incompatibles, comme l’a montré l’échec d’analyse employee lors du déplacement des repositories.

**Cible de conception :** définir trois couches nettes : `core` pour les primitives et services réellement partagés, `app_features` pour les capacités propres à une application, et `shell` pour navigation, permissions et composition. Un barrel file ne doit réexporter que des symboles dont le contrat est stable. Les providers spécifiques ne doivent jamais être masqués simplement pour satisfaire un guard.

### 3.4 API Laravel, clients et contrats E2E — priorité P1

Le dashboard admin et la vitrine disposent de plusieurs wrappers API, tandis que les tests E2E utilisent des conventions historiques de labels, routes et fixtures. Les derniers échecs ont montré que le comportement réel peut être correct alors que le test vise un ancien contrat, ou inversement.

**Cible de conception :** centraliser les contrats d’endpoint et les erreurs client, produire des fixtures de test qui respectent le même contrat que la production et distinguer explicitement les tests de compatibilité legacy des tests du comportement courant. Les locators d’interface doivent préférer les rôles et labels réellement associés ; lorsque le contrat DOM impose un ID stable, cet ID doit être documenté et testé comme tel.

### 3.5 Services d’orchestration intermodules — priorité P1

Les mesures montrent un couplage élevé dans HR, Platform, Attendance, Billing, Marketing et Notification. Le problème n’est pas qu’un module appelle toujours un autre module ; c’est l’absence possible de direction stable des dépendances. Les effets de bord peuvent se propager d’un domaine à un autre via des services concrets, des événements, des modèles ou des jobs.

**Cible de conception :** imposer une direction `Interfaces → Application → Domain → Infrastructure`, limiter les appels directs entre domaines et introduire des ports ou événements versionnés pour les intégrations. Les événements doivent porter un payload minimal, un identifiant de corrélation et, pour les données tenant, un contexte validé sans exposer de données inutiles.

### 3.6 CI/CD et gouvernance — priorité P1

Les 44 workflows actifs constituent une surface opérationnelle importante. Les annulations liées aux changements de base, les files d’attente de runners, les checks dupliqués et les déploiements externes non homogènes rendent le diagnostic lent. Une CI complexe peut masquer une régression sous une annulation ou relancer des validations sur un SHA qui n’est plus la tête de PR.

**Cible de conception :** regrouper les gates par familles, rendre leurs noms uniques et stables, clarifier les conditions `pull_request`, `workflow_dispatch` et `push`, et publier un résumé unique de validation. Les workflows de déploiement ne doivent pas être confondus avec les gates de qualité du code. Les checks externes tels que Vercel doivent être identifiés comme requis ou informatifs, jamais laissés dans une zone ambiguë.

### 3.7 Internationalisation, contenu et artefacts générés — priorité P2

Les fichiers l10n générés et les contenus vitrine volumineux augmentent le bruit des diffs et favorisent les conflits de merge. Le risque est surtout opérationnel : une modification de contenu peut déclencher des checks d’application, tandis qu’une régénération partielle peut rendre les checksums incohérents.

**Cible de conception :** séparer sources et artefacts générés, documenter l’unique commande canonique de génération, rendre le manifeste de versions déterministe et limiter les validations aux zones réellement affectées. Les gros fichiers générés ne doivent pas devenir des points de fusion manuelle.

## 4. Plan de refactoring priorisé

### Phase A — Cartographie exécutable et garde-fous, 1 à 2 semaines

Avant d’extraire du code, établir une carte de dépendances générée à partir des namespaces PHP, imports Dart, imports TypeScript et appels API. Ajouter des règles de direction de dépendances qui échouent uniquement sur les nouvelles violations. Établir également un registre des contrats sensibles : tenant, auth, payroll, pays, exports, webhooks et notifications.

Les critères de sortie sont une liste de dépendances versionnée, un propriétaire par bounded context, une liste des invariants de sécurité et une baseline de temps CI. Aucun comportement métier ne doit changer durant cette phase.

### Phase B — Contrat tenant et séparation plateforme/entreprise, 2 à 3 semaines

Créer des services d’accès tenant-scoped et des policies de frontière plateforme/entreprise. Migrer d’abord les lectures et exports à risque, puis les écritures et jobs. Ajouter des tests négatifs systématiques : identifiant d’un autre tenant, rôle insuffisant, tenant absent, contexte expiré, cache d’un autre tenant et job rejoué.

Chaque migration doit utiliser un adaptateur temporaire afin de permettre un retour arrière. Une métrique de logs structurés doit permettre de relier utilisateur, tenant, requête et job sans journaliser de données sensibles inutiles.

### Phase C — Découpage Payroll par pipeline et règles pays, 3 à 5 semaines

Figer les résultats actuels par pays avec golden tests et jeux de cas limites. Extraire ensuite les normalisations, règles de rémunération, déductions, contributions et arrondis derrière des interfaces. Introduire une version de règles pays et une compatibilité explicite entre version de bulletin et version de règles.

La validation doit comparer ancien et nouveau moteur sur les mêmes entrées, avec une tolérance nulle lorsque le résultat est légalement déterministe. Les écarts doivent être classés avant fusion : correction attendue, bug ancien, arrondi documenté ou régression bloquante.

### Phase D — Stabilisation du contrat API et des clients, 2 à 3 semaines

Inventorier les endpoints consommés par chaque client. Définir un client API par application et une couche d’erreurs commune, sans mélanger les contrats plateforme et entreprise. Générer ou valider les types à partir d’OpenAPI lorsque le contrat le permet.

Migrer les tests E2E vers des fixtures authentifiées communes, des locators stables et des assertions de comportement actuel. Les anciennes routes doivent être soit restaurées avec une décision produit explicite, soit marquées comme supprimées et testées en 404 authentifiée.

### Phase E — Rationalisation Flutter, 3 à 4 semaines

Pour chaque écran dupliqué, mesurer d’abord la divergence réelle. Extraire les composants purs, modèles et services avant d’extraire l’état ou la navigation. Définir un catalogue de providers : core partagé, application, feature et test.

Les barrel files doivent être courts et intentionnels. Chaque déplacement de fichier doit être accompagné d’un shim temporaire ou d’une mise à jour atomique de tous les imports, du guard et des tests. Le pipeline CI doit exécuter `dart format`, analyse, tests et builds sur la même révision.

### Phase F — Réduction et fiabilisation de la CI, 1 à 2 semaines

Regrouper les workflows en familles : validation rapide, sécurité, backend, web, mobile, E2E et déploiement. Conserver un nom de check unique par gate obligatoire. Définir la politique d’annulation : annuler les runs PR obsolètes, ne jamais annuler un run de base nécessaire à un déploiement ou à un audit.

Ajouter une étape de résumé qui associe chaque check au SHA vérifié et indique clairement `success`, `failure`, `cancelled` ou `external-blocked`. Une PR ne doit jamais être déclarée verte à partir d’un mélange de checks provenant de plusieurs heads.

### Phase G — Documentation et suppression contrôlée, continue

Après chaque extraction validée, supprimer les adaptateurs et chemins legacy inutilisés. Mettre à jour un ADR court, le changelog et la carte de dépendances. Les suppressions de branches, routes ou fichiers ne doivent intervenir qu’après recherche globale, validation CI et confirmation de l’absence de consommateur.

## 5. Garde-fous obligatoires

| Garde-fou | Exigence |
|---|---|
| Sécurité tenant | Tests positifs et négatifs sur chaque endpoint migré |
| Paie | Comparaison ancien/nouveau moteur sur jeux golden par pays |
| API | Contrat OpenAPI ou fixture contractuelle versionnée |
| Mobile | `dart format`, analyse, tests et build sur le même SHA |
| E2E | Fixture authentifiée commune et assertions sur le comportement actuel |
| CI | Aucun check déclaré vert s’il est `cancelled`, stale ou issu d’un autre head |
| Données | Migration réversible, idempotente et sans élargissement de scope |
| Observabilité | Corrélation requête/job/tenant sans données sensibles inutiles |
| Documentation | Changelog et ADR mis à jour dans le même changement |
| Suppression | Recherche globale des consommateurs avant retrait |

## 6. Ordre recommandé des premiers lots

Le premier lot doit rester petit et mesurer la méthode : **contrat tenant des exports et notifications**, car il combine risque élevé et périmètre observable. Le deuxième lot doit traiter un sous-pipeline Payroll dans un seul pays avec golden tests. Le troisième doit extraire un service mobile réellement partagé et un seul écran représentatif. Le quatrième doit rationaliser les checks Web E2E et les workflows associés.

Il faut éviter de commencer par une réécriture de `PayrollCalculator.php`, une fusion complète des applications Flutter ou une refonte de toute la CI. Ces chantiers sont importants, mais ils ont trop de variables simultanées pour servir de premier refactoring.

## 7. Critères de réussite à 90 jours

À trois mois, le projet devrait pouvoir démontrer que les dépendances entre bounded contexts suivent une direction documentée, que les accès tenant sensibles passent par des frontières identifiables, que les résultats de paie sont comparables par pays et version de règles, que les clients utilisent des contrats API explicites, et que les checks CI d’une PR correspondent tous à une seule tête de commit.

Les métriques à suivre sont le nombre de violations de dépendances nouvelles, le nombre de chemins d’accès tenant non couverts, le temps médian de CI, le taux de rerun/annulation, le nombre de fichiers mobiles dupliqués par feature, la taille des plus gros services et le nombre d’écarts de calcul entre moteurs comparés.

## 8. Décision proposée

La conception actuelle ne justifie pas un remplacement global. Elle justifie une **stabilisation architecturale progressive**, organisée autour de contrats explicites et de petits lots verticaux. La priorité absolue est de protéger l’isolation tenant et l’exactitude de la paie ; la réduction de duplication et la simplification de la CI viennent ensuite comme moyens de rendre ces garanties durables.

Les fichiers et zones de référence utilisés pour cette analyse sont notamment `api/app/Modules/Payroll/Infrastructure/Services/PayrollCalculator.php`, `api/app/Modules/HR`, `front/mobile_apps/leopardo_core`, `front/mobile_apps/leopardo_employee`, `front/mobile_apps/leopardo_manager`, `front/admin-dashboard/e2e`, `front/web/e2e`, `shared/i18n` et `.github/workflows`.
