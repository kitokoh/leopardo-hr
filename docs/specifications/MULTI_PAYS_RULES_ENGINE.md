# Spécification — Sélection du pays et moteur de règles multi-pays

**Statut :** Brouillon soumis à validation du propriétaire

**Objectif.** Garantir qu’une entreprise sélectionne son pays avant toute configuration ou calcul RH/paie, puis que l’ensemble des calculs utilise exclusivement les règles du pays sélectionné et de sa juridiction applicable. Aucun calcul ne doit retomber silencieusement sur l’Algérie, sur une table globale ou sur des valeurs par défaut non déclarées.

## Principe produit

Lors du provisioning d’un tenant, le pays légal de l’entreprise est obligatoire. Le système valide le code ISO supporté, affiche la devise, le fuseau horaire, la langue par défaut, le niveau de confiance réglementaire et les avertissements de conformité associés. Le pays devient la source de contexte obligatoire pour les employés, contrats, structures salariales, cycles de paie, pointages, absences et exports déclaratifs.

Une simulation ou un calcul de paie ne peut pas être exécuté si le pays n’est pas défini, si le pays demandé ne correspond pas au tenant, si les règles ne sont pas disponibles, ou si la version de règles applicable à la période n’est pas résolue. Dans ces cas, l’API doit retourner une erreur explicite et traçable ; elle ne doit jamais appliquer silencieusement un pays de repli.

## Invariants métier obligatoires

1. `company.country_code` est obligatoire pour tout tenant actif et doit appartenir au registre des pays supportés.
2. Le `country_code` fourni par un client ne peut pas remplacer celui du tenant dans un calcul authentifié. Un endpoint public de simulation peut accepter un pays uniquement si ce parcours est explicitement marqué comme simulation indépendante et ne crée ni ne modifie de données tenant.
3. Toute résolution de règles passe par un service unique de type `CountryRulesResolver` ou par `PayrollCalculator::getRules()`. Les contrôleurs ne doivent contenir aucune table de taux, tranche fiscale ou taux social dupliqué.
4. Les règles sont résolues pour un pays, une période d’effet et, si nécessaire, une entreprise. Les overrides propres à une entreprise doivent être contrôlés, versionnés et audités.
5. Les montants retournés doivent distinguer au minimum salaire brut, cotisations salariales, impôt sur le revenu, autres retenues, salaire net, cotisations patronales et coût total employeur.
6. Toute réponse de calcul doit exposer le pays appliqué, la devise, la période des règles et un identifiant ou une version de barème afin de rendre le résultat explicable.
7. Un pays marqué `pilot` ou `placeholder` doit afficher un avertissement de conformité et ne doit pas être présenté comme un calcul légal certifié.
8. Les tests golden de chaque pays doivent être indépendants des implémentations testées et couvrir les cas limites, les tranches, les arrondis, les plafonds et les périodes d’effet.
9. Une modification du pays d’un tenant existant doit être interdite après création de données de paie, sauf procédure administrative explicite de migration avec confirmation, audit et recalcul contrôlé.
10. Les données de pays doivent être isolées par tenant et ne doivent jamais être déduites d’un email, d’une IP, d’un navigateur ou d’une valeur par défaut implicite.

## Parcours utilisateur cible

Le parcours de création commence par un écran « Pays et juridiction ». L’utilisateur choisit le pays dans une liste filtrable, confirme la devise et le fuseau horaire, puis accepte l’avertissement réglementaire affiché. Le système verrouille ce choix avant d’autoriser la création d’employés, de structures salariales ou de runs de paie.

Dans le cockpit, le pays actif reste visible dans le contexte de l’entreprise. Les écrans de simulation et de paie ne proposent que les règles compatibles avec ce contexte. Un utilisateur autorisé peut consulter les règles effectives et leur version, mais ne peut pas changer de pays depuis un formulaire de calcul.

## Découpage prévisionnel des issues

| Priorité | Sujet | Résultat attendu |
|---|---|---|
| P1 | Pays obligatoire et verrouillage tenant | Aucun tenant actif sans pays validé ; aucun calcul sans contexte pays. |
| P1 | Résolveur unique de règles | Suppression des tables de taux dans les contrôleurs et interdiction des fallbacks silencieux. |
| P1 | Contrat de réponse explicable | Pays, devise, version de règles, brut, impôt, net et coût employeur toujours présents. |
| P1 | Tests d’isolation multi-pays | CI, DZ, FR et autres pays ne peuvent pas se contaminer mutuellement. |
| P2 | Versionnement temporel des règles | Un recalcul historique utilise les règles de la période concernée. |
| P2 | Avertissements de conformité | Les règles pilotes ou placeholders sont signalées avant utilisation. |
| P2 | Migration contrôlée de pays | Changement de pays audité et interdit après paie sans procédure dédiée. |
| P2 | Observabilité et audit | Chaque calcul conserve le contexte, la version et les erreurs de résolution. |
| P3 | Documentation agent et catalogue de cas | Chaque pays supporté possède des cas golden, une fiche métier et un runbook. |

## Hors périmètre initial

Cette spécification ne certifie pas juridiquement les taux. Elle définit les garde-fous techniques et le contrat produit nécessaires pour intégrer des règles validées par des experts locaux. L’ajout d’un nouveau pays doit rester conditionné à une fiche réglementaire, des sources approuvées, des cas golden et une validation métier.

## Critères de validation de la spécification

La spécification sera considérée comme validée lorsque le propriétaire aura confirmé explicitement : le pays obligatoire au provisioning, le verrouillage du pays au niveau du tenant, l’interdiction des fallbacks silencieux, l’usage d’un résolveur unique et la création des issues correspondant au découpage prévisionnel.
