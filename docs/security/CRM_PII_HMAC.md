# 🔐 CRM client — stratégie PII : HMAC lookup, chiffrement, audit (F-? / #5713)

> Programme CRM V0 — protection des données personnelles du CRM client
> (accounts, contacts, leads, opportunités — espaces client / API tenant).
> Mis à jour le 2026-08-28 (issue #5713, lot CRM V0).

## Objectif

Toute donnée personnelle CRM client (email, téléphone, nom, notes libres)
doit être :

1. **irréversible au repos pour le lookup** — l'email/téléphone ne circule
   jamais en clair dans un index de recherche ;
2. **chiffrée pour la lecture** — AES-256 (APP_KEY) via `SensitiveDataEncryptor`
   (pattern existant F-17) ;
3. **absente des logs** — aucun log applicatif, d'erreur ou d'audit ne
   contient de PII en clair ;
4. **auditée** — création, mutation, export, consentement et archivage sont
   journalisés (`audit_logs`, pattern existant).

## Architecture en deux colonnes (par table CRM)

| Colonne | Contenu | Usage |
|---|---|---|
| `email` / `phone` | Valeur **chiffrée** AES-256 (`enc:` + Crypt::encryptString) | Lecture (déchiffrement à la volée) |
| `email_hmac` / `phone_hmac` | Digest **HMAC-SHA256** hex (64) | Recherche exacte par égalité (`WHERE email_hmac = ?`) |

- Le digest est calculé par `PiiHmacLookupService` (`api/app/Core/Auth/Infrastructure/Services/PiiHmacLookupService.php`) — clé dérivée du `APP_KEY` (salt `leopardo-crm-pii-hmac-v1`), aucune variable d'env supplémentaire (parité `.env.example` #1487).
- La **normalisation** (email : lower/trim ; téléphone : suppression des séparateurs et du `+`) est appliquée à l'écriture ET à la recherche — c'est la seule garantie de retrouver le digest.
- Un digest seul ne permet pas de retrouver la valeur sans la clé (HMAC avec clé secrète) ; le chiffré seul ne permet pas de recherche. Les deux colonnes se complètent.

## Application aux tables #5709 (état actuel)

Les tables livrées en #5709 (`crm_leads`, `crm_pipelines`, `crm_opportunities`)
portent les colonnes PII **en clair pour l'exploitation** — la consolidation
HMAC (ajout des colonnes `*_hmac` + backfill + bascule des accès) est
planifiée avec la livraison des modèles du module CRM (issue #5710/#5711,
squelette #5707). La présente stratégie est le contrat de cette consolidation.

## Règles d'ingénierie (contraintes pour toute PR CRM)

1. **Jamais de PII en clair dans un log** : `Log::info`/`error`, exceptions,
   réponses d'API non autorisées → utiliser `PiiHmacLookupService::mask()`.
2. **Recherche par email/téléphone** : `lookup()` puis `WHERE *_hmac = ?`.
3. **Export** (CSV/API) : valeur déchiffrée uniquement pour l'utilisateur
   autorisé, journalisé (`audit_logs`, événement `crm.export`).
4. **Consentement** : les préférences de communication vivent dans la table
   de consentement CRM (issue #5722) ; aucune sollicitation sans base légale.
5. **Archivage** : suppression = `softDeletes` + purge RGPD documentée
   (jamais de `DELETE` direct sur une donnée client sans journal).
6. **N+1** : charger les valeurs déchiffrées par lot (pas de déchiffrement
   en boucle dans un map).

## Registre RGPD

Voir la section « CRM client » ajoutée dans
`docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` (traitements accounts/
contacts/leads : finalités, bases légales, conservation, mesures).
