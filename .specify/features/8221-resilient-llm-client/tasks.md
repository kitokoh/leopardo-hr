# Tâches — ResilientLLMClient (#8221 / BOS-031)

## Livrable 1 — spec (cette issue)

- [x] 1. Preuves vérifiées sur `main` (clients HTTP brut, tarifs en dur, interface sans `response_format`) — spec §1.
- [x] 2. `spec.md` — politique de fallback, matrice cloud-policy × fallback (§4.2), seuils CB (§5.3), dégradation explicite, parité flag OFF.
- [x] 3. `plan.md` — architecture, fichiers touchés, risques.
- [x] 4. `tasks.md` — ce fichier.

## Livrable 2 — implémentation (même PR)

- [ ] 5. Config `ai.php` : sections `resilience` (flag OFF défaut, chaîne, retry, CB) et `costs` (tarifs externalisés, valeurs = parité).
- [ ] 6. `AIResponse` : `status` + `provider` optionnels, `isRetryable()`.
- [ ] 7. Interface `LLMClient::chat(..., ?array $responseFormat = null)` ; 4 clients prod + doublons de test migrés ; pass-through OpenAI/Groq.
- [ ] 8. `LLMCircuitBreaker` (Cache, seuil 3, cooldown 60 s, half-open 1 sonde, best-effort).
- [ ] 9. `ResilientLLMClient` : retry 2× backoff, chaîne filtrée cloud-policy (tenant de session), dégradation explicite, logs structurés sans PII.
- [ ] 10. Binding `AppServiceProvider` : wrap conditionnel (flag ON), entrées inconnues ignorées + log.
- [ ] 11. `AIAuditLogger::estimateCost` ← config ; `Orchestrator` : attribution `response->provider ?? client->provider()`.
- [ ] 12. Tests : failover, CB (ouvre/fail-fast/half-open/referme), cloud-policy (jamais de cloud + dégradation), retry/comptage, parité flag OFF, coûts config, `response_format`.
- [ ] 13. CHANGELOG `[Unreleased]` + PR : checklist CA 1–5 cochée avec preuves, section rollback, **revue sécurité demandée** (app/AI).

## Après merge (suivi programme)

- BOS-032 (#8222) puis BOS-034 (#8223) deviennent éligibles (séquence Z2, une PR à la fois).
- Démonstration staging « panne Groq → fallback » à l'Exit Gate Block 3 (exploitation, hors PR).
