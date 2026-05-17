# Good First Issues — Leopardo RH

Liste de 10 "good first issues" pour les contributeurs debutants. Chaque issue est independante et peut etre realisee sans connaissance approfondie de l'architecture.

---

## 1. Ajouter la validation du format IBAN dans le formulaire employe
**Labels** : `good first issue`, `backend`, `validation`
**Fichier** : `api/app/Http/Controllers/Api/V1/EmployeeController.php`
**Description** : Ajouter une regle de validation Laravel custom `iban` qui verifie le format ISO 13616 (longueur par pays, checksum mod 97). Actuellement le champ `iban` accepte n'importe quelle chaine.
**Critere** : test unitaire qui valide FR, DZ, MA et rejette les formats invalides.

## 2. Traduire les messages d'erreur API en arabe
**Labels** : `good first issue`, `i18n`, `backend`
**Fichier** : `api/lang/ar/`
**Description** : Creer le dossier `ar/` dans `api/lang/` avec les fichiers `validation.php`, `auth.php` et `passwords.php` traduits en arabe. Les messages existants sont en francais (`fr/`) et anglais (`en/`).
**Critere** : les messages de validation retournent la bonne traduction quand `Accept-Language: ar`.

## 3. Ajouter un badge "derniere release" dans le README
**Labels** : `good first issue`, `documentation`
**Fichier** : `README.md`
**Description** : Ajouter les badges GitHub (derniere release, licence, CI status, coverage) en haut du README avec les liens vers les pages correspondantes.
**Critere** : badges visibles et liens fonctionnels.

## 4. Dark mode pour le composant StatusBadge admin
**Labels** : `good first issue`, `frontend`, `ui`
**Fichier** : `front/admin-dashboard/src/components/common/StatusBadge.vue`
**Description** : Ajouter les classes Tailwind `dark:` pour que le composant StatusBadge s'affiche correctement en mode sombre. Les couleurs actuelles ne sont definies que pour le mode clair.
**Critere** : le badge reste lisible avec `class="dark"` sur le `<html>`.

## 5. Ajouter l'export PDF pour les rapports RH
**Labels** : `good first issue`, `backend`, `feature`
**Fichier** : `api/app/Http/Controllers/Api/V1/HrReportController.php`
**Description** : Ajouter un parametre `?format=pdf` aux endpoints rapports RH (`headcount`, `turnover`, `absenteeism`) qui genere un PDF simple avec les donnees du rapport. Utiliser `barryvdh/laravel-dompdf` deja installe.
**Critere** : le PDF se telecharge avec les donnees correctes.

## 6. Limiter la taille des fichiers uploades dans l'import CSV
**Labels** : `good first issue`, `backend`, `security`
**Fichier** : `api/app/Http/Controllers/Api/V1/EmployeeController.php`
**Description** : L'import CSV employes n'a pas de limite de taille. Ajouter une validation `max:2048` (2 Mo) sur le fichier et un message d'erreur clair.
**Critere** : un fichier > 2 Mo est rejete avec message d'erreur 422.

## 7. Ajouter le tri par colonne dans la liste des webhooks
**Labels** : `good first issue`, `frontend`, `ui`
**Fichier** : `front/admin-dashboard/src/views/webhooks/WebhooksView.vue`
**Description** : Verifier que les colonnes du DataTable webhooks ont `sortable: true` et que le tri fonctionne correctement pour `url`, `status` et `created_at`.
**Critere** : clic sur l'en-tete de colonne trie les donnees.

## 8. Ajouter un favicon SVG pour le site vitrine
**Labels** : `good first issue`, `frontend`, `design`
**Fichier** : `front/web/src/app/favicon.ico`
**Description** : Remplacer le favicon par defaut Next.js par un SVG du logo Leopardo (lettre "L" sur fond emeraude). Ajouter aussi les meta tags `apple-touch-icon` dans le layout.
**Critere** : le favicon est visible dans l'onglet navigateur.

## 9. Documenter les codes d'erreur API
**Labels** : `good first issue`, `documentation`, `api`
**Fichier** : `docs/api/ERROR_CODES.md`
**Description** : Creer un fichier documentant tous les codes d'erreur API personnalises (ex: `RATE_LIMIT_EXCEEDED`, `UNSUPPORTED_API_VERSION`, `TENANT_NOT_FOUND`). Lister le code HTTP, le code erreur, le message et un exemple de reponse.
**Critere** : chaque code d'erreur retourne par l'API est documente.

## 10. Ajouter un test pour l'endpoint health ready
**Labels** : `good first issue`, `backend`, `testing`
**Fichier** : `api/tests/Feature/HealthCheckTest.php`
**Description** : L'endpoint `GET /api/v1/health/ready` existe mais n'a pas de test Feature dedie. Ecrire un test qui verifie le status 200, la structure JSON (`status`, `checks`) et que les checks critiques (database, cache) sont presents.
**Critere** : test passe dans la CI GitHub Actions.
