# Constitution Leopardo HR (miroir opérationnel)

> **Miroir de la loi fondamentale** : copie fidèle de `.specify/constitution.md`
> (version 1.0.0, ratifiée 2026-08-14), maintenue pour les runs
> `/speckit-converge` et `/speckit-implement` qui lisent ce fichier.
> **Toute modification se fait dans la source `.specify/constitution.md`**, puis ce
> miroir est resynchronisé (garde : docs/2129-converge-constitution).

# Leopardo HR Constitution

> Ce document est la loi fondamentale du projet. Il prime sur tout autre document.
> Tout agent IA doit le lire avant de commencer la moindre tâche.
> Pour le contexte opérationnel complet : `AGENTS.md`.

---
# Leopardo HR Constitution

> Ce document est la loi fondamentale du projet. Il prime sur tout autre document.
> Tout agent IA doit le lire avant de commencer la moindre tâche.
> Pour le contexte opérationnel complet : `AGENTS.md`.

---

## I. Spec-First — NON NÉGOCIABLE

**Toute feature, module ou composant significatif commence par une spec, pas par du code.**

- Avant d'implémenter, créer une spec avec `/speckit-specify`
- Avant de planifier, lancer `/speckit-clarify` si le besoin est ambigu
- Avant de coder, lancer `/speckit-analyze` pour détecter les dépendances manquantes
- Une spec = un PR maximum. Deux agents ne peuvent pas implémenter la même spec.
- **Auto-assignation obligatoire** : avant de travailler sur une issue GitHub, s'assigner dessus via `gh issue edit <number> --add-assignee @me`
- **Marker branch (protocole anti-doublon #2400)** : dès le self-assign, pousser immédiatement la branche `fix/<issue>-<slug>` (commit vide de claim) — le nom de branche EST le lock. Avant de coder, vérifier TOUTES les branches contenant le numéro d'issue (`gh api repos/.../branches | grep <issue>`) et les PRs ouvertes, pas seulement les assignees. Une seule branche par issue ; toute PR dupliquée est fermée avec renvoi vers la PR canonique.

## II. Multi-Tenant PostgreSQL — INVIOLABLE

**Toute donnée appartient à un tenant. Aucune fuite cross-tenant n'est acceptable.**

- Chaque table tenant porte `company_id` — jamais nullable pour les données métier
- Toutes les requêtes Eloquent scopées : `->where('company_id', $companyId)`
- `search_path` PostgreSQL géré via `AbstractTenantModel` — ne jamais contourner
- Toute nouvelle migration tenant dans `database/migrations/tenant/` (pas `public/`)
- Tout nouveau endpoint paie doit avoir un test `PayrollTenantIsolationTest` correspondant : `assert 404 cross-tenant`
- Nommer les colonnes avec `Schema::table($table)` qualifié par schéma dans les migrations

## III. Conformité Paie — CALCULÉ À LA MAIN

**Tout calcul de paie doit être validé par un golden test calculé manuellement.**

- Tout algorithme paie porte une référence légale dans un commentaire PHP : `// CGI art. 68`
- Tout taux fiscal ou social provient d'un document `docs/payroll/{PAYS}_COMPLIANCE.md`
- Aucun taux codé en dur sans `confidenceLevel()` documenté : `'pilot'` ou `'production'`
- `confidenceLevel = 'production'` requiert validation par un expert-comptable local (mention dans le PR)
- Minimum 3 golden tests calculés à la main par pays, couvrant SMIG, cadre moyen, haut salaire
- Tout changement de taux = mise à jour simultanée compliance doc + golden test + CHANGELOG

### Règle plafonds cotisations
```php
// TOUJOURS utiliser computeContribution() — JAMAIS de calcul inline
$amount = $this->computeContribution($gross, 'CODE', $defaultRate, $defaultCap);
```

### Règle abattements frais pro
```php
// Déclarer dans professionalExpensesDeduction() — JAMAIS dans calculateIncomeTax() inline
public function professionalExpensesDeduction(): array { return ['rate' => 30.0, 'cap' => 4200000.0]; }
```

## IV. Qualité & Tests — OBLIGATOIRE

**PHPStan strict (level 8) vert = condition sine qua non de merge.**

- PHPStan strict `[OK] No errors` avant tout PR
- Coverage module Payroll ≥ 80 % (gate bloquante)
- Coverage backend global ≥ 65 %
- Tests écrits **avant** l'implémentation pour toute logique métier
- Pattern `PendingCommand` : toujours appeler `->run()` avant les assertions DB
- Tests de régression cross-tenant sur tout nouvel endpoint sensible

### Stack de validation
```bash
cd api
vendor/bin/phpstan analyse --configuration phpstan-strict.neon  # level 8
vendor/bin/pint --test                                           # formatting
php artisan test --filter=PayrollTenantIsolation                # isolation
```

## V. Sécurité & RGPD — NON NÉGOCIABLE

**Zéro secret dans le code. Zéro donnée sensible en clair au repos.**

- Secrets via Pulumi ESC ou `.env` — jamais dans le code ni les rapports d'audit
- Données paie chiffrées au repos via `SensitiveDataEncryptor`
- Audit trail immuable pour toute modification paie : `AuditLog::create()`
- `PayrollAnomalyService` en lecture seule — aucune modification automatique de la paie
- Tout endpoint admin protégé par Policy Laravel — jamais de garde inline
- RGPD : toute collecte de donnée PII nécessite une entrée dans `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`

## VI. Architecture DDD — STRUCTURE STRICTE

**Le monolithe modulaire DDD est la loi. Aucun couplage transversal non documenté.**

```
api/app/Modules/{Module}/
  Application/   — Actions, DTOs (orchestration)
  Domain/        — Models, Contracts, Exceptions (règles métier)
  Infrastructure/ — Services, Repositories (implémentation)
  Interfaces/    — Api/V1/Controllers, Requests, Resources (HTTP)
```

- Un module ne dépend jamais directement d'un autre module via injection (passer par contrats)
- `Module Structure Validator` vert = structure DDD respectée
- Nouveaux modules : spec dans `docs/specifications/` + validation propriétaire avant tout code

## VII. Gouvernance Git & CI — DISCIPLINE

**Un commit = une raison. Une PR = une issue. CI verte = merge autorisé.**

- Branche : `feat/<numero>-slug`, `fix/<numero>-slug`, `docs/<numero>-slug`
- PR title : `feat(module): description courte (Closes #numero)`
- Chaque PR contient `Closes #<numero>` pour fermeture automatique
- CHANGELOG.md mis à jour dans chaque PR avec entrée sous `## [Unreleased]`
- Ne jamais pusher directement sur `main`
- Supprimer la branche après merge
- **Anti-doublon** : vérifier `gh issue list --assignee @me` ET toutes les branches contenant le numéro d'issue (protocole #2400) avant de démarrer. Si une spec/branche existe déjà pour cet objectif, contribuer dessus — ne pas créer une deuxième PR. Une PR dupliquée sur une même issue est fermée avec commentaire de renvoi.

### Checks requis (branch protection main)
| Check | Seuil |
|-------|-------|
| Backend Coverage | ≥ 65 % global, ≥ 80 % Payroll |
| PHPStan Strict level 8 | 0 erreur |
| Module Structure Validator | pass |
| Frontend ESLint + TypeScript | 0 erreur |
| actionlint + shellcheck | pass |

## VIII. Pays & Conformité Régionale — PRESETS

**Chaque pays a un niveau de maturité déclaré. Ne pas mentir sur `confidenceLevel`.**

| Zone | Pays | Statut cible |
|------|------|-------------|
| Maghreb | DZ (Algérie) | `production` (expert validé) |
| Maghreb | MA, TN | `pilot` |
| CEMAC | CM | `pilot` (IRPP + CNPS CGI 2024) |
| CEMAC | GA, CG | `pilot` |
| CEMAC | CF, TD, GQ | `placeholder` |
| CEDEAO | CI, SN | `pilot` |
| CEDEAO | BF, ML | `pilot` |
| CEDEAO | TG, BJ, NE | `placeholder` |

> **Statut RÉEL (source de vérité runtime)** : ce tableau est la **cible**
> produit ; l'état effectif par pays vit dans
> `docs/payroll/VALIDATION_EXPERTE.md` et dans `confidenceLevel()` du code.
> Aucun pays ne passe `pilot` → `production` sans fiche de validation experte
> **signée** (issue #1904) — DZ/FR sont cibles `production` mais restent
> `pilot` dans le code tant que la fiche n'est pas signée (SN : #1912).

Règle : tout PR qui fait passer un pays de `placeholder` → `pilot` doit :
1. Créer `docs/payroll/{PAYS}_COMPLIANCE.md`
2. Implémenter les règles dans la classe `PayrollRules`
3. Fournir ≥ 6 golden tests calculés à la main

## IX. Performance & Scalabilité

- Clôture mensuelle 10 000 employés < 30 min (jobs async, queue `payroll`)
- Cache Redis sur jours fériés et barèmes fiscaux (TTL 24h)
- N+1 interdit — utiliser `->with()` sur toute relation utilisée dans une boucle
- Index DB obligatoire sur `(company_id, employee_id)` pour toute table tenant

## X. Produit — Vision Non Négociable

**Leopardo HR est un Company OS mobile-first pour PME terrain (5–250 employés).**

- Mobile employee (`leopardo_employee`) = app prioritaire — builds toujours verts
- Paie DZ = wedge commercial — conformité maximale avant expansion
- Multi-tenant shared PostgreSQL (`search_path`) — isolation physique en phase 3
- Open-core : fonctionnalités périphériques en `peripheral`, noyau toujours profond

---

## Gouvernance

Cette Constitution prime sur `AGENTS.md`, `CONVENTIONS.md`, et tout autre document.
Tout amendement nécessite une PR dédiée, un motif documenté, et une migration plan.
Les presets Spec Kit (`.specify/presets/`) étendent cette Constitution sans la contredire.

**Version**: 1.0.0 | **Ratifiée**: 2026-08-14 | **Projet**: kitokoh/leopardo-hr
