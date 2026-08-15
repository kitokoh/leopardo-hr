# Mini-spec — Issue #3895

## Problème

`VerifyTrialSignup::resolveUniqueSlug()` boucle `exists()` puis insert — non
sérialisé entre deux signups simultanés au même nom de société → violation
d'unicité `companies.slug` → 500 pour l'un des deux (échec d'onboarding trial
aléatoire sous charge).

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Insertion avec candidat + violation unique | Retry borné (≤ 5) avec nouveau candidat, jamais de 500 |
| Test simulant la collision | `TrialSignupSlugRaceTest` vert |
| PHPStan strict / Pint | 0 erreur |

## Correctif

`provisionTrialCompany` enveloppe la transaction de création dans un retry sur
`QueryException 23505` (nouveau candidat via `resolveUniqueSlug`, borné à 5) ;
`resolveUniqueSlug` passe en `protected` pour la sous-classe de test.

Closes #3895
