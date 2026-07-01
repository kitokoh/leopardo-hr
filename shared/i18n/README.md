# i18n — Architecture multilingue Leopardo RH

## Langues supportées

| Code | Langue    | Direction | Statut     |
|------|-----------|:---------:|:----------:|
| `fr` | Français  | LTR       | ✅ défaut  |
| `ar` | العربية   | RTL       | ✅ prod    |
| `tr` | Türkçe    | LTR       | ✅ prod    |
| `en` | English   | LTR       | ✅ prod    |

---

## Source de vérité unique

```
shared/i18n/locales/
├── fr.json   ← source de vérité
├── ar.json
├── tr.json
└── en.json
```

**Ne jamais éditer** les fichiers générés directement.  
Toute modification passe par `shared/i18n/locales/*.json` puis sync.

---

## Synchronisation

```bash
# Sync tous les targets en une fois
node shared/i18n/sync/sync-backend.js   # → api/lang/*/shared.php + emails.enterprise.php
node shared/i18n/sync/sync-mobile.js    # → front/mobile_apps/leopardo_core/lib/l10n/app_*.arb
node shared/i18n/sync/sync-web.js       # → front/admin-dashboard/src/i18n/locales/*.json
                                        # → front/web/src/lib/i18n/locales/*.json
```

> Le CI `i18n-enterprise.yml` exécute ces 3 syncs et échoue si des fichiers générés n'ont pas été committés.

---

## Architecture par surface

### API Laravel (`api/`)

| Mécanisme | Détail |
|-----------|--------|
| **Middleware** | `SetLocale` — prepend sur toute la stack API |
| **Priorité** | 1. `employees.preferred_language` → 2. `companies.language` → 3. `Accept-Language` header → 4. `fr` |
| **Fichiers** | `api/lang/{fr,ar,tr,en}/*.php` |
| **Clé shared** | `api/lang/*/shared.php` + `emails.enterprise.php` (générés) |
| **Réponses** | Champ `localized_message` dans toutes les erreurs JSON |
| **Endpoint** | `GET /api/v1/i18n/catalog/{locale}` — catalog complet + ETag/cache |
| **Changement** | `PATCH /api/v1/auth/language` — persiste `preferred_language` |

### Mobile Flutter (`front/mobile_apps/`)

| Mécanisme | Détail |
|-----------|--------|
| **Localizations** | `flutter_localizations` + `AppLocalizations` générés depuis `.arb` |
| **Fichiers ARB** | `leopardo_core/lib/l10n/app_{fr,ar,tr,en}.arb` (générés par sync-mobile) |
| **Locale active** | `AppPreferences.preferredLanguage` + `isRtl` (Hive) |
| **Envoi API** | Header `Accept-Language` injecté par `ApiClient` interceptor |
| **Init** | `auth_repository._persistEmployeeContext()` après chaque login/me |
| **Changement** | `UserAuthRepository.updateProfile(preferredLanguage: ...)` → persist local + header |

### Web client Next.js (`front/web/`)

| Mécanisme | Détail |
|-----------|--------|
| **Détection** | `getPreferredLocale()` : user.language → localStorage → navigator.language |
| **Catalogue** | `src/lib/i18n/locale-catalog.ts` → importe les JSON locales |
| **Inline copy** | `src/lib/i18n.ts` → copy statique pour login/dashboard (SSR-safe) |
| **Envoi API** | Header `Accept-Language: getPreferredLocale()` dans `apiFetch` |
| **Init DOM** | `<LocaleSync />` dans layout : applique `dir` et `lang` au `<html>` |
| **Changement** | `PATCH /api/v1/auth/language` + `storePreferredLocale()` |

### Admin Dashboard Vue (`front/admin-dashboard/`)

| Mécanisme | Détail |
|-----------|--------|
| **Catalogue** | `src/i18n/locales/*.json` (générés par sync-web) |
| **Store** | `stores/locale.js` — Pinia store, persiste dans localStorage |
| **Envoi API** | Header `Accept-Language` injecté dans l'intercepteur axios |
| **Init** | `useLocaleStore()` dans `main.js` au montage |
| **Après login** | `useLocaleStore().initFromUser(userData)` dans `stores/auth.js` |
| **$t** | `app.config.globalProperties.$t(key)` disponible partout |

---

## Ajouter une nouvelle langue

1. Créer `shared/i18n/locales/{code}.json` (copier `fr.json`, traduire)
2. Ajouter `{code}` dans `Language::SUPPORTED` (`api/app/Models/Language.php`)
3. Créer `api/lang/{code}/` en copiant `api/lang/fr/` et traduisant
4. Ajouter dans `I18nCatalog::RTL_LOCALES` si nécessaire
5. Lancer `node shared/i18n/sync/sync-*.js`
6. Régénérer les ARB Flutter : `flutter gen-l10n` dans `leopardo_core`
7. Committer tous les fichiers générés

---

## Checklist avant merge

```
[ ] shared/i18n/locales/*.json — toutes les clés identiques par locale
[ ] api/lang/{fr,ar,tr,en}/*.php — même ensemble de fichiers (pas de dashboard.php manquant)
[ ] front/mobile_apps/leopardo_core/lib/l10n/app_*.arb — clés synchronisées
[ ] front/admin-dashboard/src/i18n/locales/*.json — à jour
[ ] front/web/src/lib/i18n/locales/*.json — à jour
[ ] node shared/i18n/validators/validate.js → 0 erreur
```
