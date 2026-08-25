# Feature Specification: Pack CI — cas limites golden (issue #5251)

**Feature Branch**: `mod/payroll/5251-pack-ci-completion`

**Created**: 2026-08-23

**Input**: Issue #5251 — Pack Côte d'Ivoire 100 % (CNPS, ITS, GMP, golden tests ≥ 15).

**État** : règles CI solides (ITS 2024 — réforme ord. 2023-718/719, CGI art. 119 bis : 6 tranches mensuelles 0→32 % ; CNSS retraite 3,2/4,5 % plafond 1 647 315 ; famille 5,75 % + AT 2 % plafond 70 000 ; RICF art. 120) ; 28 golden tests existants (≥ 15 requis).

**Manques** : bornes exactes du barème ITS (± 1 FCFA) et plafonds CNSS à la frontière.

**Décision** : `GoldenCiEdgeCasesTest` (12 tests / 22 assertions) — 10 bornes ITS + retraite 1 647 315 exacte/au-delà + famille/AT 70 000 exacte/au-delà. Zéro changement moteur.

**Validation** : 12 tests verts, Pint PASS, PHPStan strict 0 erreur.
