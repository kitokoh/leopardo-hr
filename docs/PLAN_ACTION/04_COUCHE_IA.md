# 04 — COUCHE IA

**Objectif :** Implementer l'architecture IA-native parallele decrite dans le document de reference (13 chapitres, 55 taches, 5 phases). En parallele, utiliser les LLMs existants (ChatGPT, Claude) comme moteur avant de construire des modeles proprietaires.

**Document de reference :** `Leopardo_RH_Architecture_IA_Native.pdf`

---

## 1. Principe fondateur (rappel du PDF)

- La couche IA est **ADDITIVE** — elle n'accede jamais directement a la base de donnees
- Elle passe TOUJOURS par la **Business Actions Layer** qui valide permissions, tenant et parametres
- Le systeme classique continue de fonctionner sans modification
- 2 modes coexistent : Mode Classique (inchange) ET Mode IA (chat/voix/agents)
- Routes IA separees : `/api/ai/*` (pas de conflit avec `/api/v1/*`)

---

## 2. Phase 1 — Chat IA basique (Priorite HAUTE)

### Objectif

Un chat textuel dans le dashboard et l'app mobile. L'utilisateur pose une question en langue naturelle, l'IA repond en utilisant les donnees de son tenant.

### Strategie interim : utiliser GPT-4o / Claude comme moteur

Pas besoin de construire un modele. L'architecture utilise l'API OpenAI ou Anthropic avec tool calling. Le LLM est interchangeable grace a l'abstraction `LLMClient`.

### Composants a creer

```
api/app/AI/
    Orchestrator.php          # Cerveau central — gere le flow complet
    IntentEngine.php          # Detection d'intention (via LLM tool calling)
    ToolRegistry.php          # Catalogue des outils disponibles
    MemoryManager.php         # Gestion du contexte conversationnel
    AIAuditLogger.php         # Audit trail IA
    LLMClient.php             # Interface abstraction LLM
    Providers/
        ClaudeClient.php      # Implementation Claude API
        OpenAIClient.php      # Implementation OpenAI API
    DTOs/
        AIRequest.php
        AIResponse.php
        ToolCall.php
        ToolResult.php

api/app/Http/Controllers/AI/
    AIGatewayController.php   # POST /api/ai/chat, GET /api/ai/chat/history
    VoiceController.php       # Phase 3

api/app/Http/Middleware/
    AIRateLimiter.php         # Quotas par plan SaaS
    AITenantInjector.php      # Contexte tenant pour l'IA
    AIFeatureCheck.php        # Verifie que le plan inclut l'IA

api/routes/ai.php             # Routes IA separees
```

### Tables a creer

```
ai_conversations
  - id, company_id, user_id
  - title (auto-generated)
  - messages (JSON: [{role, content, tool_calls, created_at}])
  - context (JSON: {company_name, user_role, recent_actions})
  - token_count (integer)
  - created_at, updated_at

ai_audit_logs
  - id, company_id, user_id
  - conversation_id
  - prompt (text)
  - response (text)
  - tools_called (JSON)
  - provider (string: claude, openai)
  - model (string)
  - input_tokens (integer)
  - output_tokens (integer)
  - cost_cents (integer)
  - duration_ms (integer)
  - error (text, nullable)
  - created_at

ai_tool_registry
  - id
  - name (string: get_employees, create_absence, get_attendance_anomalies)
  - description (text — pour le LLM)
  - parameters (JSON schema)
  - required_permissions (JSON: ["employees.view"])
  - required_role (enum: employee, manager, admin, super_admin)
  - module (string: rh, attendance, payroll, etc.)
  - active (boolean)
  - created_at, updated_at
```

### Outils IA (Tool Registry) — Phase 1

| Outil | Description | Permissions | Action existante |
|-------|-------------|-------------|------------------|
| `get_employees` | Lister les employes avec filtres | employees.view | EmployeeController@index |
| `get_employee_details` | Detail d'un employe | employees.view | EmployeeController@show |
| `get_attendance_today` | Presences du jour | attendance.view | AttendanceController@today |
| `get_attendance_anomalies` | Anomalies de presence | attendance.view | AttendanceController@anomalies |
| `get_monthly_report` | Rapport mensuel | attendance.view | AttendanceController@monthlyReport |
| `get_absences` | Liste des absences | absences.view | AbsenceController@index |
| `create_absence` | Creer une demande d'absence | absences.create | AbsenceController@store |
| `approve_absence` | Approuver une absence | absences.approve | AbsenceController@approve |
| `get_daily_summary` | Resume quotidien d'un employe | estimations.view | EstimationController@dailySummary |
| `get_notifications` | Notifications non lues | notifications.view | NotificationController@index |
| `get_departments` | Liste des departements | departments.view | DepartmentController@index |
| `get_leave_balances` | Soldes de conges | leave.view | (nouveau module) |
| `get_payroll_summary` | Resume paie | payroll.view | (nouveau module) |
| `get_headcount` | Effectifs | reports.view | (nouveau module) |
| `search_employees` | Recherche employe par nom/poste | employees.view | (query) |

### Endpoints Phase 1

```
POST   /api/ai/chat                               # Envoyer un message
GET    /api/ai/chat/history                        # Historique conversations
DELETE /api/ai/chat/{conversationId}               # Supprimer conversation
GET    /api/ai/tools                               # Liste outils disponibles (pour debug/admin)
```

### Configuration

```php
// config/ai.php
return [
    'enabled' => env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai'),  // openai | claude | local
    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('AI_MODEL', 'gpt-4o'),
        ],
        'claude' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_MODEL', 'claude-sonnet-4-20250514'),
        ],
    ],
    'max_tokens' => 1024,
    'temperature' => 0.3,
    'system_prompt_path' => resource_path('ai/system_prompt.md'),
    'quotas' => [
        'trial' => 10,
        'starter' => 50,
        'business' => 200,
        'enterprise' => null,
    ],
];
```

### Taches Phase 1

- [x] **T-IA-01** : Creer `config/ai.php` — **FAIT**
- [x] **T-IA-02** : Creer les migrations (ai_conversations, ai_audit_logs, ai_tool_registry) — **FAIT** (`2026_05_11_000001_create_ai_tables.php`)
- [x] **T-IA-03** : Implementer `LLMClient` interface + `OpenAIClient` + `ClaudeClient` — **FAIT** (`app/AI/LLMClient.php`, `app/AI/Providers/OpenAIClient.php`, `app/AI/Providers/ClaudeClient.php`)
- [x] **T-IA-04** : Implementer `ToolRegistry` — **FAIT** (`app/AI/ToolRegistry.php`)
- [x] **T-IA-05** : Implementer `IntentEngine` — **FAIT** (`app/AI/IntentEngine.php`)
- [x] **T-IA-06** : Implementer `MemoryManager` — **FAIT** (`app/AI/MemoryManager.php`)
- [x] **T-IA-07** : Implementer `AIOrchestrator` — **FAIT** (`app/AI/Orchestrator.php`)
- [x] **T-IA-08** : Implementer `AIAuditLogger` — **FAIT** (`app/AI/AIAuditLogger.php`)
- [x] **T-IA-09** : Creer les 3 middlewares — **FAIT** (`AIRateLimiter.php`, `AITenantInjector.php`, `AIFeatureCheck.php` + `EnsureAIAnalyticsAccess.php`)
- [x] **T-IA-10** : Creer `routes/ai.php` + `AIGatewayController` — **FAIT**
- [x] **T-IA-11** : Seeder pour les 15 outils du Tool Registry — **FAIT** (`database/seeders/AIToolRegistrySeeder.php`)
- [x] **T-IA-12** : Creer le system prompt dans `resources/ai/system_prompt.md` — **FAIT**
- [x] **T-IA-13** : Tests Feature (chat endpoint, quota enforcement, tool calling) — **FAIT** (`tests/Feature/AIGatewayAndAnalyticsTest.php`)
- [x] **T-IA-14** : Tests Unit (orchestrator, intent engine, tool registry) — **FAIT** (couverts dans AIGatewayAndAnalyticsTest)

---

## 3. Phase 2 — Tool Calling avance + Analytics

### Objectif

L'IA peut executer des actions (creer absence, modifier employe), pas seulement lire. Dashboard analytics IA pour le super-admin.

### Outils supplementaires

| Outil | Description | Type |
|-------|-------------|------|
| `create_employee` | Creer un employe | write |
| `update_employee` | Modifier un employe | write |
| `check_in_employee` | Pointer l'arrivee | write |
| `check_out_employee` | Pointer le depart | write |
| `create_salary_advance` | Demander une avance | write |
| `generate_report` | Generer un rapport | read |
| `get_org_chart` | Afficher l'organigramme | read |

### Analytics IA (super-admin)

```
GET    /api/ai/analytics/usage                    # Utilisation par tenant
GET    /api/ai/analytics/costs                    # Couts LLM par periode
GET    /api/ai/analytics/tools                    # Outils les plus utilises
GET    /api/ai/analytics/errors                   # Erreurs et taux de succes
```

### Taches Phase 2

- [ ] **T-IA-15** : Implementer les outils write avec confirmation utilisateur
- [ ] **T-IA-16** : Ajouter le mecanisme de confirmation (l'IA demande confirmation avant d'executer une action write)
- [x] **T-IA-17** : Creer les endpoints analytics IA — **FAIT** (`AIAnalyticsController.php` : usage, costs, tools, errors)
- [ ] **T-IA-18** : Dashboard admin pour visualiser les analytics
- [ ] **T-IA-19** : Tests pour les actions write avec confirmation

---

## 4. Phase 3 — Voice IA

### Objectif

Les managers terrain peuvent parler a l'IA en francais, arabe, turc ou anglais. Speech-to-Text + IA + Text-to-Speech.

### Architecture

```
Audio -> STT (Deepgram/Whisper) -> Texte -> IntentEngine -> Action -> Texte reponse -> TTS (ElevenLabs/Edge TTS) -> Audio
```

### Services externes

| Service | Usage | Alternative gratuite |
|---------|-------|---------------------|
| Deepgram | STT (transcription) | Whisper API (OpenAI) |
| ElevenLabs | TTS (synthese vocale) | Edge TTS (Microsoft, gratuit) |

### Endpoints Phase 3

```
POST   /api/ai/voice/transcribe                   # Audio -> Texte
POST   /api/ai/voice/synthesize                   # Texte -> Audio
POST   /api/ai/voice/command                      # Audio -> Action -> Audio (pipeline complet)
```

### Taches Phase 3

- [x] **T-IA-20** : Implementer `VoiceController` — **FAIT** (`app/Http/Controllers/AI/VoiceController.php`)
- [ ] **T-IA-21** : Integrer Whisper API pour STT
- [ ] **T-IA-22** : Integrer Edge TTS pour synthese vocale (gratuit)
- [ ] **T-IA-23** : Pipeline voice complet (audio in -> action -> audio out)
- [ ] **T-IA-24** : Support 4 langues (FR, AR, TR, EN)
- [ ] **T-IA-25** : Tests Feature voice pipeline

---

## 5. Phase 4 — Agents autonomes

### Objectif

L'IA peut executer des workflows complexes de maniere autonome : "Prepare la paie du mois et envoie-moi le resume".

### Taches Phase 4

- [x] **T-IA-26** : Implementer le multi-step agent (sequence d'outils) — **FAIT** (`app/AI/AgentRunner.php` + `AgentController.php`)
- [ ] **T-IA-27** : Workflow "preparer la paie" (collecter donnees -> calculer -> resume)
- [ ] **T-IA-28** : Workflow "rapport hebdomadaire" (anomalies + absences + effectifs)
- [ ] **T-IA-29** : Notifications proactives (l'IA alerte le manager)

---

## 6. Phase 5 — IA Predictive

### Objectif

Predictions basees sur les donnees historiques : turnover, absenteisme, charge de travail.

### Taches Phase 5

- [ ] **T-IA-30** : Prediction turnover (employes a risque)
- [ ] **T-IA-31** : Prediction absenteisme (jours critiques)
- [ ] **T-IA-32** : Optimisation planning (repartition equipes)
- [ ] **T-IA-33** : Dashboard predictif pour les managers
