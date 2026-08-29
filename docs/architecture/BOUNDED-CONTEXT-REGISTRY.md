# Registre des bounded contexts — Leopardo HR

> **MAT-001 (issue #5859)** — Registre automatisé des bounded contexts.
> Source de vérité machine-readable : [`dev-hub/governance/bounded-context-registry.json`](../../dev-hub/governance/bounded-context-registry.json)
> Garde CI : [`dev-hub/tools/check-bounded-context-registry.sh`](../../dev-hub/tools/check-bounded-context-registry.sh)

## Objectif

Un bounded context (BC) est une frontière métier ET technique : vocabulaire,
modèles, migrations Laravel, routes, Policies, événements, tests et propriétaire.
Le registre canonicalise ces informations **en JSON vérifiable** pour que tout
nouveau module, chemin ou propriétaire soit déclaré — et que l'oubli fasse
échouer la CI avec un message actionnable.

Référence humaine de gouvernance : `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md`
(plan d'exécution agents, 23 BC numérotés BC-01 → BC-23).

## Structure du registre

```jsonc
{
  "_meta": { "...": "métadonnées du registre" },
  "_shared_exceptions": [
    { "path": "api/app/Core/Http", "reason": "infrastructure partagée, aucun BC propriétaire" }
  ],
  "bounded_contexts": [
    {
      "code": "BC-01",
      "name": "PLATFORM",
      "context": "Platform Core",
      "responsibility": "Catalogue, modules, provisioning, ...",
      "owner": "Agent 01 — BC-PLATFORM",
      "priority": "P0",
      "status": "active",                       // active | planned
      "paths": [
        { "path": "api/app/Modules/Platform", "status": "active" },
        { "path": "api/app/Modules/Onboarding", "status": "active" }
      ],
      "routes": ["api/routes/modules/..."],     // fichiers 100 % possédés
      "migrations": ["api/database/migrations/tenant"],
      "events": ["App\\Modules\\Platform\\Domain\\Events"],
      "dependencies": ["BC-02", "BC-03"]
    }
  ]
}
```

## Règles vérifiées par le guard (MAT-001)

1. **Structure** : JSON valide, champs obligatoires (`code`, `name`,
   `responsibility`, `owner`, `priority`, `status`, `paths`), codes uniques.
2. **Dépendances** : chaque code de `dependencies` existe dans le registre.
3. **Chemins** : `status=active` → le répertoire doit exister ; `status=planned`
   → toléré (verticale non encore créée).
4. **Couverture** : tout répertoire métier présent (`api/app/Modules/*`,
   `api/app/Solutions/*`, `api/app/Core/{Auth,Tenant,Feature}`,
   `api/app/Contracts/*`, `api/app/Policies`, `api/app/Jobs`, `api/app/AI`)
   doit être déclaré par au moins un BC. Un nouveau module non déclaré fait
   échouer la CI.
5. **CODEOWNERS** : tout chemin actif a une ligne CODEOWNERS dédiée ; toute
   ligne CODEOWNERS Modules/Core/Contracts/AI/Jobs/Policies correspond à un
   chemin actif du registre (hors `_shared_exceptions`).
6. **Routes & migrations** : les fichiers/répertoires déclarés existent.

## Comment mettre à jour

- **Nouveau module métier** : ajouter une entrée BC (ou un chemin) dans
  `bounded-context-registry.json` + la ligne CODEOWNERS correspondante, puis
  lancer `bash dev-hub/tools/check-bounded-context-registry.sh`.
- **Nouvelle verticale (ex. FuelStation)** : entrée `status=planned` avec le
  chemin cible ; passer à `active` quand le répertoire est créé.
- **Infrastructure partagée** : ajouter le chemin à `_shared_exceptions` avec
  une raison explicite (pas de BC propriétaire unique).

## Validation locale

```bash
bash dev-hub/tools/check-bounded-context-registry.sh
node --test dev-hub/tools/tests/check-bounded-context-registry.test.mjs
```

## Périmètre & non-régression

Le registre est de la gouvernance de plateforme : il ne modifie aucun module
métier, aucune route, aucune migration. Il ne touche pas au CRM commercial.
Les PRs de maturité doivent rester courtes et référencer leur issue avec
`Closes #N`.
