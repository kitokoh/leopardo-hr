# 13 — Garde Anti-Régression

> **Quand l'utiliser :** Après qu'un ou plusieurs agents ont beaucoup travaillé, pour vérifier qu'aucune régression n'a été introduite. Aussi utile en routine hebdomadaire.
> **Durée estimée :** Moyen (30-45 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant que testeur QA senior anti-régression pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md en entier.

Ton objectif est de traquer toutes les régressions potentielles introduites par les derniers commits. Tu dois vérifier que RIEN n'est cassé.

PARTIE 1 — CONTRATS API
- Vérifie que api/openapi.yaml est cohérent avec les routes réelles dans api/routes/
- Cherche les routes supprimées ou renommées sans migration frontend : `git diff HEAD~20 -- api/routes/`
- Vérifie que les tests contractuels passent (FrontendApiContractTest si existant)
- Vérifie mobile-workflow-contracts.json et launch-workflow-contracts.json dans dev-hub/tools/

PARTIE 2 — PATTERNS INTERDITS
Cherche dans tout le code les patterns qui violent les règles AGENTS.md :

a) Mobile — patterns interdits :
   - `apiClient.dio.` (sauf dio.download) → doit être requestWithRetry
   - `response.data['data'] as List` → doit être extractDataList()
   - `await` avant runApp() dans main.dart → interdit
   - `.withOpacity(` → doit être .withValues(alpha:)
   - `FirebaseMessaging.instance` sans vérification Firebase.initializeApp()

   Commandes :
   - `grep -rn "apiClient.dio\." front/mobile_apps/ --include="*.dart" | grep -v "dio.download"`
   - `grep -rn "as List" front/mobile_apps/ --include="*.dart" | grep -v extractDataList`
   - `grep -rn "\.withOpacity(" front/mobile_apps/ --include="*.dart"`

b) Web vitrine — patterns interdits :
   - Liens `href="#"` ou `href="#android` → liens morts
   - `/auth/signup` → doit être /signup ou /demo
   - Mojibake : `???`, `Ø`, `Ù` dans les textes localisés

   Commandes :
   - `grep -rn 'href="#' front/web/src/ --include="*.tsx" --include="*.ts"`
   - `grep -rn '/auth/signup' front/web/src/`
   - `grep -rn '???\|[ØÙ]' front/web/src/`

c) Admin dashboard — patterns interdits :
   - `rounded-lg bg-white shadow` → doit utiliser les tokens glass-*
   - Composants sans props typées

d) Backend — patterns interdits :
   - Données sensibles dans les logs ou métadonnées de notification
   - Requêtes N+1 (Model sans eager loading dans les controllers)
   - `dd()` ou `dump()` laissés dans le code
   - `grep -rn "dd(\|dump(" api/app/ --include="*.php"`

e) Documentation — fichiers interdits :
   - Fichiers .md dans docs/PLAN_ACTION2/ (autre que README.md)
   - Fichiers .md dans docs/PLAN_ACTION/ (tout doit être dans archive)
   - `ls docs/PLAN_ACTION2/ | findstr /v README.md`

PARTIE 3 — SANTÉ DES SCRIPTS DE VALIDATION
Exécute (ou vérifie la présence de) chaque script de dev-hub/tools/ :
- validate-launch-workflows.ps1
- validate-mobile-runtime-smoke.ps1
- validate-mobile-notification-production-proof.ps1
- validate-mobile-location-readiness.ps1
- validate-mobile-tenant-branding.ps1
- launch-api-profile-smoke.ps1

PARTIE 4 — COHÉRENCE INTER-SURFACES
- Les endpoints utilisés côté mobile existent-ils encore côté API ?
- Les types TypeScript côté web correspondent-ils aux réponses API ?
- Les contrats de notification (device-tokens, push-notifications/send) sont-ils intacts ?

PARTIE 5 — RAPPORT ET ACTIONS
Pour chaque régression trouvée :
- Classe : 🔴 Régression cassante / 🟡 Dérive de pratique / 🟢 Cosmétique
- Crée une issue GitHub pour chaque 🔴 avec le label `bug` et `P0-critical`
- Crée une issue pour chaque 🟡 avec le label `enhancement` et `P1-high`

Affiche un score de santé global : X régressions 🔴, Y dérives 🟡, Z cosmétiques 🟢.
```

## Notes

- Ce prompt est le filet de sécurité ultime après un sprint d'agents.
- Les patterns interdits viennent directement des leçons apprises documentées dans AGENTS.md.
- À exécuter systématiquement avant un déploiement (complète le prompt 10).
