# Mini-spécification — Issue #3271

## Objectif

Éviter que la console super-admin présente un composeur Chat IA fonctionnel alors que l’API plateforme répond volontairement `501 ADMIN_CHAT_UNAVAILABLE`.

## Correction

`ChatView.vue` affiche un état `role=status` expliquant l’indisponibilité au niveau plateforme, désactive le champ et le bouton d’envoi, et ne tente plus le POST `/v1/admin/ai/chat`. L’historique reste consultable lorsque les endpoints de lecture sont disponibles. Les textes visibles sont ajoutés au catalogue `adminChat` des quatre locales FR/EN/TR/AR.

## Critères d’acceptation

1. L’interface ne promet plus une réponse IA au niveau super-admin.
2. Le formulaire et le bouton d’envoi sont désactivés.
3. Aucun POST vers l’endpoint qui répond 501 n’est exécuté.
4. Le message d’indisponibilité est localisé FR/EN/TR/AR.
5. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3271  
Branche : `fix/3271-honest-admin-ai-state`  
Date : 2026-08-15
