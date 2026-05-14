# Guide Integration Partenaires

## Objectif

Ce guide donne le contrat d'integration minimal pour les partenaires Leopardo RH : API REST, webhooks, exports et futur SSO. Il sert aux integrateurs paie, comptabilite, pointeuses, support client et revendeurs terrain.

## Prerequis

- Obtenir un compte super-admin ou un compte tenant autorise.
- Lire la documentation interactive : `/docs`.
- Utiliser la specification canonique : `/docs/openapi.yaml`.
- Tester d'abord sur un environnement sandbox ou staging.
- Ne jamais partager un token entre deux clients.

## API REST

Les endpoints stables sont exposes sous `/api/v1`.

### Authentification

- Employes/managers : token Sanctum obtenu via `/api/v1/auth/login`.
- Plateforme : token super-admin obtenu via `/api/v1/platform/auth/login`.
- Les integrations serveur a serveur doivent utiliser un compte dedie, avec rotation controlee.

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
- `attendance.checked_in`
- `attendance.checked_out`
- `absence.requested`
- `absence.approved`
- `absence.rejected`
- `payroll.validated`

### Contrat de livraison

```json
{
  "event": "employee.created",
  "company_id": "uuid",
  "occurred_at": "2026-05-14T09:00:00Z",
  "data": {
    "id": 123
  }
}
```

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
