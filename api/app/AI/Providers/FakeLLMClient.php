<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;

/**
 * Driver LLM FAKE — réservé aux tests et aux environnements hors production
 * sans clé (issue #6848 : `AI_LLM_DRIVER=fake`).
 *
 * Répond sans aucun appel réseau : écho du dernier message utilisateur en
 * contenu, zéro tool_call. Les tests qui doivent scripter des propositions
 * d'outils remplacent `LLMClient` par une instance dédiée
 * (`$this->app->instance(LLMClient::class, …)` — pattern existant dans
 * `tests/Feature/AI/*`).
 */
class FakeLLMClient implements LLMClient
{
    public function chat(array $messages, array $tools = []): AIResponse
    {
        $lastUser = '';
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] !== 'user') {
                continue;
            }
            if (is_scalar($message['content'] ?? null)) {
                $lastUser = (string) $message['content'];
            }

            break;
        }

        return new AIResponse(
            content: $lastUser === ''
                ? 'Assistant (fake) : aucune consigne reçue.'
                : 'Assistant (fake) — réponse simulée à : '.$lastUser,
            model: 'fake',
        );
    }

    public function provider(): string
    {
        return 'fake';
    }
}
