# Décision — 2026-10-28 — Échecs CI préexistants sur `main` : périmètre de la mission

## Objet

Plusieurs checks sont rouges sur **tout commit de `main`**, donc sur toutes les PR ouvertes. Cette note tranche ce qui relève de la mission « implémenter les issues ouvertes » et ce qui n'en relève pas, pour éviter deux erreurs symétriques : bloquer toutes les PR sur une panne d'infrastructure, ou masquer un vrai défaut en l'appelant « flake ».

## Mesures

| Workflow | État sur `main` | Verdict |
|---|---|---|
| `desktop-ci.yml` (Desktop CI — Windows / macOS) | **8 échecs / 8 runs** (2026-10-22 → 2026-10-28) | Préexistant |
| `web-ci.yml` (Web E2E Playwright) | **6 échecs / 6 runs** | Préexistant, **cause identifiée et corrigée** (PR #8980) |

### Desktop CI / macOS — `Xcode SPM` : préexistant, hors périmètre

`flutter analyze` est vert après le correctif de #8754 (2 `unused_result` + 2 `unintended_html_in_doc_comment` sur `leopardo_manager`). L'échec restant est la **résolution des dépendances SPM par Xcode**, et il touche **les 6 apps** (`leopardo_employee`, `platform_admin`, `accounting`, `hr`, `manager`, `marketing`), sur **8 runs consécutifs** de `main` — y compris sur des commits qui ne touchent aucun fichier Dart.

**Décision : ce n'est pas un défaut de contenu, c'est une panne d'environnement de build macOS.** Aucune PR de cette mission n'est bloquée dessus, et aucun correctif n'est tenté à l'aveugle dans le code applicatif : modifier six `Package.swift`/`Podfile` pour un symptôme non reproduit localement (pas de runner macOS disponible ici) produirait un correctif non vérifiable. **Action de suivi** : ce point doit être tracé comme incident CI infra et traité avec un runner macOS, pas depuis une PR de contenu.

### Web E2E Playwright — cause racine trouvée

Les 11 échecs ne sont pas homogènes : 2 fichiers concentrent l'essentiel (`accounting-dashboard-golden.spec.js`, `companies-list-quick-actions.spec.js`), et 4 échecs portent explicitement sur des **libellés de navigation** (`Équipe plateforme`, `Sidebar — source de vérité unique`, `modules d'entreprise cliente regroupés`).

Pour ces 4 derniers, la cause est **prouvée** : le bloc `navigation` a perdu **34 clés** lors du merge `8890f8ba3` (72 → 39 dans `shared/i18n/locales/*.json`), et `sync-web.js` a propagé la perte au catalogue admin. `translate()` retourne `''` → entrées de menu vides → les assertions `getByText(...)` échouent. **Corrigé par la PR #8980** (4 locales, 0 clé manquante).

**Décision : les échecs restants de `accounting-dashboard-golden` et `companies-list-quick-actions` sont hors périmètre de cette PR.** Ils ne dépendent pas de l'i18n et n'ont pas été diagnostiqués ; les inclure reviendrait à élargir une PR de restauration de catalogues à du debug fonctionnel non borné. Ils sont listés comme dette à traiter séparément.

## Règle appliquée pour la suite de la mission

Un check rouge n'est **accepté comme préexistant** que si l'on peut montrer qu'il échoue **sur `main`**, sur des commits **sans rapport** avec les fichiers modifiés. Sinon il est traité. Cette règle a permis de distinguer, dans cette session :

- un échec **de contenu** (`flutter analyze`, 4 vraies violations) → corrigé ;
- un échec **de contenu** (Playwright / libellés) → corrigé ;
- un échec **d'environnement** (macOS SPM) → documenté, non bloquant ;
- du bruit **local** (79 échecs `tests/Feature/Travel` dus à des contraintes CHECK absentes du dump de schéma, contre 90 sur `main`) → écarté comme artefact, la CI restant l'arbitre.
