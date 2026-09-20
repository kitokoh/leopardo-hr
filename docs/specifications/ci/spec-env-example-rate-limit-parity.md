# Spec Kit — Parité `.env.example` pour les rate limits de sécurité

## Contexte

Le workflow `Architecture Quality` de `main` a échoué sur le parity guard `dev-hub/tools/check-env-example-parity.sh api`. Trois clés consommées par `api/config/security.php` n’étaient pas documentées dans `api/.env.example` : `RATE_LIMIT_KIOSK_SHOW_PER_MINUTE`, `RATE_LIMIT_METRICS_PER_MINUTE` et `RATE_LIMIT_WEB_ACTIVATE_PER_MINUTE`.

## Objectif

Rétablir la parité entre les appels `env()` de la configuration et l’exemple d’environnement, sans modifier les valeurs effectives de Render ni introduire de secret.

## Critères d’acceptation

- Les trois clés figurent dans la section `config/security.php` de `api/.env.example`.
- Les valeurs documentées correspondent aux valeurs par défaut de `api/config/security.php` : `120`, `30` et `10`.
- `bash dev-hub/tools/check-env-example-parity.sh api` termine avec succès et signale `278 clés, 0 manquante`.
- `git diff --check` termine avec succès.
- Le correctif est isolé dans une branche et une PR dédiées afin de ne pas mélanger la correction de main avec la PR PHPStan #4737.

## Validation

Le parity guard a été exécuté localement avec succès. Aucun déploiement Render ni aucune variable secrète n’est modifié par ce correctif.

## Tâches

- [x] Ajouter les trois clés manquantes dans `.env.example`.
- [x] Exécuter le parity guard.
- [x] Mettre à jour le CHANGELOG.
- [ ] Créer la PR et attendre Architecture Quality sur main.
