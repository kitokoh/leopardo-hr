# Feature Specification: Léa — Onboarding en Langage Naturel (BOS-040)

**Feature Branch**: `feat/8228-bos-040-lea-natural-onboarding`  
**Created**: 2026-10-08 | **Status**: Review Ready  
**Issue**: #8228 (Programme Business OS — Block 4, P2 / Architecture, zéro code prod)  
**Références programme**: `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` — prérequis de BOS-041/042/043.

---

## 1. Contexte & Problème

Aujourd'hui, l'onboarding Leopardo passe par le formulaire d'entretien `/setup-interview` (`SetupInterviewPlanner`). Bien que déterministe et sûr, ce formulaire impose une friction cognitive aux fondateurs de terrain qui préfèrent décrire leur activité en langage naturel (« J'ai un restaurant de 15 personnes avec service à table et gestion des plannings »).

La décision d'architecture arrêtée (ADR-BOS-040 / 08 §6) fixe le principe non négociable :
**« L'IA propose, le moteur décide, l'utilisateur confirme. »**
L'IA ne fait que traduire le texte libre en réponses de l'interview existant (`SetupInterviewPlanner`), sans jamais effectuer d'écriture directe en base de données ni d'activation autonome.

---

## 2. Contrat d'Interface & Schéma d'Answers Produit

Léa extrait et produit exclusivement un objet conforme à `SetupInterviewPlanner::QUESTIONS` :

```json
{
  "company_name": "string (optionnel, 2..120 caractères)",
  "company_type": "solo" | "team",
  "team_size": "1-10" | "11-50" | "51-200" | "201-500" | "500+",
  "sector": "restaurant" | "fuel_station" | "education" | "commerce" | "services" | "travel" | "other",
  "premises": "single" | "multiple" | "mobile" | "none",
  "priorities": ["attendance", "payroll", "accounting", "crm", "cameras", "showcase"],
  "scheduled_hours": "yes" | "no"
}
```

### Règles d'Allowlist & Fail-Closed
1. **Valeurs inconnues** : Toute valeur non présente dans `SetupInterviewPlanner::QUESTIONS` est strictement rejetée et convertie en `null` (ou omise de `priorities`).
2. **Priorités (multi-choix)** : Seules les valeurs dans `['attendance', 'payroll', 'accounting', 'crm', 'cameras', 'showcase']` sont acceptées. Aucune extension ad-hoc n'est autorisée en V1 (les spécificités d'éducation sont dérivées via `sector: education`).
3. **Texte libre (`company_name`)** : Nettoyé (`trim`), borné à [2, 120] caractères.

---

## 3. Stratégie JSON Robuste (Sans Structured Output Natif)

Étant donné que `LLMClient` ne garantit pas de mode JSON-Schema natif selon les providers :
1. **System Prompt Contraint** : Le prompt injecte la définition de l'allowlist et impose un format JSON strict sans balises Markdown superflues (````json`).
2. **Extraction & Sanitization** : Regex d'extraction du bloc JSON `{...}` pour éliminer tout préambule ou bavardage.
3. **Validation Déterministe** : Validation via `InterviewAnswerValidator` contre l'allowlist `SetupInterviewPlanner`.
4. **Retry Loop (1 itération max)** : En cas d'erreur de parsing ou de non-conformité, un unique prompt correctif est envoyé avec la cause d'erreur.
5. **Fallback Transparent** : Si l'extraction échoue, le système bascule immédiatement vers le formulaire interactif standard sans bloquer l'utilisateur.

---

## 4. Règles de Clarification & Dialogue (≤ 2 Rounds)

- **Round 1 (Initial)** : L'utilisateur fournit sa description libre. Léa extrait les réponses certaines et identifie les ambiguïtés bloquantes (ex: type d'équipe non précisé).
- **Round 2 (Clarification ciblée)** : Léa pose au maximum 2 questions ciblées portant uniquement sur les champs essentiels manquants.
- **Fin de dialogue** : Si des ambiguïtés subsistent après le round 2, les valeurs par défaut sûres sont proposées sur l'écran récapitulatif éditable côté frontend (`/onboarding/lea/review`).

---

## 5. Audit & Observabilité (`lea.intent.*`)

Chaque appel au pipeline Léa émet des événements de télémétrie structurée :
- `lea.intent.received` : Réception du texte utilisateur (hash / métadonnées de longueur, PII non persistées).
- `lea.intent.parsed` : Réponses extraites avec score de confiance par champ.
- `lea.intent.fallback` : Déclenchement du fallback vers le formulaire guidé (avec motif : syntax_error, low_confidence, validation_failed).
- `lea.intent.confirmed` : Validation finale par l'utilisateur du récapitulatif éditable.

### Métriques Clés du Pilote :
- **Taux de complétude Léa** : % des onboardings complétés sans abandon.
- **Taux de correction manuelle** : Nombre de champs modifiés par l'utilisateur avant confirmation (cible ≤ 1 champ modifié en moyenne).
- **Taux de bascule en fallback** : Cible < 15% sur les 100 premiers tenants.

---

## 6. Sécurité, Rétention & Multi-Tenant

- **Zéro écriture par l'IA** : L'IA ne produit qu'un payload transitoire en mémoire.
- **Exécution d'activation** : Seul l'appel explicite de l'utilisateur à `CompleteSetupInterview` avec son token de session déclenche `SolutionActivator`.
- **Rétention des prompts** : Aucune donnée conversationnelle n'est conservée à long terme en base sans consentement explicite. Tout prompt transitoire est purgé post-session.
