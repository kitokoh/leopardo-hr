# Guide Integration Partenaires

## Objectif

Ce guide donne le contrat d'integration minimal pour les partenaires Leopardo RH : API REST, webhooks, exports et futur SSO. Il sert aux integrateurs paie, comptabilite, pointeuses, support client et revendeurs terrain.

Les decisions open core et marketplace sont cadrees dans `docs/GUIDES/GUIDE_OPEN_CORE_MARKETPLACE.md` et l'ADR `docs/architecture/adr/0004-open-core-marketplace-boundaries.md`. Un partenaire ne doit jamais importer directement du code backend ou mobile interne : l'integration passe par API publique, webhooks signes et scopes documentes.

## Prerequis

- Obtenir un compte super-admin ou un compte tenant autorise.
- Lire la documentation interactive : `/docs`.
- Utiliser la specification canonique : `/docs/openapi.yaml`.
- Tester d'abord sur un environnement sandbox ou staging.
- Ne jamais partager un token entre deux clients.

## API REST

Les endpoints stables sont exposes sous `/api/v1`.

### Environnements

| Environnement | URL | Usage |
|---------------|-----|-------|
| Production API | `https://gestionemployerbackend.onrender.com/api/v1` | Backend Render actuel pour apps mobiles et integrateurs autorises |
| Documentation | `https://gestionemployerbackend.onrender.com/docs` | Swagger UI publique |
| API Explorer | `https://gestionemployerbackend.onrender.com/api-explorer` | Requetes demo avec tokens Bearer |
| Local | `http://localhost:8000/api/v1` | Developpement backend |

Les comptes demo exposes par `/api/v1/demo-users` sont destines a la recette et a la documentation. Ils ne doivent pas etre reutilises comme comptes serveur a serveur.

### Authentification

- Employes/managers : token Sanctum obtenu via `/api/v1/auth/login`.
- Plateforme : token super-admin obtenu via `/api/v1/platform/auth/login`.
- Les integrations serveur a serveur doivent utiliser un compte dedie, avec rotation controlee.
- Chaque requete authentifiee doit envoyer `Authorization: Bearer <token>` et `Accept: application/json`.

### Versioning

- La version stable actuelle est `/api/v1`.
- Une route ne doit pas changer de structure JSON sans migration documentee.
- Les futurs breaking changes doivent passer par `/api/v2` ou par un champ de compatibilite explicite.
- Les clients doivent ignorer les champs inconnus afin de rester compatibles avec les ajouts non cassants.

### Format JSON standard

Les ressources simples sont retournees sous une cle `data` lorsque c'est possible :

```json
{
  "data": {
    "id": 123,
    "name": "Amina"
  }
}
```

Les listes paginees doivent rester compatibles Laravel :

```json
{
  "data": [],
  "links": {
    "first": "https://example.test/api/v1/employees?page=1",
    "last": "https://example.test/api/v1/employees?page=3"
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42
  }
}
```

Les erreurs suivent le format :

```json
{
  "error": "VALIDATION_ERROR",
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Codes metier a traiter par les clients :

- `UNAUTHENTICATED` ou HTTP `401` : reconnecter l'utilisateur.
- `FORBIDDEN` ou HTTP `403` : permission insuffisante ou ressource hors perimetre.
- `COMPANY_NOT_FOUND` : lookup tenant absent ou entreprise inactive.
- `TWO_FA_REQUIRED` : le super-admin doit completer le second facteur.
- `VALIDATION_ERROR` : afficher les erreurs champ par champ.
- `RATE_LIMITED` ou HTTP `429` : retry avec backoff.

### Regles multi-tenant

- Une requete authentifiee est toujours rattachee a une company.
- Les identifiants d'un autre tenant doivent retourner `403` ou `404`.
- Les exports doivent etre demandes depuis le tenant source uniquement.
- Les champs RH sensibles ne doivent pas etre caches hors du systeme partenaire sans base legale et duree de retention.

## Webhooks

Les webhooks diffusent les evenements metier vers un endpoint partenaire HTTPS.

### Evenements supportes

- `employee.created`
- `employee.archived`
- `employee.departed`
- `attendance.checked_in`
- `attendance.checked_out`
- `absence.requested`
- `absence.approved`
- `absence.rejected`
- `payroll.validated`

> Le catalogue complet annonce (y compris les evenements planifies non encore
> emis) vit dans `WebhookController::AVAILABLE_EVENTS` ; la CI garde
> l'alignement catalogue ↔ emissions reelles (`check-webhook-event-catalog.sh`,
> issue #5744).

### Contrat de livraison

Enveloppe canonique versionnee (issue #5744 — additive, retro-compatible) :

```json
{
  "event": "employee.created",
  "event_version": 1,
  "company_id": "8f14e45f-ceea-4b0a-9d0e-3c9b3a0f5c12",
  "correlation_id": "0f7c3a2e-4d1b-4a5c-9e8f-6a7b8c9d0e1f",
  "occurred_at": "2026-08-28T09:00:00+00:00",
  "timestamp": "2026-08-28T09:00:01+00:00",
  "data": {
    "id": 123
  }
}
```

- `event` : nom stable de l'evenement (ne change pas ; la version vit dans `event_version`).
- `event_version` : version du contrat d'evenement — incrementee uniquement pour un changement incompatible (procedure `docs/api/VERSIONING.md` § 5bis).
- `company_id` : identifiant du tenant proprietaire de l'occurrence.
- `correlation_id` : identifiant de correlation de l'occurrence metier — identique pour tous les endpoints d'un meme tenant lors d'un meme dispatch.
- `occurred_at` : moment metier de l'occurrence (ISO 8601).
- `timestamp` : horodatage de livraison (champ herite, conserve pour retro-compatibilite).
- `data` : donnees metier de l'evenement.

### Signature et idempotence

Chaque livraison inclut :

- `Webhook-Id` : identifiant unique et idempotent de la livraison.
- `Webhook-Timestamp` : horodatage Unix.
- `Webhook-Signature` : `v1=<hmac-sha256>,t=<timestamp>` — HMAC SHA-256 de
  `<timestamp>.<payload JSON brut>` avec le secret partage de l'endpoint.
- `X-Leopardo-Event` : nom de l'evenement (legacy).
- `X-Leopardo-Signature` : HMAC SHA-256 (legacy).
- `X-Leopardo-Event-Version` : version de l'evenement (issue #5744).

Le partenaire doit refuser un timestamp trop ancien et ignorer proprement un
`Webhook-Id` deja traite. Les retries doivent etre consideres normaux.

### Retry et dead-letter

Chaque livraison echoue jusqu'a 3 tentatives (backoff 30s / 120s / 600s). Au-dela de 10 echecs cumules, l'endpoint est automatiquement desactive (`active=false`) pour eviter de marteler un partenaire indisponible.

Si les 3 tentatives echouent, la livraison est marquee dead-letter (`dead_lettered_at` non nul) plutot que silencieusement perdue :

- `GET /api/v1/webhooks/{id}/dead-letters` liste les livraisons dead-letter d'un endpoint (managers uniquement).
- `POST /api/v1/webhooks/{id}/dead-letters/{delivery}/replay` remet en file le meme evenement/payload d'origine (202) et reactive l'endpoint pour lui laisser une nouvelle chance. Le replay ne re-execute pas l'action metier d'origine cote plateforme : c'est le meme `event`/`data`, donc pas de doublon fonctionnel emis — seul le partenaire doit deduplicquer via l'`event_id`/`Webhook-Id` s'il retraite un evenement deja vu.

### Exigences partenaire

- Endpoint HTTPS public.
- Repondre `2xx` en moins de 5 secondes.
- Verifier la signature ou le secret partage quand il est fourni.
- Traiter les evenements de facon idempotente.
- Journaliser `event`, `company_id`, horodatage et statut de traitement.

## Exports

Les exports a privilegier pour les integrations sont :

- paie : bulletins, rapports mensuels, virements quand disponibles ;
- attendance : anomalies, rapport mensuel JSON/CSV/PDF ;
- privacy : exports RGPD a usage strictement encadre ;
- comptabilite : ecritures de paie quand le module sera active.

## SSO SAML/OIDC

Le SSO enterprise est planifie mais ne doit pas etre considere comme disponible tant qu'une ADR et un contrat OpenAPI dedie ne sont pas merges.

Principes retenus pour la future integration :

- OIDC prioritaire pour Google Workspace et Azure AD ;
- SAML reserve aux clients enterprise qui l'exigent ;
- mapping explicite `email -> employee/user lookup` ;
- refus par defaut si l'utilisateur n'est pas lie a une company active ;
- journalisation de chaque login SSO avec tenant, provider et resultat.

## Checklist de recette partenaire

- Healthcheck `/api/v1/health` OK.
- Authentification OK avec un compte de test dedie.
- Lecture d'une ressource autorisee OK.
- Lecture d'une ressource hors tenant refusee.
- Webhook nominal recu et traite.
- Webhook rejoue sans doublon fonctionnel.
- Erreur partenaire visible dans les logs de livraison.
- Documentation de donnees personnelles et retention validee.

## Support

En cas d'incident d'integration, fournir :

- environnement ;
- company concernee ;
- endpoint appele ou event webhook ;
- `X-Request-Id` si present ;
- horodatage UTC ;
- payload minimal masque ;
- resultat attendu et resultat observe.
