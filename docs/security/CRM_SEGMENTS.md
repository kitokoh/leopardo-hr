# CRM — Segments : consentement & rétention

> Module CRM client — Issue #5723. Règles de conformité des segments.

## Consentement

- La condition `crm_consents.has_consent` (canal, finalité marketing)
  n'inclut que les contacts ayant un consentement **`granted` actif** à
  l'instant du rebuild : les segments respectent le consentement (RGPD).
- Le retrait d'un consentement (`#5722`) n'efface pas rétroactivement les
  membres computed : les campagnes ciblant un segment re-vérifient le
  consentement à l'envoi (`CommunicationConsentService::allows()`) — la
  garde est toujours appliquée au moment de l'action.
- Un segment ne stocke **aucune PII** : il référence des `contact_id`
  (entiers) — la rétention des données de contact appartient aux tables CRM
  (#5708) et à la politique de rétention du socle.

## Rétention

| Donnée | Rétention |
|---|---|
| `crm_segments` (définitions) | Tant que le tenant est actif ; suppression explicite par l'utilisateur (destruction en cascade membres + versions) |
| `crm_segment_versions` | Alignée sur le segment ; conservée 3 ans après suppression du segment (audit) |
| `crm_segment_members` | Alignée sur le segment ; purgée à la suppression |
| `audit_logs` (mutations) | 5 ans (politique d'audit du socle) |

## Anonymisation

- À l'anonymisation d'un contact, ses `crm_segment_members` sont supprimées
  (le lien segment↔contact est rompu) ; les définitions de segments ne
  contiennent aucune donnée personnelle et sont conservées.
- Aucune exportation de segment n'expose d'email/téléphone en clair hors du
  périmètre habilité (`principal`/`marketing`, audit `segment.export` — à
  venir avec #5729).
