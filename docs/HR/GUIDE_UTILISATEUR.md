# Guide utilisateur — Module HR (issue #5263)

**Version** : 2026-08-22 · **Surface** : API `/api/v1` + portail web + apps mobiles (employee / manager / hr).

## 1. Vue d'ensemble

Le module HR couvre le cycle de vie de l'employé : **référentiels** (départements, positions), **dossier employé**, **contrats** (par pays, amendements, signature), **évaluations**, **plans de carrière**, et le **self-service** employé. Tout est multi-tenant (une entreprise ne voit jamais les données d'une autre).

## 2. Référentiels d'organisation

| Endpoint | Rôle | Usage |
|---|---|---|
| `GET/POST /departments` | manager (principal/rh) | lister/créer des départements |
| `GET/PUT/PATCH/DELETE /departments/{id}` | manager | fiche / renommer / supprimer |
| `GET /departments/{id}/hierarchy` | manager | organigramme du département (arbre manager → équipe) |
| `GET/POST /positions` · `GET/PUT/PATCH/DELETE /positions/{id}` | manager | référentiel des postes |

Le manager `dept` ne voit que son département ; l'employé ne gère pas les référentiels (403).

## 3. Dossier employé

- `GET /employees` (manager) — liste paginée/filtrable (status, role, recherche, tri allowlisté).
- `POST /employees` (principal/rh) — création avec mot de passe ou invitation ; `salary_type` (`fixed`/`hourly`/`daily`) + `salary_base`/`hourly_rate`.
- `GET /employees/{id}` — fiche complète (l'employé n'accède qu'à sa propre fiche ; le salaire n'est visible qu'aux rôles autorisés — voir [Matrice RBAC](RBAC_MATRIX.md)).
- `PUT/PATCH /employees/{id}` — édition (principal/rh ; l'employé peut compléter ses données personnelles).
- `POST /employees/{id}/archive` — archivage (principal/rh).
- Import CSV : `POST /employees/import` (principal/rh) — rapport ligne par ligne.

## 4. Contrats (par pays)

- `POST /contracts` — création ; si aucune clause explicite, **clauses légales du pays de l'entreprise** semées automatiquement (DZ loi 90-11, MA loi 65-99, TN loi 96-62, SN loi 97-17 — option `apply_legal_template=false` pour désactiver).
- `GET /contracts/templates?country=DZ` — modèle légal du pays (période d'essai, préavis, congés, heures sup, SMIG, cotisations, clauses CDI/CDD).
- Cycle de vie : `POST /contracts/{id}/activate` (draft → active, signe implicitement) · `suspend` · `terminate` (motif) · `renew`.
- `POST /contracts/{id}/sign` — signature explicite (date + document), idempotente.
- `GET /contracts/{id}/amendments` + `POST /contracts/{id}/amendments` — historique des avenants (salaire, poste, heures, renouvellement) ordonné par date d'effet.
- `GET /contracts/expiring` — contrats proches de l'échéance.
- `GET /contracts/{id}/pdf` / `generate-pdf` — PDF généré (articles + clauses légales) — employé (ses contrats) ou manager.

## 5. Évaluations

Workflow **brouillon → soumise → accusée** (`draft → submitted → acknowledged`) :

- `POST /evaluations` (manager) — création avec période, score /5, critères, forces/axes d'amélioration.
- `PUT /evaluations/{id}/submit` (manager) — soumission.
- `PUT /evaluations/{id}/acknowledge` (employé concerné) — accusé de réception (verrouille).
- `DELETE` uniquement en brouillon. Un manager `dept` n'évalue que son département.

## 6. Plans de carrière

- `GET/POST /career-events` (manager) — événements : `promotion`, `raise` (augmentation), `transfer` (département), `title_change`.
- Workflow `pending → approved → applied` (ou `rejected`) via `PUT /career-events/{id}/approve|reject|apply`.
- **Impact paie** : `apply` met à jour le salaire de base / poste / département de l'employé — le prochain run de paie l'utilise.
- L'employé voit son parcours complet (contrats + événements) via `GET /me/career`.

## 7. Self-service employé (`/me/*`)

| Endpoint | Contenu |
|---|---|
| `GET /me` | profil compact (rôle, langue, capabilities) |
| `GET /me/career` | parcours : contrats (timeline) + événements de carrière |
| `GET /me/contracts` · `/me/contract` | contrats (liste / actif) |
| `GET /me/trainings` · `POST /me/trainings/{id}/enroll` | formations + inscription |
| `GET /me/loans` · `/me/loans/{id}/repayments` | avances/emprunts + échéancier |
| `GET /me/pay-slips` · `/me/pay-slips/{id}/document` | bulletins + PDF |

## 8. RBAC en bref

- **principal / rh** : gestion complète (dossiers, contrats, évaluations, carrière, référentiels).
- **dept / superviseur** : leur périmètre uniquement (département / équipe directe).
- **comptable** : lecture des salaires/paie, pas d'édition RH.
- **employé** : self-service + son propre dossier (salaire visible sur sa fiche uniquement).
- Matrice exhaustive : [`docs/HR/RBAC_MATRIX.md`](RBAC_MATRIX.md).

## 9. Bonnes pratiques

- Toujours paginer les listes (`per_page`, max 100) ; tri allowlisté uniquement.
- Les messages d'erreur sont localisés ×4 (fr/en/ar/tr) — ne pas dépendre de la valeur littérale anglaise.
- Les données bancaires (IBAN, RIB, NID) ne sont jamais renvoyées par l'API (chiffrées au repos).
