# Mini-spec — Issue #3706

## Intention
Éliminer l’ambiguïté entre les domaines réellement joignables et les domaines de production réservés, afin que les templates et les builds ne pointent pas par défaut vers des hôtes NXDOMAIN.

## Décision
Le domaine API actuellement canonique est `gestionemployerbackend.onrender.com`, vérifié comme joignable. Le portail web actuellement déployé est `gestionemployer-backend.vercel.app`. Les domaines `*.leopardo-rh.com` restent documentés comme cibles de migration et ne doivent pas être utilisés comme defaults avant validation DNS/HTTP.

## Changements
`docs/ops/DOMAINS.md` devient la source de vérité opérationnelle. `api/.env.example` utilise désormais les endpoints actifs pour `APP_URL` et `FRONTEND_URL`; `CLOUD_API_URL` était déjà aligné. Les defaults frontend, CI, kiosk et smoke test déjà actifs sont conservés et référencés par le registre.

## Critères d’acceptation

| Critère | Résultat |
|---|---|
| Source de vérité ajoutée | `docs/ops/DOMAINS.md` |
| Defaults API sans NXDOMAIN | `APP_URL` et `FRONTEND_URL` utilisent les domaines actifs |
| Domaine API versionné documenté | `/api/v1` est explicite dans le registre |
| Migration DNS séparée | Les domaines réservés sont marqués comme non actifs |
| Garde anti-réapparition | Couverture par `check-canonical-domains.sh` de #3708 |
