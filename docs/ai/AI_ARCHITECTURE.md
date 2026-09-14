# AI Architecture & Predictive Insights — Leopardo RH

Leopardo RH integrates advanced AI layers to transform raw HR data into actionable workforce intelligence. Our AI strategy focuses on **Augmentation**, not replacement.

## 🤖 The AI Orchestrator

The `App\AI\Orchestrator` is the central hub for all intelligent features. It manages the lifecycle of AI requests, from data ingestion to LLM processing and response delivery.

### Key Components:
-   **Context Provider:** Collects tenant-scoped data (attendance logs, employee profiles) through the
    same read tools the REST API uses — never raw table dumps.
-   **Model Wrapper:** `App\AI\LLMClient` with plain HTTP adapters for OpenAI, Groq and Anthropic
    (`App\AI\Providers\*`). There is no LangChain dependency and no self-hosted Llama integration.
-   **Prompt Templates:** A versioned system prompt loaded from `resources/ai/system_prompt.md`.

---

## 📈 Predictive Capabilities

Everything in this section is **deterministic SQL over tenant data** — no model is called. It is
grouped here for product reasons, not because it is machine learning.

### 1. Attendance Anomaly Detection
-   **Pattern Matching:** Flags employees with no check-in and no approved absence over a period.
-   **Severity Levels:** Rules assign a severity to each anomaly; human review is expected.
-   **Geo-Verification:** GPS-fenced check-in validation exists **as a product rule**, not as an AI
    service — there is no AI geo-verification component in the codebase.

### 2. Payroll Readiness (not forecasting)
-   **Projection Engine:** There is no statistical payroll forecast. `PreparePayrollWorkflow`
    reports what is *missing* before closing a period (employees without a salary structure,
    pending absences) so payroll can be prepared safely.
-   **Absenteeism / turnover indicators:** `AbsenteeismPredictor` and `TurnoverPredictor` compute
    rolling averages and ratios with fixed multipliers. They are heuristics, **not regression models**.

### 3. Smart Recruitment (Roadmap)
-   **Candidate Ranking:** AI-driven scoring of applicants against job descriptions — **not implemented**.
-   **Bias Mitigation:** Not implemented.

---

## 🔒 Privacy & Ethics

-   **Tenant Isolation:** AI models never "leak" data between different companies. Your data is used only for your insights.
-   **PII Redaction:** Sensitive employee data is anonymized before being sent to external AI providers.
-   **Human in the Loop:** All AI suggestions (like anomaly flags or payroll projections) must be reviewed by an HR Manager.

---

## 🚀 Voice & Multimodal Interaction

`POST /api/v1/ai/voice/command` exists and accepts natural-language commands from mobile users:

-   "Check my remaining leave balance."
-   "Clock me in for the morning shift."
-   "Show me the attendance report for last week."

**Status: experimental and fail-closed.** Speech-to-text and text-to-speech require a configured
provider (Groq Whisper for STT; ElevenLabs or the external `edge-tts` binary for TTS). Without one,
both endpoints answer `503 STT_UNAVAILABLE` / `503 TTS_UNAVAILABLE` — they never return a fake
transcript or a silent `null` audio. Per `docs/ai/STATUS.md`, voice is **out of the FOCUS critical
path** and carries no customer-support commitment.

---

For technical implementation details, see `docs/ai/README.md`.
