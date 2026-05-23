<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocsTest extends TestCase
{
    public function test_swagger_ui_page_is_public(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Leopardo RH API Docs')
            ->assertSee('/docs/openapi.yaml');
    }

    public function test_openapi_yaml_is_served_from_the_canonical_spec(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi: "3.0.3"', false)
            ->assertSee('Leopardo RH API', false);
    }

    public function test_root_exposes_tester_guide_and_api_explorer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Guide Testeur')
            ->assertSee('/tester-guide')
            ->assertSee('API Explorer')
            ->assertSee('/api-explorer');

        $this->get('/tester-guide')
            ->assertOk()
            ->assertSee('Guide testeur Leopardo RH')
            ->assertSee('Application mobile')
            ->assertSee('Admin plateforme');

        $this->get('/api-explorer')
            ->assertOk()
            ->assertSee('API Explorer Leopardo RH')
            ->assertSee('/demo-users')
            ->assertSee('/notifications');
    }
}
