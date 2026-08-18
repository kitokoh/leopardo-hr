# ADR-0015 — Onboarding : 6 étapes seedées canoniques (dont optionnelles), Quick Start < 15 employés

- **Statut** : proposé (à valider par le propriétaire du produit)
- **Date** : 2026-08-18
- **Liens** : issues #4937 (wizard data-driven, mergé), #4938 (QR app mobile + premier pointage), #4939 (Quick Start), #4940 (cette décision) ; conception `docs/dossierdeConception/11_ux_wireframes/24_ONBOARDING_GUIDE.md`

## Décision

1. **Le seed canonique reste 6 étapes** (`SeedDefaultSteps`) : `add_employees`, `configure_payroll`, `setup_schedules`, `setup_geofence`, `setup_kiosk`, `first_checkin`. Deux d'entre elles sont **optionnelles** (`required=false`) : `setup_geofence` et `setup_kiosk`.
2. **Le wizard web est data-driven** : il affiche les étapes réelles de la checklist backend, complète/saute chaque étape via `PATCH …/{stepKey}/complete|skip` (livré par #4946). Il ne duplique plus de conception figée.
3. **L'étape `first_checkin`** (« Premier pointage effectué ») reste une étape de **validation** : l'utilisateur confirme avoir effectué un pointage de test (le pointage réel se fait dans l'app mobile / le kiosque). Pas de création de pointage fictif côté web.
4. **Le Quick Start (< 15 employés)** est une **variante d'expérience** (voir spec `docs/specifications/ONBOARDING_MOBILE_QR_QUICKSTART.md`), pas un seed différent : mêmes étapes, présentation raccourcie + suggestions de skip, planning par défaut 08:00-17:00 Lun-Sam appliqué côté backend.
5. La **conception « 4 étapes »** (`24_ONBOARDING_GUIDE.md`) est **réinterprétée** : elle reste la cible d'expérience « parcours guidé court », atteinte par le Quick Start plutôt que par un changement du seed.

## Pourquoi

- Le seed 6 étapes est déjà provisionné, testé (`SeedDefaultStepsTest`, `OnboardingSeedStepsTest`) et consommé par les surfaces (web, mobile) ; le réduire casserait des contrats existants sans gain produit.
- La géolocalisation et le kiosque sont des options d'activation (désactivées par défaut côté produit, cf. `13_USER_FLOWS_VALIDES.md`) : les rendre non-requises au seed reflète la réalité sans changer le nombre d'étapes.
- Le wizard data-driven (livré) rend la liste des étapes **honnête** : l'utilisateur voit exactement ce que le backend attend, et le « tout fait en un clic » a été supprimé (anti-friction + honnêteté produit, contrat QA-expert-web).
- Le Quick Start cible le marché PME < 15 employés (positionnement produit) : réduire l'effort d'onboarding pour ce segment améliore l'activation sans toucher au noyau.

## Portée

- Frontend web : wizard data-driven (fait) + affichage QR app mobile + suggestion Quick Start (issue #4938/#4939).
- Backend : aucun changement de seed ; le QR onboarding existe déjà (`GET /company/qr-onboarding`) ; le planning par défaut Quick Start reste à spécifier (voir spec).
- Surfaces mobiles : inchangées (elles consomment déjà la checklist).

## Conséquences

- L'issue #4940 est close par cette ADR (décision documentée) ; toute remise en cause passe par une nouvelle ADR.
- La spec `ONBOARDING_MOBILE_QR_QUICKSTART.md` guide l'implémentation #4938/#4939 ; les deltas backend y sont listés et priorisés (le planning par défaut Quick Start nécessite une décision/implémentation backend séparée).
