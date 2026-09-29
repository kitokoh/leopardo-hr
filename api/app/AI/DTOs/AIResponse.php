<?php

namespace App\AI\DTOs;

class AIResponse
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  int|null  $status  Statut HTTP du provider (null hors appel HTTP) — sert à la
     *                            fois d'observabilité et de critère de réessai (BOS-031).
     * @param  string|null  $provider  Provider ayant réellement servi la réponse
     *                                 (`ResilientLLMClient` en fallback, #8221).
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly string $model = '',
        public readonly ?string $error = null,
        public readonly ?int $status = null,
        public readonly ?string $provider = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /**
     * BOS-031 (#8221) — cet échec mérite-t-il un réessai (ou un fallback) ?
     *
     * Politique : 429 (quota/débit) et 5xx (panne provider) sont retryables ;
     * tout le reste (401/403/404/422, absence de clé, réponse métier invalide)
     * ne l'est pas — réessayer ne changerait rien. Un échec sans statut HTTP
     * (transport) est traité comme retryable par les clients, qui posent un
     * statut 503 dans ce cas.
     */
    public function isRetryable(): bool
    {
        if ($this->error === null || $this->status === null) {
            return false;
        }

        return $this->status === 429 || $this->status >= 500;
    }
}
