# ADR — Couches `Application/` vides du backlog #6841 : décisions Fleet, Restaurant, Payroll

- **Statut :** acté — décisions structurelles, **aucun code applicatif** (hors cartographie)
- **Date :** 2026-09-06
- **Périmètre :** `api/app/Modules/{Fleet,Restaurant,Payroll}` (BC-18 FIELD, BC-25 RESTAURANT, BC-07 PAYROLL)
- **ADR parent :** inventaire #6841 (audit architecture 2026-09-05, PR #6832), epic #6528 ; contrôleurs épais → Actions : #6569
- **Issues de référence :** #6899 (Fleet), #6901 (Restaurant), #6896 (Payroll)
- **Délégation :** décisions validées et déléguées au PM par le fondateur le 2026-09-06 (freeze scope 60 jours — validation expresse obtenue, aucun refactor large engagé dans cet ADR)

---

## Contexte

L'inventaire #6841 a mesuré les couches `Application/` vides ou incomplètes des
modules. Trois sous-issues demandaient une **décision de structure** (pas un
remplissage « à l'aveugle » — aucun besoin métier nouveau identifié par
l'audit) :

- **#6899 Fleet (BC-18)** : `Application/` et `Infrastructure/` vides (`.gitkeep`
  seuls, 0 PHP) ; Domain 8 PHP / Interfaces 8 PHP ; contrats morts supprimés en
  #6832. 17 PHP au total.
- **#6901 Restaurant (BC-25)** : 3 PHP seulement (Domain : `Solution/`
  RestaurantManifest + `Survey/` RestaurantSurvey ; `Providers/`). Pas de routes
  propres — webhooks/shop traités par RestaurantManager (inline `api.php`),
  surveys via `Core\Solutions`.
- **#6896 Payroll (BC-07)** : 133 PHP ; `Application/` = 1 Service
  (`PayrollRegularizationService`), 0 Action, pas de `Actions/` ni `DTOs/` ;
  contrôleurs épais (664 l. pour `PayrollRunController`, 557 l.
  `SocialDeclarationController`) orchestrant de riches services Infrastructure.

## Options considérées

1. **Peupler les couches vides immédiatement** (Actions factices ou extraction
   complète sans besoin exprimé).
2. **Supprimer les couches vides** et documenter une architecture cible réduite.
3. **Conserver la structure canonique du template** (5 couches + Providers) et
   **acter par module** : N/A intentionnel OU peuplement au fil des besoins.
4. **Payroll** : construire la couche Application par **extraction de cas
   d'usage nommables** depuis les contrôleurs épais, par lots ordonnés et
   testés (pattern interne : #5591 PayrollCalculator, Expense #6894, Planning
   #6895/#6906).

## Décisions

### D1 — Fleet (BC-18) : couches vides **conservées**, peuplement au fil des besoins

Les couches `Application/` et `Infrastructure/` (vides) sont **conservées**
pour maintenir la structure canonique du module (template 5 couches +
Providers) ; **aucune Action factice** n'est créée. Le module sera peuplé quand
un besoin fonctionnel l'exigera (aujourd'hui : routes de gestion véhicules /
alertes dans `hr_extended.php`, logique encore dans Domain/Interfaces). La
documentation vivante indique « vide — acté ADR-0020 » au lieu de laisser
entendre un état transitoire.

### D2 — Restaurant (BC-25) : module **fournisseur de contenu** → N/A intentionnel

`Restaurant` n'est pas une verticale applicative autonome : il **fournit du
contenu** (`RestaurantManifest`, `RestaurantSurvey`) consommé par
RestaurantManager (webhooks/shop) et `Core\Solutions` (surveys). Les couches
`Application/`, `Infrastructure/`, `Interfaces/` sont donc **intentionnellement
absentes** (N/A acté) — retrait de la mention « en cours » des docs vivantes ;
aucune route propre n'est à créer dans ce module.

### D3 — Payroll (BC-07) : couche Application construite par **extraction par lots** (cartographie d'abord)

La couche Application de Payroll se construit **exclusivement par extraction de
cas d'usage nommables** depuis les contrôleurs épais (#6569), en réutilisant les
services Infrastructure existants (PayrollService, PayrollCalculator,
PayrollCycleService, PayrollClosingService, déclarations par pays, générateurs
bordereau/journal/PDF…) — pattern déjà éprouvé sur Expense (#6894) et Planning
(#6895/#6906) : orchestration pure dans les Actions, persistance et politique
métier inchangées dans les services, **zéro changement de comportement**,
baselines PHPStan inchangées, coverage ≥ 65 % (jeu golden paie DZ inclus).

**Étape 1 (cet ADR) : cartographie v1** — `docs/architecture/
PAYROLL_APPLICATION_CARTOGRAPHIE.md` (clusters, contrôleurs, candidats Actions,
ordre d'extraction). L'issue #6896 **reste ouverte** tant que les lots
d'extraction ne sont pas livrés (pas de `Closes`).

## Conséquences

- `docs/ARCHITECTURE_STATUS.md` : notes des lignes Fleet/Restaurant/Payroll
  alignées (le tableau reste généré depuis le disque — aucune colonne touchée).
- `api/ARCHITECTURE.md` : lignes modules mises à jour + pointeur ADR-0020.
- Aucune suppression de code, aucun changement de comportement, aucune
  migration. Cliquets PHPStan / layer purity / MAT-001 inchangés.
- Réévaluation : à chaque nouveau besoin métier sur Fleet ou Payroll, les
  Actions correspondantes sont créées **dans le module** (jamais dans le
  contrôleur) ; Restaurant reste N/A sauf décision contraire du BC-owner.
