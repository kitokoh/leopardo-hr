# Convergence des apps mobiles vers `leopardo_employee` (F-27, #1557)

> Programme FOCUS — stratégie de convergence progressive : les apps
> non-employee (`leopardo_hr`, `leopardo_manager`, `leopardo_marketing`,
> `leopardo_platform_admin`) convergent vers un socle partagé, avec
> `leopardo_employee` comme app « employé » canonique.

## État 2026-08-09

| App | Fichiers | Imports `leopardo_core` | Rôle |
|---|---|---|---|
| `leopardo_core` | — | — | Package partagé (API client, stockage, i18n, thème, widgets, modèles, providers) |
| `leopardo_employee` | 79 | oui | App employé canonique (pointage hors-ligne, géofencing, congés, paie self-service) |
| `leopardo_manager` | 94 | 89 | Manager/RH (dashboard, équipes, validations) |
| `leopardo_hr` | 93 | 88 | RH dédiée (split de manager) |
| `leopardo_platform_admin` | 12 | 11 | Super-admin plateforme (routes `/platform/*`) |
| `leopardo_marketing` | 6 | oui | Marketing (publication réseaux sociaux) |

Aucun import direct `package:leopardo_employee` depuis les autres apps :
la convergence passe par `leopardo_core`, jamais par des imports inter-apps.

## Stratégie de convergence

1. **Socle unique** : chaque brique commune (API `requestWithRetry` +
   `extractDataList/Map`, offline Hive, push, branding tenant, géofencing)
   vit dans `leopardo_core` — déjà le cas (validate-mobile-apps-split.ps1
   verrouille la séparation).
2. **Parallélisme contrôlé** : `leopardo_hr`/`leopardo_manager` restent des
   apps parallèles tant que les parcours diffèrent (équipes/validations vs
   self-service). La convergence ne fusionne PAS les écrans, elle **réduit la
   duplication** : tout code dupliqué est extrait vers `leopardo_core`.
3. **Traqueur de duplication** (à créer) : un inventaire
   `docs/mobile/CONVERGENCE_TRACKER.md` listant les fichiers dupliqués
   (screen/provider/service) et leur cible de refactor.
4. **Gardes CI** : `mobile-apps-guard` (10 scripts validate-mobile-*) +
   `Mobile apps split guard` — toute évolution qui violerait la séparation
   échoue en PR.

## Critères de convergence (progressive)

- [ ] 100 % des repositories/services partagés dans `leopardo_core`
      (audit par grep des `lib/features/**/data|services` dupliqués).
- [ ] Zéro widget de base dupliqué (boutons, cartes, champs) — thème core.
- [ ] Le tracker de convergence versionné et mis à jour à chaque refactor.
- [ ] `leopardo_platform_admin` : uniquement `leopardo_core` + écrans
      plateforme (déjà quasi atteint : 12 fichiers).
- [ ] Docs client (F-25) et guide testeur alignés sur la cible.

## Hors périmètre

- Fusionner `leopardo_hr`/`leopardo_manager` en une seule app (décision
  produit, pas technique) — le socle core le permettra le moment venu.
- Le kiosque (`front/zkteco-kiosk`) reste une web app dédiée (hors Flutter).
