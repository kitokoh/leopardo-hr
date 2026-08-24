# Feature Specification: Pack CM — cas limites golden (issue #5252)

**Feature Branch**: `mod/payroll/5252-pack-cm-completion`

**Created**: 2026-08-23

**Input**: Issue #5252 — Pack Cameroun 100 % (CNPS, IRCM, base CEMAC, golden tests ≥ 15).

**État** : règles CM solides (IRPP annuel CGI 2024 art. 68 : 10/15/25/35 % + centimes ×1,10 ; abattement frais pro 30 % plafonné 350 000 XAF/mois ; CNPS vieillesse 4,2/4,2 % + famille 7 % plafonnées 750 000, AT 2 % non plafonné) ; 17 golden tests existants (≥ 15 requis).

**Manques** : profils IRPP franchissant les bornes annuelles 2 M/3 M/5 M, preuve du plafond d'abattement, preuve du non-plafonnement de l'AT.

**Décision** : `GoldenCmEdgeCasesTest` (6 tests / 14 assertions). Zéro changement moteur.

**Validation** : 6 tests verts, Pint PASS, PHPStan strict 0 erreur.
