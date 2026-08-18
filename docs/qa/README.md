# 📋 Sessions QA — docs/qa/

> Inventaire et cycle de vie des comptes-rendus de sessions QA / audit 360°.

## Règle de nommage

`QA_SESSION_YYYY-MM-DD-<suffixe>.md` — le suffixe identifie l'expert ou la vague
(ex. `expert5`, `swe-qa`, `agent360`). **Éviter les suffixes dupliqués** (ex. trois
fichiers `QA_SESSION_2026-08-16-swe-qa` + `-2`, `-b`, `-v2`, `-v3` : un seul fichier
par vague, avec versioning `-vN` réservé aux corrections).

## Cycle de vie

1. **Session du jour** : fichier créé dans `docs/qa/` avec date + suffixe.
2. **Clôture** : quand la session est synthétisée dans un rapport consolidé
   (`docs/audits/` ou `docs/qa/` avec bandeau), ajouter un bandeau
   `> ✅ CLÔTURÉE le YYYY-MM-DD — voir <lien>`.
3. **Archivage** : les sessions de plus de 7 jours sans relecture passent sous
   `docs/archive/qa/` ou reçoivent un bandeau d'obsolescence.
4. **Interdits** : fichiers de test (`probe-*.md`), brouillons — supprimés ou
   déplacés hors `docs/qa/`.

## Sessions notables (récentes)

- `QA_SESSION_2026-08-17-*` — audit 360° (drain, portail Lot 2, leçons) — actives.
- `QA_SESSION_2026-08-16-*` — audit 360° multi-experts (agent360, swe-qa, qa360…) — à consolider.
- `audit-expert5-2026-08-15/` — audits par surface (admin, api, mobile, web).

Rapports consolidés : `docs/audits/`, `docs/qa-expert-audit-360-2026-08-15.md`.
