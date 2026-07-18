<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client HTTP minimal pour l'API Ayrshare (pas de SDK officiel PHP — on
 * garde le meme parti que StripeService : appel REST direct via le
 * facade Http, sans dependance externe supplementaire).
 *
 * Auth Ayrshare : header `Authorization: Bearer {API_KEY}` (cle primaire
 * du compte Leopardo cote Ayrshare) + header `Profile-Key: {PROFILE_KEY}`
 * pour agir au nom d'un profil utilisateur (= un tenant Leopardo).
 *
 * Reference : https://www.ayrshare.com/docs/apis/overview
 */
class AyrshareClient
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.ayrshare.api_key');
        $this->baseUrl = rtrim((string) config('services.ayrshare.base_url', 'https://api.ayrshare.com/api'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Cree un nouveau profil utilisateur Ayrshare pour un tenant et retourne
     * sa Profile Key (a chiffrer/persister dans social_accounts.provider_profile_ref).
     *
     * @return array{profileKey: string, refId: string, title: string}
     */
    public function createProfile(string $title): array
    {
        $response = $this->primaryClient()
            ->post("{$this->baseUrl}/profiles/profile", [
                'title' => $title,
            ]);

        if (! $response->successful()) {
            $this->logFailure('createProfile', $response->status(), $response->json());
            throw new RuntimeException('Ayrshare: echec de creation du profil.');
        }

        $data = $response->json();

        return [
            'profileKey' => (string) ($data['profileKey'] ?? ''),
            'refId' => (string) ($data['refId'] ?? ''),
            'title' => (string) ($data['title'] ?? $title),
        ];
    }

    /**
     * Genere une URL d'onboarding (Ayrshare "Link Social Accounts" flow)
     * pour qu'un manager connecte ses reseaux sociaux depuis le profil.
     */
    public function generateJwtLoginUrl(string $profileKey, ?string $redirectUrl = null): string
    {
        $payload = ['profileKey' => $profileKey];
        if ($redirectUrl) {
            $payload['redirect'] = $redirectUrl;
        }

        $response = $this->primaryClient()
            ->post("{$this->baseUrl}/profiles/generateJWT", $payload);

        if (! $response->successful()) {
            $this->logFailure('generateJwtLoginUrl', $response->status(), $response->json());
            throw new RuntimeException('Ayrshare: echec de generation du lien de connexion.');
        }

        return (string) $response->json('url');
    }

    /**
     * Etat des reseaux connectes pour un profil (activeSocialAccounts).
     *
     * @return array<int, string>
     */
    public function connectedPlatforms(string $profileKey): array
    {
        $response = $this->profileClient($profileKey)->get("{$this->baseUrl}/user");

        if (! $response->successful()) {
            $this->logFailure('connectedPlatforms', $response->status(), $response->json());

            return [];
        }

        return array_values($response->json('activeSocialAccounts', []));
    }

    /**
     * Publie (ou planifie) un post sur les plateformes cibles au nom d'un
     * profil tenant. Le champ `scheduleDate` (ISO 8601 UTC) delegue la
     * planification a Ayrshare lui-meme ; on l'utilise en repli si le job
     * interne PublishScheduledSocialPost preferait laisser Ayrshare planifier.
     * Phase 4 utilise l'appel immediat (post du contenu au moment `due`),
     * cette methode reste generique pour couvrir les deux cas.
     *
     * @param  array<int, string>  $platforms
     * @param  array<int, string>  $mediaUrls
     * @return array{id: string, status: string, raw: array<string, mixed>}
     */
    public function publishPost(
        string $profileKey,
        string $content,
        array $platforms,
        array $mediaUrls = [],
        ?string $scheduleDateIso = null,
    ): array {
        $payload = [
            'post' => $content,
            'platforms' => $platforms,
        ];

        if ($mediaUrls !== []) {
            $payload['mediaUrls'] = $mediaUrls;
        }

        if ($scheduleDateIso !== null) {
            $payload['scheduleDate'] = $scheduleDateIso;
        }

        $response = $this->profileClient($profileKey)->post("{$this->baseUrl}/post", $payload);

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['status'] ?? null) === 'error') {
            $this->logFailure('publishPost', $response->status(), $body);
            throw new RuntimeException(
                'Ayrshare: echec de publication — '.($body['errors'][0]['message'] ?? $body['message'] ?? 'erreur inconnue')
            );
        }

        return [
            'id' => (string) ($body['id'] ?? ''),
            'status' => (string) ($body['status'] ?? 'success'),
            'raw' => $body,
        ];
    }

    private function primaryClient(): PendingRequest
    {
        return Http::withToken($this->apiKey, 'Bearer')
            ->acceptJson()
            ->asJson();
    }

    private function profileClient(string $profileKey): PendingRequest
    {
        return $this->primaryClient()->withHeaders([
            'Profile-Key' => $profileKey,
        ]);
    }

    /** @param array<string, mixed>|null $body */
    private function logFailure(string $operation, int $status, ?array $body): void
    {
        Log::error("Ayrshare: {$operation} failed", [
            'status' => $status,
            'body' => $body,
        ]);
    }
}
