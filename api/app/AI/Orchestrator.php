<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\DTOs\AIRequest;
use App\AI\Exceptions\TokenBudgetExceededException;
use App\AI\Privacy\AiCloudPolicy;
use App\AI\Privacy\PrivacySanitizer;
use App\AI\Support\UntrustedToolContent;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\File;

class Orchestrator
{
    /**
     * C1 (#6859) — borne de la boucle d'outils du chat « commande » (EPIC
     * #6846) : 3 rounds d'exécution d'outils après l'appel LLM initial =
     * 4 échanges maximum (spec §6 : « boucle ≤ 4 itérations »). Au-delà,
     * plus aucun outil n'est exécuté — arrêt propre avec réponse finale.
     */
    private const MAX_TOOL_ITERATIONS = 3;

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly IntentEngine $intentEngine,
        private readonly MemoryManager $memoryManager,
        private readonly AIAuditLogger $auditLogger,
        private readonly LLMClient $client,
        // BC-23-D10 (issue #6238) : budgets de tokens versionnés.
        private readonly TokenBudgetGuard $tokenBudgetGuard,
        // A6 (#6853) : politique cloud (flag tenant ai_cloud_allowed) + minimisation RGPD.
        private readonly AiCloudPolicy $cloudPolicy,
        private readonly PrivacySanitizer $privacySanitizer,
    ) {}

    /**
     * @return array{conversation_id: int, response: string, tools_used: array<int, string>, tokens: array{input: int, output: int}}
     */
    public function handle(AIRequest $request): array
    {
        $startTime = hrtime(true);

        $conversation = $this->memoryManager->loadOrCreateConversation(
            $request->userId,
            $request->companyId,
            $request->conversationId,
            ['company_id' => $request->companyId],
        );

        $conversationId = $conversation['id'];

        try {
            // BC-23-D10 : fail-closed sur le cumul de la conversation — au-delà
            // du budget de contexte, on refuse de continuer (nouvelle conversation).
            // A6 (#6853) — politique cloud : driver cloud sans flag tenant
            // `ai_cloud_allowed` → réponse explicite, aucun appel externe.
            if ($this->cloudPolicy->isCloudDriver($this->client->provider())) {
                $company = Company::query()->find($request->companyId);
                if (! $this->cloudPolicy->cloudAllowed($company)) {
                    return $this->cloudRefusal($request, $conversationId, $startTime, $company);
                }
            }

            if ($this->tokenBudgetGuard->contextBudgetExceeded($conversation['token_count'])) {
                throw new TokenBudgetExceededException(
                    sprintf(
                        'AI conversation context token budget exceeded (%d > %d); start a new conversation.',
                        $conversation['token_count'],
                        $this->tokenBudgetGuard->maxContextTokens(),
                    )
                );
            }

            $messages = $conversation['messages'];
            $toolsUsed = [];
            $pendingConfirmations = [];

            $systemPrompt = $this->loadSystemPrompt($request->companyId);
            // BOS-034 (#8223) — anti-injection du chat principal : le prompt
            // système porte l'instruction dédiée (les blocs marqués sont des
            // DONNÉES, jamais des ordres), calquée sur le pipeline email.
            $llmMessages = [['role' => 'system', 'content' => $systemPrompt."\n\n".UntrustedToolContent::systemInstruction()]];

            foreach ($messages as $msg) {
                $llmMessages[] = $msg;
            }
            $llmMessages[] = ['role' => 'user', 'content' => $request->message];

            $userRole = $this->resolveUserRole($request->userId, $request->companyId);
            $tools = $this->toolRegistry->getToolsAsLLMFormat($userRole, $request->companyId);

            // Issue #5625 : filtrer les outils sans handler PHP pour ne pas promettre
            // au LLM (et donc à l'utilisateur) des fonctionnalités inexistantes.
            $implementedToolNames = array_merge(
                IntentEngine::supportedReadToolNames(),
                WriteActionRunner::supportedWriteToolNames(),
            );
            $tools = array_values(
                array_filter(
                    $tools,
                    static fn (array $t): bool => is_array($t['function'] ?? null)
                        && in_array($t['function']['name'] ?? '', $implementedToolNames, true),
                ),
            );

            $response = $this->chat($llmMessages, $tools);

            $iteration = 0;

            // BC-23-D10 : cumul de tokens de LA requête courante (toutes itérations).
            $requestTokens = 0;

            while ($response->hasToolCalls() && $iteration < self::MAX_TOOL_ITERATIONS) {
                $requestTokens += $response->inputTokens + $response->outputTokens;
                $this->tokenBudgetGuard->assertRequestWithinBudget($requestTokens, 0);

                $results = $this->intentEngine->executeToolCalls($response, $request->companyId, $request->userId, $conversationId);

                // A5 (#6852) : chaque outil exécuté ou proposé est journalisé
                // (ai_tool_executions) avec le contexte de conversation — la
                // chaîne conversation → action → effet devient rejouable.
                foreach ($response->toolCalls as $index => $toolCall) {
                    $result = $results[$index] ?? null;
                    if ($result === null) {
                        continue;
                    }

                    $decoded = json_decode($result->content, true);
                    $confirmationRequired = is_array($decoded) && ($decoded['status'] ?? null) === 'confirmation_required';

                    $this->auditLogger->logToolExecution(
                        companyId: $request->companyId,
                        userId: $request->userId,
                        conversationId: $conversationId,
                        pendingActionId: is_array($decoded) && is_string($decoded['pending_action_id'] ?? null)
                            ? $decoded['pending_action_id']
                            : null,
                        toolName: $toolCall->name,
                        toolInput: $toolCall->arguments,
                        stage: $confirmationRequired
                            ? 'confirmation_required'
                            : ($result->success ? 'executed' : 'error'),
                        success: $result->success,
                        resultSummary: $result->content,
                    );
                }

                foreach ($results as $result) {
                    $toolsUsed[] = $result->name;
                    $decoded = json_decode($result->content, true);
                    if (is_array($decoded) && ($decoded['status'] ?? null) === 'confirmation_required') {
                        $pendingConfirmations[] = $decoded;
                    }
                }

                if ($pendingConfirmations !== []) {
                    break;
                }

                if ($this->client->provider() === 'claude') {
                    $llmMessages[] = [
                        'role' => 'assistant',
                        'content' => array_map(fn ($call) => [
                            'type' => 'tool_use',
                            'id' => $call->id,
                            'name' => $call->name,
                            'input' => $call->arguments,
                        ], $response->toolCalls),
                    ];

                    $llmMessages[] = [
                        'role' => 'user',
                        // BOS-034 (#8223) — chaque tool result est encadré de
                        // délimiteurs hostiles-neutralisés avant réinjection.
                        'content' => array_map(fn ($result) => [
                            'type' => 'tool_result',
                            'tool_use_id' => $result->toolCallId,
                            'content' => UntrustedToolContent::frame($result->name, $result->content),
                            'is_error' => ! $result->success,
                        ], $results),
                    ];
                } else {
                    foreach ($results as $result) {
                        $llmMessages[] = ['role' => 'assistant', 'content' => "Tool call: {$result->name}"];
                        $llmMessages[] = ['role' => 'user', 'content' => "Tool result:\n".UntrustedToolContent::frame($result->name, $result->content)];
                    }
                }

                $response = $this->chat($llmMessages, $tools);
                $iteration++;
            }

            // Dernier appel LLM de la requête (le seul si aucun tool call).
            $requestTokens += $response->inputTokens + $response->outputTokens;
            $this->tokenBudgetGuard->assertRequestWithinBudget($requestTokens, 0);

            // C1 (#6859) — arrêt propre à la borne d'itérations : si le modèle
            // demande encore des outils SANS avoir produit de texte final, on
            // n'exécute rien de plus et on rend une réponse explicite en
            // français (état des exécutions déjà faites) au lieu d'une réponse
            // vide. Cas « confirmation requise » : non concerné (break avant la
            // borne, réponse pilotée par pending_confirmations côté client).
            $finalContent = $response->hasToolCalls()
                && $iteration >= self::MAX_TOOL_ITERATIONS
                && trim($response->content) === ''
                ? $this->iterationLimitResponse($toolsUsed)
                : $response->content;

            $messages[] = ['role' => 'user', 'content' => $request->message];
            $messages[] = ['role' => 'assistant', 'content' => $finalContent];

            $totalTokens = $response->inputTokens + $response->outputTokens;
            $this->memoryManager->saveMessages($conversationId, $messages, $totalTokens);

            if (count($messages) <= 2) {
                $title = mb_substr($request->message, 0, 100);
                $this->memoryManager->updateTitle($conversationId, $title);
            }

            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            $this->auditLogger->log(
                companyId: $request->companyId,
                userId: $request->userId,
                conversationId: $conversationId,
                prompt: $request->message,
                response: $finalContent,
                toolsCalled: $toolsUsed,
                // BOS-031 (#8221, FR-9) — le provider AUDITÉ est celui qui a
                // réellement servi la réponse (`AIResponse::provider` renseigné
                // par le décorateur en cas de fallback) ; repli sur le client
                // configuré quand l'information n'est pas portée.
                provider: $response->provider ?? $this->client->provider(),
                model: $response->model,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                durationMs: $durationMs,
                error: $response->error,
                workflow: $request->workflow,
            );

            return [
                'conversation_id' => $conversationId,
                'response' => $finalContent,
                'tools_used' => $toolsUsed,
                'pending_confirmations' => $pendingConfirmations,
                'tokens' => ['input' => $response->inputTokens, 'output' => $response->outputTokens],
            ];
        } catch (TokenBudgetExceededException $exception) {
            // BC-23-D10 : le rejet de budget est tracé dans l'audit AI (erreur)
            // pour l'observabilité (analytics errors) — puis remonté fail-closed.
            $this->auditLogger->log(
                companyId: $request->companyId,
                userId: $request->userId,
                conversationId: $conversationId,
                prompt: $request->message,
                response: '',
                toolsCalled: $toolsUsed ?? [],
                provider: $this->client->provider(),
                model: '',
                inputTokens: 0,
                outputTokens: 0,
                durationMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
                error: $exception->getMessage(),
                workflow: $request->workflow,
            );

            throw $exception;
        }
    }

    /**
     * A6 (#6853) — appel LLM avec minimisation RGPD : les messages envoyés
     * vers un driver cloud passent par PrivacySanitizer (texte seulement).
     */
    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    private function chat(array $messages, array $tools): \App\AI\DTOs\AIResponse
    {
        if ($this->cloudPolicy->isCloudDriver($this->client->provider())) {
            $messages = $this->privacySanitizer->sanitizeMessages($messages);
        }

        return $this->client->chat($messages, $tools);
    }

    /**
     * A6 (#6853) — refus explicite d'envoi cloud (flag tenant inactif) :
     * réponse en clair + audit d'erreur, aucun appel externe, aucune écriture
     * de conversation.
     *
     * @return array<string, mixed>
     */
    /**
     * @return array{conversation_id: int, response: string, tools_used: array<int, string>, pending_confirmations: array<int, mixed>, tokens: array{input: int, output: int}}
     */
    private function cloudRefusal(AIRequest $request, int $conversationId, int|float $startTime, ?Company $company): array
    {
        $message = $this->cloudPolicy->refusalMessage();

        $this->auditLogger->log(
            companyId: $request->companyId,
            userId: $request->userId,
            conversationId: $conversationId,
            prompt: $request->message,
            response: '',
            toolsCalled: [],
            provider: $this->client->provider(),
            model: '',
            inputTokens: 0,
            outputTokens: 0,
            durationMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
            error: 'AI_CLOUD_NOT_ALLOWED (tenant='.($company !== null ? (string) $company->id : '?').')',
            workflow: $request->workflow,
        );

        return [
            'conversation_id' => $conversationId,
            'response' => $message,
            'tools_used' => [],
            'pending_confirmations' => [],
            'tokens' => ['input' => 0, 'output' => 0],
        ];
    }

    /**
     * C1 (#6859) — réponse de fin de boucle quand la borne d'itérations est
     * atteinte sans texte final du modèle : liste honnête (noms d'outils
     * uniquement, aucune donnée métier/PII) des exécutions déjà effectuées.
     *
     * @param  array<int, string>  $toolsUsed
     */
    private function iterationLimitResponse(array $toolsUsed): string
    {
        $summary = $toolsUsed !== []
            ? ' Outils déjà exécutés : '.implode(', ', array_unique($toolsUsed)).'.'
            : '';

        return "J'ai atteint la limite d'itérations autorisée pour cette demande (4 échanges maximum).".$summary.' Reformule ou précise ta demande pour continuer.';
    }

    private function loadSystemPrompt(string $companyId): string
    {
        /** @var string $path */
        $path = config('ai.system_prompt_path', resource_path('ai/system_prompt.md'));

        if (File::exists($path)) {
            return File::get($path);
        }

        return "Tu es l'assistant IA de Leopardo RH. Tu aides les managers et employes a gerer les ressources humaines. Reponds en francais sauf si l'utilisateur parle une autre langue. Sois concis et professionnel.";
    }

    private function resolveUserRole(int $userId, string $companyId): string
    {
        $employee = Employee::where('id', $userId)
            ->where('company_id', $companyId)
            ->first();

        if (! $employee) {
            return 'employee';
        }

        if ($employee->isManager()) {
            return 'manager';
        }

        return 'employee';
    }
}
