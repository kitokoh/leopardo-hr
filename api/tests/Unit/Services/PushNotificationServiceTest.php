<?php

namespace Tests\Unit\Services;

use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.firebase.project_id', 'test-project-id');
        // Mock the access token in cache to skip the complex OAuth2 flow in tests
        Cache::put('firebase_access_token', 'mock-access-token', 3600);
    }

    public function test_send_to_tokens_returns_zero_if_no_project_id(): void
    {
        Config::set('services.firebase.project_id', null);

        $service = new PushNotificationService();
        $result = $service->sendToTokens(['token1'], 'Title', 'Body');

        $this->assertEquals(0, $result);
    }

    public function test_send_to_tokens_dispatches_http_requests_to_fcm(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/test-project-id/messages:send' => Http::response(['name' => 'messages/123'], 200),
        ]);

        $service = new PushNotificationService();
        $result = $service->sendToTokens(['token1', 'token2'], 'Test Title', 'Test Body', ['key' => 'value']);

        $this->assertEquals(2, $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project-id/messages:send' &&
                   $request->hasHeader('Authorization', 'Bearer mock-access-token') &&
                   $request['message']['notification']['title'] === 'Test Title' &&
                   $request['message']['data']['key'] === 'value';
        });
    }

    public function test_send_to_tokens_handles_fcm_errors_gracefully(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/test-project-id/messages:send' => Http::sequence()
                ->push(['error' => ['status' => 'INVALID_ARGUMENT']], 400)
                ->push(['name' => 'messages/456'], 200),
        ]);

        $service = new PushNotificationService();

        // We expect 1 success out of 2 tokens
        $result = $service->sendToTokens(['invalid-token', 'valid-token'], 'Title', 'Body');

        $this->assertEquals(1, $result);
    }
}
