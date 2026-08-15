# Mini-spec — Issue #3744

## Intention
Protéger au repos les données personnelles de pointage stockées par le bridge kiosk.

## Décision
Le répertoire `desktop-bridge/data` est créé avec le mode `700`. Toute base SQLite fichier passée à `LocalStore` est normalisée en mode `600` avant et après ouverture. Les bases SQLite en mémoire utilisées par les tests (`:memory:`) restent supportées sans tentative de chmod.

## Critères d’acceptation

| Élément | Mode attendu |
|---|---:|
| `desktop-bridge/data/` | `0700` |
| `desktop-bridge/data/kiosk.db` | `0600` |
| Base `:memory:` | Compatible tests, sans erreur de chemin |
| Données existantes | Permissions durcies à la prochaine ouverture |

## Validation

Les 27 tests bridge existants passent avec `unittest`. Un contrôle isolé confirme le mode `0600` sur une base temporaire ; `git diff --check` passe également.
