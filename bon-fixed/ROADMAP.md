# BON — Feuille de route & suivi qualité

> Dernière mise à jour : version 3 (post-audit v1 + v2)

---

## ✅ Corrections appliquées dans cette version

### 🔴 Bugs critiques (crash garanti corrigés)

| # | Fichier | Problème | Correction |
|---|---------|----------|------------|
| 1 | `libs/scraper.py` | `record_publication()` et `record_error()` passaient des strings (`account_name`, `group_url`) là où `database.py` attendait des entiers (`account_id`, `group_id`) → crash SQLite | Les méthodes DB acceptent maintenant `Union[int, str]` et font la résolution id/nom en interne |
| 2 | `libs/scraper.py` | `health_manager.record_success("key")` — argument `used_selector` manquant (TypeError) | Signature rendue optionnelle avec default `""` dans `SelectorHealthManager` |
| 3 | `libs/scraper.py` | `health_manager.record_failure("key")` — arguments `reason` et `tried_selectors` manquants (TypeError) | Idem — les deux rendus optionnels |
| 4 | `libs/database.py` | `record_account_block()` insérait dans la colonne `blocked_at` qui n'existe pas dans le schéma (la colonne s'appelle `started_at`) → OperationalError | Correction de la colonne `blocked_at` → `started_at` |
| 5 | `libs/database.py` | `can_account_post(account_id: int)` recevait un string → `get_account_by_id()` retournait None → tous les posts bloqués silencieusement | Méthode accepte maintenant `Union[int, str]` |
| 6 | `libs/error_handlers.py` | Détection CAPTCHA toujours vraie : `frame_locator()` retourne toujours un objet Locator (jamais None) → `if captcha_frame` toujours True | Remplacé par `page.locator("iframe[src*='recaptcha']").count() > 0` |

### 🔴 Sécurité critique

| # | Fichier | Problème | Correction |
|---|---------|----------|------------|
| 7 | `libs/scraper.py` + `libs/database.py` | `scraper.close()` sérialisait les cookies Facebook (storage_state) et les stockait en clair dans la colonne `storage_state` de SQLite → exposition de tous les comptes si `bon.db` est volé ou partagé | Suppression de la colonne `storage_state` du schéma. Les cookies restent exclusivement dans les fichiers `{session}_state.json` sur disque |

### 🟠 Bugs architecturaux

| # | Fichier | Problème | Correction |
|---|---------|----------|------------|
| 8 | `automation/__init__.py` | Import inconditionnel de `SeleniumEngine` crashait si Selenium n'est pas installé (supprimé des dépendances dans `requirements.txt`) | Import conditionnel dans un bloc `try/except ImportError` |
| 9 | `libs/database.py` | Singleton `get_database()` non thread-safe — double initialisation possible en multi-thread | Ajout de `threading.Lock` avec double-checked locking |
| 10 | `automation/selector_health.py` | Même problème de singleton non thread-safe | Même correction |
| 11 | `libs/database.py` | Chaque méthode ouvrait sa propre connexion SQLite via `@contextmanager` sans verrou → écritures concurrentes possibles | Refactoring vers méthodes privées `_exec()`, `_query()`, `_query_one()` avec `self._lock` |

### 🟡 Bugs mineurs

| # | Fichier | Problème | Correction |
|---|---------|----------|------------|
| 12 | `libs/scraper.py` | Condition `elif not images` après `if images` — branche toujours vraie (redondant) | Remplacé par `else` |
| 13 | `libs/scraper.py` (marketplace) | `page.set_input_files("input[type='file']", ...)` — sélecteur trop générique, peut cibler le mauvais input | Remplacé par `self.selectors.get_candidates("add_image")[0]` |

### 🟡 Qualité & robustesse

| # | Fichier | Amélioration |
|---|---------|-------------|
| 14 | `.gitignore` | Ajout de `*.db`, `*.jsonl`, `*.pid`, `scraper.log`, `*_state.json`, `data/anti_block_state.json`, `logs/errors/`, `logs/screenshots/` |
| 15 | `libs/database.py` | Ajout de `PRAGMA journal_mode=WAL` (performance SQLite) et `PRAGMA foreign_keys=ON` (intégrité référentielle) |
| 16 | `libs/database.py` | `record_publication()` tronque `post_content` à 500 chars (évite des entrées DB volumineuses) |

---

## ⚠️ Problèmes connus non corrigés dans cette version

### 🔴 Priorité haute

#### P1 — Trois systèmes de rate-limiting non synchronisés
**Fichiers** : `libs/timing_humanizer.py`, `libs/database.py`, `automation/anti_block.py`

Ces trois composants implémentent chacun leur propre logique de contrôle de fréquence sur des sources de données différentes (JSON de session, SQLite, JSON anti-block), sans jamais se consulter :

- `timing_humanizer` → lit `last_run_ts` dans le JSON de session
- `database.can_account_post()` → lit `cooldown_until` dans SQLite
- `AntiBlockManager.can_post()` → lit `anti_block_state.json`

`AntiBlockManager` n'est d'ailleurs jamais appelé dans le flux principal (`scraper.py` ne l'importe pas).

**Correction recommandée** : désigner une seule source de vérité (la DB SQLite), migrer toute la logique de cooldown dans `database.can_account_post()`, et supprimer les doublons dans `timing_humanizer`. `AntiBlockManager` peut rester comme outil de monitoring mais ne doit pas être une source d'autorité séparée.

#### P2 — Package `automation/` partiellement mort à l'exécution
**Fichiers** : `automation/engine.py`, `automation/playwright_engine.py`

`__main__.py` et `scraper.py` importent directement `libs/playwright_engine.py`. `AutomationEngine` et `PlaywrightWrapper` (dans `automation/`) ne sont jamais instanciés dans le flux réel. Ce package est documenté comme "interface unifiée" mais n'est pas utilisé comme tel.

**Correction recommandée** : soit `scraper.py` utilise `AutomationEngine` comme point d'entrée unique, soit le dossier `automation/engine.py` et `automation/playwright_engine.py` sont supprimés pour éviter la confusion. La décision architecturale doit être tranchée explicitement.

### 🟠 Priorité moyenne

#### P3 — `_apply_theme()` : logique de remplacement fragile
**Fichier** : `libs/scraper.py`

```python
sel = candidates[0].replace("index", str(theme_idx))
```

Cette ligne suppose que le sélecteur contient le mot littéral `"index"` comme placeholder. Si `selectors.json` est modifié et que le placeholder change de nom ou disparaît, le remplacement échoue silencieusement (le sélecteur reste invalide sans erreur).

**Correction recommandée** : utiliser un format template explicite dans `selectors.json` (ex: `{theme_index}`) et appliquer `str.format(theme_index=theme_idx)` avec un try/except qui log l'anomalie.

#### P4 — `automation/selector_tester.py` : erreur Python dans le code
**Fichier** : `automation/selector_tester.py`, ligne ~100

```python
text = element.evaluate("el => el.innerText[:200]")
```

JavaScript ne supporte pas la syntaxe de slicing Python `[:200]`. Ce code produira une `SyntaxError` JavaScript à l'exécution.

**Correction** : `element.evaluate("el => el.innerText.substring(0, 200)")`

#### P5 — `logs/bon.db` et `logs/activity.jsonl` présents dans l'archive
Ces fichiers de données runtime sont embarqués dans le zip/dépôt. Ils peuvent contenir des données de session ou de debug sensibles.

**Correction** : s'assurer que `.gitignore` est bien pris en compte avant chaque archivage. Utiliser `git clean -xdf` ou un script de packaging qui exclut explicitement `logs/`.

### 🟡 Priorité basse

#### P6 — Fichiers vestiges à la racine
`__post_in_groups__.py`, `__post_in_groupsx__.py`, `__save_groups__.py` sont des scripts standalone de l'ancienne architecture. Ils ne sont référencés nulle part dans le code actuel et utilisent potentiellement d'anciennes APIs.

**Action** : archiver dans un dossier `legacy/` ou supprimer après vérification qu'ils ne sont pas utilisés en dehors du projet (ex: scripts cron externes).

#### P7 — `env1/` dans l'archive de distribution
Le virtualenv complet (~35MB) est inclus dans le zip. C'est une mauvaise pratique : il contient des binaires compilés spécifiques à une plateforme, des fichiers de licence tiers, et gonfle inutilement la taille du projet.

**Action** : exclure `env1/` et `env/` du `.gitignore` (déjà fait dans cette version) et du processus d'archivage.

#### P8 — Données personnelles dans `data.json` / `data1.json`
Les tests unitaires dans `test_smoke.py` (classe `TestDataFiles`) vérifient déjà l'absence de chemins Windows personnels. Cependant, le contenu réel des fichiers n'a pas été audité dans cette révision.

**Action** : passer `python -m pytest tests/test_smoke.py::TestDataFiles -v` et corriger toute assertion échouée.

---

## 🗺️ Prochaines étapes recommandées (roadmap fonctionnelle)

| Priorité | Fonctionnalité | Description |
|----------|---------------|-------------|
| 🔴 | Unification rate-limiting | Fusionner timing_humanizer + DB cooldown en une seule source de vérité |
| 🔴 | Décision architecture automation/ | Choisir entre garder `AutomationEngine` comme façade unique ou supprimer le doublon |
| 🟠 | Tests d'intégration | Ajouter des tests qui mockent Playwright et vérifient le flux complet sans navigateur |
| 🟠 | Chiffrement des sessions | Si les fichiers `_state.json` sont sur un serveur partagé, chiffrer avec `pycryptodome` |
| 🟠 | Dashboard PyQt | Connecter `tools/dashboard.py` à `get_database()` pour un affichage temps réel |
| 🟡 | Rotation de logs | `activity.jsonl` croît indéfiniment — ajouter une rotation (ex: `logging.handlers.RotatingFileHandler`) |
| 🟡 | CDN sélecteurs | Configurer `BON_SELECTORS_CDN_URL` et héberger `selectors.json` pour mise à jour auto |
| 🟡 | Multi-session parallèle | Permettre le lancement de plusieurs sessions simultanées via ThreadPoolExecutor |
| 🟡 | Warmup progressif | Implémenter la logique `warmup_completed` déjà présente dans le schéma DB |

---

## 📁 Fichiers modifiés dans cette version

```
libs/
  database.py          ← Refactorisé (signatures, sécurité, thread-safety)
  scraper.py           ← Corrigé (appels DB, health_manager, CAPTCHA)
  error_handlers.py    ← Corrigé (détection CAPTCHA)

automation/
  __init__.py          ← Corrigé (Selenium optionnel)
  selector_health.py   ← Corrigé (signatures + thread-safety)

.gitignore             ← Complété
ROADMAP.md             ← Ce fichier
```

## 📁 Fichiers inchangés

```
libs/playwright_engine.py
libs/selector_registry.py
libs/session_manager.py
libs/config_manager.py
libs/config_validator.py
libs/timing_humanizer.py
libs/log_emitter.py
automation/engine.py
automation/anti_block.py
automation/playwright_engine.py
automation/selenium_engine.py
automation/selector_tester.py
__main__.py
requirements.txt
tests/test_smoke.py
config/selectors.json
```
