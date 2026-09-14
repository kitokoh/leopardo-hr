<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

/**
 * #7384 — catalogue des réglages de l'assistant IA éditables depuis le cockpit.
 *
 * Source de vérité UNIQUE : le mapping « clé de réglage → chemin de config »,
 * le type, et le caractère secret. L'API et l'écran admin le consomment, donc
 * ajouter un réglage = ajouter une entrée ici (aucun autre fichier à toucher).
 *
 * Invariant de sécurité : `secret: true` ⇒ la valeur est chiffrée au repos et
 * n'est JAMAIS renvoyée par l'API (seulement un booléen « configurée »).
 */
final class PlatformAiSettingsCatalog
{
    /**
     * @return array<string, array{
     *     config: string,
     *     type: 'bool'|'string'|'select',
     *     secret: bool,
     *     label: string,
     *     group: string,
     *     options?: list<string>,
     *     help?: string
     * }>
     */
    public static function all(): array
    {
        return [
            'enabled' => [
                'config' => 'ai.enabled',
                'type' => 'bool',
                'secret' => false,
                'label' => 'Assistant IA activé',
                'group' => 'general',
                'help' => "Interrupteur global. Éteint, l'API répond 403 AI_FEATURE_DISABLED.",
            ],
            'driver' => [
                'config' => 'ai.driver',
                'type' => 'select',
                'secret' => false,
                'label' => 'Driver LLM',
                'group' => 'general',
                'options' => ['fake', 'groq', 'openai', 'claude'],
                'help' => "« fake » n'appelle aucun réseau et n'exécute aucun outil (tests).",
            ],
            'groq_api_key' => [
                'config' => 'ai.providers.groq.key',
                'type' => 'string',
                'secret' => true,
                'label' => 'Clé API Groq',
                'group' => 'providers',
            ],
            'groq_model' => [
                'config' => 'ai.providers.groq.model',
                'type' => 'string',
                'secret' => false,
                'label' => 'Modèle Groq',
                'group' => 'providers',
                'help' => 'Défaut gratuit et tool-calling : openai/gpt-oss-120b.',
            ],
            'openai_api_key' => [
                'config' => 'ai.providers.openai.key',
                'type' => 'string',
                'secret' => true,
                'label' => 'Clé API OpenAI',
                'group' => 'providers',
            ],
            'openai_model' => [
                'config' => 'ai.providers.openai.model',
                'type' => 'string',
                'secret' => false,
                'label' => 'Modèle OpenAI',
                'group' => 'providers',
            ],
            'anthropic_api_key' => [
                'config' => 'ai.providers.claude.key',
                'type' => 'string',
                'secret' => true,
                'label' => 'Clé API Anthropic',
                'group' => 'providers',
            ],
            'anthropic_model' => [
                'config' => 'ai.providers.claude.model',
                'type' => 'string',
                'secret' => false,
                'label' => 'Modèle Anthropic',
                'group' => 'providers',
            ],
            'stt_provider' => [
                'config' => 'ai.voice.stt_provider',
                'type' => 'string',
                'secret' => false,
                'label' => 'Transcription (STT)',
                'group' => 'voice',
            ],
            'tts_provider' => [
                'config' => 'ai.voice.tts_provider',
                'type' => 'select',
                'secret' => false,
                'label' => 'Synthèse vocale (TTS)',
                'group' => 'voice',
                'options' => ['edge_tts', 'elevenlabs'],
                'help' => 'edge_tts est gratuit et couvre le français ; ElevenLabs est payant.',
            ],
            'elevenlabs_api_key' => [
                'config' => 'ai.voice.elevenlabs_key',
                'type' => 'string',
                'secret' => true,
                'label' => 'Clé API ElevenLabs',
                'group' => 'voice',
            ],
            'deepgram_api_key' => [
                'config' => 'ai.voice.deepgram_key',
                'type' => 'string',
                'secret' => true,
                'label' => 'Clé API Deepgram',
                'group' => 'voice',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function isSecret(string $key): bool
    {
        return (bool) (self::find($key)['secret'] ?? false);
    }

    /**
     * Réglages non secrets — utilisés par l'applier (les secrets le sont aussi,
     * cf. `withSecrets()`), et par les tests.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
