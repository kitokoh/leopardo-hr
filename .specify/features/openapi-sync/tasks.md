## Tâches
- [ ] 1. Écrire/extr. le script de comparaison chemins openapi ↔ routes (python, dans /tmp, non commité).
- [ ] 2. Lister les écarts confirmés (i18n, exports, partner, SmartAttendance, verbes documents/expense-claims/loans, bank-exports).
- [ ] 3. Corriger `api/openapi.yaml` pour les écarts « spec doit suivre le code ».
- [ ] 4. Implémenter `GET /api/v1/bank-exports` + `POST /api/v1/bank-exports` (`BankExportController`), routes dans le bon fichier module, tests Feature.
- [ ] 5. Mettre à jour `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md` si un frontend consomme ces routes.
- [ ] 6. Vérifier Redocly lint en local si dispo (npx @redocly/cli lint) sinon CI.
- [ ] 7. CHANGELOG + PR `fix/2267-...` `Closes #2267`, CI verte, merge.
