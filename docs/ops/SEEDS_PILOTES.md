# Seeds pilotes et données synthétiques (MAT-012, #5870)

## Objectif

Semer des environnements pilotes **reproductibles** par verticale (développement,
recette, démo), **sans secret ni donnée réelle**, **idempotents**, **nettoyables**,
et **impossibles à diriger vers un tenant de production par erreur**.

## Commandes

```bash
# Semer la verticale CRM (pilotes déterministes crm-pilot-alpha/beta)
php artisan pilot:seed crm

# Nettoyer les pilotes d'une verticale
php artisan pilot:cleanup crm
php artisan pilot:cleanup crm --tenant=crm-pilot-alpha   # cible un pilote
```

`--force` autorise l'exécution hors environnement pilote/demo — **jamais** de
quoi cibler un tenant réel : la garde de slug est stricte même avec `--force`.

## Garde-fous (PilotSeedGuard)

| Garde | Comportement |
|---|---|
| Environnement `production` | refus sans `--force` explicite |
| Slug hors allowlist | refus systématique (ex. `acme-real-client` → erreur) |
| Allowlist | `crm-pilot-alpha`, `crm-pilot-beta`, `techcorp-algerie`, `pharmaplus-casablanca`, `digitalflow-tunis` |
| Idempotence | re-seed = skip de l'existant ; re-cleanup = no-op |
| Nettoyage | supprime les lignes CRM + employés + société du pilote (transaction) |

## Ajouter une verticale

1. Créer le seeder (pattern `CrmPilotSeeder` : slugs déterministes, skip
   réentrant, zéro secret) ;
2. l'enregistrer dans `SeedPilotCommand::VERTICALS` et
   `CleanupPilotCommand::VERTICALS` (seeder + slugs + tables tenant) ;
3. ajouter les slugs à `PilotSeedGuard::ALLOWED_PILOT_SLUGS` ;
4. couvrir par tests (création, idempotence, nettoyage, gardes).

## Rollback

- Retirer les commandes + `App\Core\Seed\PilotSeedGuard` ; les données déjà
  semées restent nettoyables via `pilot:cleanup` ou manuellement.
