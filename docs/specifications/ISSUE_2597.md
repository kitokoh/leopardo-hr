# ISSUE_2597 — Écrans AI Voice placeholders « Bientôt disponible »

**Statut**: Fixed (PR `fix/2597-remove-ai-voice-placeholders`) · **Priorité**: P3 · **Module**: mobile (×3 apps)

## Constat

`ai_voice_screen.dart` (leopardo_hr, leopardo_manager) + `ai_voice_repository.dart` (×3) =
écrans placeholders orphelins : routes GoRouter retirées (#3715), aucune entrée manifeste,
provider `aiVoiceRepositoryProvider` jamais consommé.

## Correctif (option 1 de l'issue)

Suppression des 5 fichiers + 2 déclarations de provider + imports. Aucune référence restante.
Le câblage réel `/ai/voice/transcribe` (#2213) reste un chantier produit séparé.
