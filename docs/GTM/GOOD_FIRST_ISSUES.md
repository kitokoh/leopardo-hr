# Good First Issues — Leopardo RH

> Mise à jour 2026-07-21 : cette liste ne reflète que des issues **réellement ouvertes sur GitHub**
> avec le label `good first issue`, vérifiées contre l'état actuel du code (voir lien de chaque item).
> Plusieurs items de la précédente version de ce fichier étaient déjà résolus dans le code
> (validation email unique, tests `FleetControllerTest`/`PaySlipControllerTest`, dark mode dashboard,
> ARIA sur `DataTable`, page `/pricing`) ou pointaient vers des chemins obsolètes
> (`front/mobile/` renommé en `front/mobile_apps/*`, `app/Http/Controllers/Api/V1/` remplacé par
> `app/Modules/*/Interfaces/Api/V1/Controllers/`) — retirés ou corrigés ici.
>
> `docs/GESTION_PROJET/GOOD_FIRST_ISSUES.md` (ancienne liste sœur, mêmes problèmes de chemins
> obsolètes) a été supprimé le 2026-07-29 (audit doc) : ce fichier-ci est désormais la seule
> liste «good first issues» du dépôt.

## Issues ouvertes actuellement

| # | Titre | Domaine |
|---|---|---|
| [#923](https://github.com/kitokoh/leopardo-hr/issues/923) | Ajouter un filtre par mois/année sur la vue Paie (PayrollView) | Frontend (Vue) |
| [#924](https://github.com/kitokoh/leopardo-hr/issues/924) | Endpoint `GET /api/v1/me/contract` (singulier) pour le contrat actif | Backend (Laravel) |
| [#925](https://github.com/kitokoh/leopardo-hr/issues/925) | Internationaliser `AiChatScreen` (FR/EN/AR/TR) dans les 3 apps mobiles | Mobile (Flutter) |
| [#926](https://github.com/kitokoh/leopardo-hr/issues/926) | Documenter les codes d'erreur API (`docs/api/ERROR_CODES.md`) | Documentation |
| [#927](https://github.com/kitokoh/leopardo-hr/issues/927) | Ajouter un écran "Mon profil" dédié à `leopardo_employee` | Mobile (Flutter) |
| [#928](https://github.com/kitokoh/leopardo-hr/issues/928) | Valider le format IBAN à la création/mise à jour d'un employé | Backend (Laravel) |

Chaque issue contient : contexte vérifié dans le code actuel, fichiers concernés, et critère d'acceptation.
Pour proposer une nouvelle "good first issue", vérifier d'abord qu'elle n'est pas déjà résolue en
grep'ant le code (voir avertissement ci-dessus) avant de l'ouvrir sur GitHub.
