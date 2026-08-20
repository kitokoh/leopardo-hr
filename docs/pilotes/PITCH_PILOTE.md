# 🎯 Pitch pilote — Leopardo RH pour les PME algériennes (issue #5154)

**Version** : 1.0 · **Date** : 2026-08-20 · **Usage** : 1 page, support de la signature des 3 pilotes DZ (Phase 2).

---

## Le problème

- **La paie algérienne est un casse-tête** : IRG par tranches avec abattement, CNAS salariale 9 % et patronale 26 %, SMIG à 20 000 DZD — chaque erreur coûte des redressements et du temps RH.
- Les PME (5-250 employés) jonglent entre Excel, un cabinet comptable et des outils qui ne connaissent pas le modèle DZ.
- Aucune solution « tout-en-un » mobile-first adaptée à la réalité terrain : pointage, paie et RH dans le même outil.

## La solution

**Leopardo RH** — un OS RH/paie open-source, multi-tenant, pensé pour les PME :

- **Paie DZ conforme** : moteur de paie algérien avec barème IRG (LF en vigueur), CNAS 9 %/26 %, SMIG, abattement 40 % (plancher 12 000 / plafond 18 000 DZD/an) — **validé par un expert-comptable DZ le 2026-08-08**.
- **Pointage biométrique** : kiosques ZKTeco (empreinte/visage/carte), géofencing, horaires par équipe.
- **Mobile-first** : employés et managers opèrent depuis leur téléphone (pointage, demandes, bulletins).
- **Open-source & sans enfermement** : code sous licence ouverte, pas de coût par employé caché.

## La preuve

| Élément | Détail |
|---|---|
| Conformité | Référentiel `docs/payroll/DZ_COMPLIANCE.md` — IRG, CNAS, SMIG validés par expert-comptable DZ (2026-08-08) |
| Golden tests | **Golden tests DZ calculés à la main** (fixtures réalistes, références légales `CGI art.…`) — objectif ≥ 40 (issue #5149) ; 158 golden tests toutes zones |
| Transparence | Calcul détaillé dans chaque bulletin (assiette IRG = brut − CNAS, abattement, tranches) |
| 30 min | Onboarding complet < 30 min (checklist `docs/pilotes/ONBOARDING_PILOTE.md`, issue #5151) |

## Conditions pilote (3 places — DZ)

- **Durée** : 3 mois gratuits (période pilote), données réelles.
- **En échange** : feedback structuré (carnet pilote, issue #5152), cas réels de paie (bulletins anonymisés), un pointage/paie réels par mois.
- **Accompagnement** : SLA bugs < 24 h (issue #5155), onboarding guidé < 30 min.
- **Début** : J+7 après signature (sessions d'onboarding planifiées).
- **Périmètre inclus** : pointage (kiosque web/app), RH (employés, contrats, absences), paie DZ (IRG/CNAS, bulletins), import CSV, rapports.
- **Hors périmètre pilote** : personnalisations hors-modèle, pays hors DZ (MA/SN en maintenance, freeze #5147).

## Parcours prospect → pilote

1. Clic sur « Essai gratuit » (vitrine) — parcours trial guidé (sandbox < 30 s) ou OTP.
2. Session d'onboarding pilotée (< 30 min, checklist).
3. Signature de la fiche d'engagement pilote (fiches : `docs/pilotes/FICHES_QUALIFICATION_PILOTES.md`).
4. Démarrage : import des données réelles, 1er pointage, 1re paie simulée.

## Contact

Formulaire de contact vitrine (section « Pilotes DZ ») ou canal dédié — voir SLA pilotes `docs/pilotes/SLA_PILOTES.md` (#5155).

---

*Document commercial — données conformes au freeze scope 60 jours (#5147). Pricing définitif hors périmètre (décision R5).*
