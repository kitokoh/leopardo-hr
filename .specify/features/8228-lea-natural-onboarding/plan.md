# Plan d'Exécution: Léa — Onboarding en Langage Naturel (BOS-040)

## Phase 1 : Spécification & Constitution (Cette PR #8228)
- [x] Spécification architecturale complète (`spec.md`)
- [x] Découpage en tâches prêtes pour BOS-041/042/043 (`tasks.md`)
- [x] Amendement de la Constitution Leopardo : Principe « L'IA propose, le moteur décide » et règles de gouvernance des tables de connaissances tenant-scopées.

## Phase 2 : Extracteur Déterministe (BOS-041)
- Implémentation du service `InterviewAnswerExtractor` dans `api/app/Modules/Onboarding/Domain/Services/`.
- Prompting contraint avec fallback regex / json parser.
- Tests unitaires et suites de parité (golden test cases langage naturel → réponses `SetupInterviewPlanner`).

## Phase 3 : Endpoint & Orchestration (BOS-042)
- Création du contrôleur `LeaOnboardingController` et de l'endpoint `POST /api/v1/onboarding/lea/interpret`.
- Rate limiting dédié et gestion du fallback dégradé.

## Phase 4 : Interface Utilisateur (BOS-043)
- Écran récapitulatif éditable sur `front/web` (`/onboarding/lea/review`).
- Validation directe vers `CompleteSetupInterview`.

## Phase 5 : Mesure & Pilote (BOS-044)
- Audit logs `lea.intent.*` sur 100 inscriptions pilotes.
- Décision de passage en General Availability (GA).
