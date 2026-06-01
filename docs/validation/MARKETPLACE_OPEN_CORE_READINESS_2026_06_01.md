# Marketplace/open core readiness - 2026-06-01

## Decision

Leopardo RH ne doit pas encore publier de depot open source ni de marketplace public. Le bon niveau pour le lancement est un cadrage enterprise-first avec contrats API publics, sandbox future et limites claires.

## Score

| Domaine | Score | Observation |
|---|---:|---|
| Bornes open core | 90/100 | Les candidats open source sont limites aux SDK, docs et exemples nettoyes. |
| Protection enterprise | 95/100 | Backend, mobile, kiosk, CI/CD, paie, GPS, biometrie et notifications restent prives. |
| Strategie marketplace | 84/100 | Les scopes et webhooks sont cadres, implementation a lancer apres stabilisation API. |
| Risque secrets/licences | 78/100 | Publication interdite tant que secret scan, license scan et nettoyage demo data ne sont pas industrialises. |

Score global : **87/100**.

## Risques restants

- Publication prematuree d'un package contenant fichiers Firebase, service account ou `.env`.
- Plugin partenaire utilisant des routes internes non documentees au lieu de `/api/v1`.
- Scopes API trop larges qui contournent RBAC ou isolation tenant.
- Absence de sandbox dediee pour integrateurs.
- Ambiguite de support entre community et enterprise.

## Gates avant publication publique

- `api/openapi.yaml` couvre les routes exposees.
- Les exemples utilisent uniquement des donnees anonymisees.
- Un secret scan et un license scan passent sur le package extrait.
- Aucun fichier mobile natif Firebase ou credentials cloud n'est present.
- Les webhooks sont signes et documentes.
- Les scopes marketplace sont allowlistes et testes.
- Une decision juridique valide la licence.

## Conclusion

Le lot 67.7 est clos au niveau attendu pour le lancement : decision strategique documentee, limites open core/enterprise explicites et garde de validation ajoute. L'implementation marketplace reste un chantier futur, pas un prerequis marketing immediat.
