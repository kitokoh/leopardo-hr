# ADR 0025 — Tokens Sanctum : TTL 30 jours glissants justifié, contrôles compensatoires

## Statut

Proposée.

**Date** : 2026-09-23
**Décideurs** : Équipe technique Leopardo HR (proposition agent, issue #7655 point 3 ; décision produit sous-jacente : propriétaire, #7491)

> Note numérotation : au moment de la rédaction, le dernier ADR mergé est 0023
> et le numéro 0024 est réservé par la PR #8101 (hygiène schéma). Si 0024
> reste libre au merge, ce fichier peut être renuméroté (même protocole que
> l'ADR-0023).

## Contexte

L'audit backend 2026-09-19 (issue #7655, point 3) relève : « Tokens Sanctum
valides 30 jours (`config/sanctum.php`, `SANCTUM_TOKEN_EXPIRATION=43200`)
sans rotation, pour un SaaS paie/PII. → Réduire drastiquement (heures) +
refresh, **ou justifier par écrit**. »

Deux faits que le constat d'audit ne reflétait plus :

1. **La rotation existe.** `TokenAutoRefreshMiddleware` (#5581, durci #6563)
   pivote le token sous verrou pessimiste dès qu'il entre dans la fenêtre
   `sanctum.auto_refresh_window` (24 h avant échéance), et ne livre le
   nouveau token que dans le corps JSON (jamais en header, exfiltrable via
   les logs de proxy).
2. **Le TTL de 30 jours est une décision propriétaire explicite** (#7491) :
   la durée précédente de 7 jours forçait une reconnexion hebdomadaire,
   « exactement le comportement que le propriétaire a interdit pour le
   tunnel d'acquisition ». Cette décision est verrouillée par un test garde
   (`SessionDurationTest`).

Réduire le TTL « à quelques heures » contredirait frontalement #7491 : avec
la rotation glissante, seul un utilisateur **inactif** au-delà du TTL est
déconnecté — c'est précisément la population que le propriétaire veut garder
connectée 30 jours.

## Décision

1. **Le TTL reste 30 jours glissants** (43 200 min), conformément à #7491.
   La présente ADR constitue la « justification par écrit » demandée par
   l'audit #7655.
2. **Contrôles compensatoires ajoutés** (tranche 2 de #7655) :
   - **Purge quotidienne des tokens expirés** : `sanctum:prune-expired
     --hours=24` planifiée chaque jour à 04:30 (`routes/console.php`).
     Sans purge, les hash de tokens expirés s'accumulaient indéfiniment
     dans `personal_access_tokens`.
   - **Préfixe de token `leo_`** (`sanctum.token_prefix`, défaut et
     `.env.example`) : les tokens émis deviennent détectables par le secret
     scanning (GitHub et équivalents). Rétro-compatible : le hash stocké est
     figé à la création, les tokens historiques sans préfixe restent
     valides ; seuls les nouveaux portent le marqueur.
   - Tests de verrouillage : `tests/Feature/Auth/SanctumTokenHygieneTest.php`.

## Contrôles existants (rappel, pour l'auditabilité)

- Tokens hashés au repos en DB (Sanctum, SHA-256).
- Rotation glissante atomique sous `SELECT FOR UPDATE` (#5581), livraison
  du token exclusivement dans le corps JSON (#5581/#6563).
- Verrouillage de compte après échecs de connexion (AuthService,
  `locked_until`).
- La fuite du token via le cache d'idempotence (clé `_auth`) est traitée
  séparément : #8052 (lot sécurité audit 2026-09-22).

## Conséquences

- Positives : dette d'audit soldée par écrit ; table
  `personal_access_tokens` bornée ; fuite de token détectable par scanning.
- Négatives / risques acceptés : un token volé sur un poste inactif reste
  valide jusqu'à 30 jours (risque accepté par #7491 ; mitigé par la
  détection de fuite et la possibilité de révocation serveur).
- Réversibilité : `SANCTUM_TOKEN_EXPIRATION` et `SANCTUM_TOKEN_PREFIX`
  restent pilotables par environnement sans changement de code.

## Références

- Issue #7655 (point 3), audit 2026-09-19.
- Issue #7491 (décision propriétaire, session 30 jours glissants).
- #5581 / #6563 (rotation atomique, canal de livraison du token).
- `config/sanctum.php`, `routes/console.php`,
  `tests/Feature/Auth/SanctumTokenHygieneTest.php`,
  `tests/Feature/Auth/SessionDurationTest.php`.
