# Statut des capacités IA — Programme FOCUS (F-29)

> **Programme FOCUS** : l'IA est orientée sur le **wedge** (paie + présence).
> Les capacités génériques restent **fonctionnelles et documentées**, mais leur
> statut est **expérimental** : hors chemin critique, sans promesse de support
> client, et hors périmètre des pilotes FOCUS.

## 🎯 Priorité FOCUS (à approfondir)

| Capacité | Statut | Cible |
|---|---|---|
| Détection d'anomalies de paie (F-28) | 📋 à construire | Rapport pré-clôture, action humaine requise |
| Prédiction d'absentéisme → planning | ✅ existante (`AbsenteeismPredictor`) | Maintenue, connectée au wedge présence |
| Prédiction de turnover | ✅ existante (`TurnoverPredictor`) | Maintenue |
| AI Analytics (requêtes naturelles) | ✅ existante | Maintenue |

## 🧪 Expérimental (documenté, non bloquant)

| Capacité | Statut | Règle |
|---|---|---|
| Voice (STT/TTS — Deepgram, ElevenLabs) | ✅ fonctionnel | Hors chemin critique ; pas de promesse de support |
| Agents conversationnels larges (Orchestrator, ToolRegistry) | ✅ fonctionnel | Usage exploratoire |
| WriteToolPolicy / écritures assistées | ✅ existant | **Interdiction d'écrire la paie sans confirmation humaine** — à tester (F-28) |

## Règles de sécurité (non négociables)

1. **L'IA ne modifie jamais la paie sans confirmation humaine** (WriteToolPolicy).
2. Les endpoints IA ne font pas partie du contrat de conformité des pilotes.
3. Aucune donnée de paie en clair dans les logs (StructuredLogging).
4. Pas de nouvelle feature IA générique pendant le programme FOCUS (les PRs sont re-planifiées après).

## Garde-fous CI existants

- `EnsureAIAnalyticsAccess` / `AIFeatureCheck` / `AIRateLimiter` (middleware).
- Tests AI existants (`AIGatewayAndAnalyticsTest`, `AIWorkflowTest`, `AIWriteActionConfirmationTest`) : maintenus, aucune régression tolérée.
