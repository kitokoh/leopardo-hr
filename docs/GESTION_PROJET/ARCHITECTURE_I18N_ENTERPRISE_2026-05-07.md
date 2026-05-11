# Enterprise Multilingual Infrastructure - Leopardo RH

## 1. Architecture finale

Le systeme multilingue passe d une logique dispersee a une logique centralisee:

- `shared/i18n/locales/*.json` devient la source de verite unique
- `shared/i18n/glossary/glossary.json` verrouille la terminologie metier
- `shared/i18n/validators/validate.js` bloque les catalogues invalides
- `shared/i18n/sync/*.js` genere les sorties backend, web et mobile
- `api` expose un endpoint de distribution distante avec version et checksum
- `mobile` prepare un cache hors-ligne pour recevoir des mises a jour sans republication

## 2. Arborescence cible

```text
/shared
  /i18n
    /analytics
    /glossary
    /locales
    /schemas
    /sync
    /validators
    /versions
```

## 3. Source of truth

Chaque fichier locale suit ces regles:

- structure hierarchique, jamais de `loginTitle`
- metadonnees obligatoires: `_version`, `_updated_at`, `_locale`
- placeholders coherents d une langue a l autre
- textes emails versionnes comme le reste

## 4. Synchronisation cible

- `sync-backend.js`
  - genere `api/lang/{locale}/shared.php`
  - genere `api/lang/{locale}/emails.enterprise.php`
- `sync-web.js`
  - genere `front/admin-dashboard/src/i18n/locales/{locale}.json`
- `sync-mobile.js`
  - genere `front/mobile/lib/l10n/app_{locale}.arb`

## 5. Remote translation updates

Endpoint backend:

- `GET /api/v1/i18n/catalog`
- `GET /api/v1/i18n/catalog/{locale}`

Contrat:

- normalisation des variantes (`fr-CA` -> `fr`, `en-GB` -> `en`)
- retour du `version`, `checksum`, `rtl`, `fallback_locale`
- `ETag` pour court-circuiter les re-downloads
- `Cache-Control` public

Strategie mobile:

- dernier catalogue stocke dans Hive
- checksum stocke avec le payload
- fallback sur catalogue embarque si le remote echoue
- remote sync non bloquant au demarrage

## 6. Support RTL

- backend: normalisation des locales et propagation via `Accept-Language`
- web: fichiers locaux prets + futur branchement UI par `dir=rtl`
- mobile: locales variantes et directionnalite arabe preparees
- validation: detection de mojibake et controle minimal de script arabe

## 7. Emails multilingues

Les templates de base prepares dans le catalogue:

- invitation
- reset_password
- payroll_ready
- absence_approved
- absence_rejected

## 8. Analytics

Voir `shared/i18n/analytics/translation-events.md`.

## 9. CI/CD

Workflow recommande:

1. validation du catalogue
2. generation des artefacts
3. verification qu aucun diff non committe n apparait
4. blocage du merge si i18n invalide

## 10. Plan de migration backend

1. conserver l existant Laravel
2. migrer les domaines partages vers `shared.php` et `emails.enterprise.php`
3. basculer progressivement les appels `__()` communs
4. remplacer ensuite les anciens fichiers domaine par domaine

## 11. Plan de migration web

1. brancher `front/admin-dashboard/src/i18n/index.js`
2. remplacer les literals UI critiques
3. introduire gestion `locale + dir`
4. ajouter tests RTL et snapshot

## 12. Plan de migration mobile

1. generer les ARB depuis `shared/i18n`
2. brancher le cache remote
3. migrer ecran par ecran vers les nouvelles cles
4. activer ensuite le refresh distant automatique

## 13. Priorites d implementation

1. source de verite shared
2. validation automatique
3. synchro mobile/web/backend
4. endpoint backend de distribution
5. cache mobile
6. migration UI progressive

## 14. Risques techniques

- coexistence temporaire ancien/nouveau systeme
- cles Flutter historiques non hierarchiques
- dette de literals hardcodes cote web
- divergence possible si les scripts de sync ne sont pas lances

## 15. Optimisations futures

- console interne de traduction
- remote kill-switch par version de catalogue
- screenshot diff RTL automatise
- suggestions IA sous contrainte glossary

## 16. Bonnes pratiques enterprise

- une seule source de verite
- un seul glossaire verrouille
- checksums et versioning partout
- pas de texte metier hardcode dans les nouveaux modules
- migration par surface, pas big bang

## 17. Positionnement

Cette architecture n est plus une logique MVP. Elle est faite pour une plateforme SaaS multi-tenant qui doit evoluer sur plusieurs pays, plusieurs surfaces et plusieurs modules sans perdre sa coherence terminologique.
