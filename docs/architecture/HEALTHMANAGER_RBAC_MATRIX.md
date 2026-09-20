# HealthManager — Matrice RBAC (BC-31 HEALTH)

> Issues #7785..#7792. Rôles déterminés par `HealthAccess`
> (`api/app/Modules/HealthManager/Domain/Access/HealthAccess.php`),
> enforcement par 17 policies deny-by-default (`Domain/Policies/`),
> enregistrées au point unique `AuthServiceProvider` (PA2-ARCH-008).

## Rôles

| Rôle | Détermination |
| :--- | :--- |
| **Direction** (`health.admin`) | manager avec `manager_role` `principal`/`rh`/null |
| **Praticien** (`health.practitioner`) | fiche `health_practitioners` ACTIVE liée à l'employé |
| **Réception** (`health.reception`) | rôle `reception` dans `health_staff_roles` |
| **Facturation** (`health.billing`) | rôle `billing` dans `health_staff_roles`, ou manager `comptable` |

Employé lambda → 403 partout. Cross-tenant → 404 fail-closed. Solution
inactive → 403 `HEALTH_SOLUTION_INACTIVE` (flag `healthmanager`, défaut false).

## Matrice

| Ressource | Direction | Praticien | Réception | Facturation | Lambda |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Référentiel (services, salles, lits, spécialités, praticiens, rôles staff) | CRUD | lecture | lecture | — | 403 |
| Patients (administratif) | CRUD + archive | **lecture seule** | CRUD + archive | — | 403 |
| Rendez-vous | tout | **les siens** (lecture, transitions) | tout | — | 403 |
| Consultations | lecture + écrire (toutes) | créer les siennes, modifier **les siennes** | **403 même en lecture** | **403** | 403 |
| Prescriptions | lecture | créer sur SES consultations, lire | **403** | **403** | 403 |
| Hospitalisations / occupation | tout | lecture | tout | — | 403 |
| Catalogue d'actes | CRUD | — | — | CRUD | 403 |
| Factures / paiements | tout | — | lecture | tout | 403 |
| Tableau de bord | ✅ | ✅ | ✅ | ✅ | 403 |

Points durs (testés dans `api/tests/Feature/HealthManager/`) :

- **Confidentialité médicale** : la réception n'accède JAMAIS au contenu
  médical (consultations, prescriptions) — 403 y compris en lecture.
- Un praticien qui force un `practitioner_id` étranger (filtre ou payload)
  est ramené à son propre périmètre.
- Seul l'auteur d'une consultation (ou la direction) peut la modifier.
- Matrice canonique par endpoint : 401 non authentifié / 403 solution
  inactive / 403 lambda / happy path / 404 cross-tenant.
