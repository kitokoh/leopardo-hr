# Registre canonique des domaines

## Domaines actuellement joignables

| Surface | Domaine canonique actuel | Usage |
|---|---|---|
| API Laravel | `https://gestionemployerbackend.onrender.com` | API, santé, documentation et clients web/mobile/kiosque |
| API versionnée | `https://gestionemployerbackend.onrender.com/api/v1` | Base URL des consommateurs API |
| Portail web Vercel | `https://gestionemployer-backend.vercel.app` | Vitrine et parcours web actuellement déployés |

Ces valeurs correspondent aux defaults exécutables et au backend Render vérifié comme joignable. Elles doivent rester la référence pour les environnements de démonstration et de staging tant que le DNS de production n’est pas provisionné.

## Domaines de production réservés

Les domaines suivants sont des cibles de migration, et non des endpoints actifs : `www.leopardo-rh.com`, `app.leopardo-rh.com`, `admin.leopardo-rh.com`, `api.leopardo-rh.com` et `docs.leopardo-rh.com`. Ils ne doivent pas être utilisés comme defaults de build ou comme URL de smoke test avant validation DNS/HTTP.

La mise en place du DNS et des certificats reste une responsabilité d’infrastructure distincte. Toute nouvelle référence first-party doit être ajoutée ici et validée par la garde `dev-hub/tools/check-canonical-domains.sh`.
