## Plan technique
1. Script de comparaison : extraire chemins+verbes de `openapi.yaml` (parsing YAML) et de `routes/**/*.php` (regex), produire la liste des écarts.
2. Par écart : corriger la spec (chemin/verbe) pour matcher le code, ou ajouter le code si la spec documente un vrai contrat (`bank-exports` index/store).
3. Ajouter `BankExportController@index` + `@store` (+ FormRequest, pagination, tests Feature) et routes `GET/POST /bank-exports` (super-admin ou tenant selon le contexte existant du contrôleur).
4. Vérifier `openapi-ci.yml` et les tests `OpenApiDocsTest`/`FrontendApiContractTest`.
5. Ne pas toucher aux routes existantes qui fonctionnent. CHANGELOG.
