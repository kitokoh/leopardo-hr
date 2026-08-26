<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Recruitment\Interfaces\Api\V1\Requests\PublicApplyRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Issue #5588 (audit sécurité 2026-08-26) — `resume_url` (portail carrières
 * public) doit porter la garde anti-SSRF `NotPrivateUrl` : https uniquement,
 * hôte public résolu (RFC 1918, loopback, link-local, 169.254.169.254,
 * hôtes .local/.internal/.lan refusés). Le champ n'est jamais fetché
 * aujourd'hui, mais un futur job de parsing de CV ne doit pas pouvoir lire
 * le réseau interne.
 */
class PublicApplyResumeUrlGuardTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function validator(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, (new PublicApplyRequest())->rules());
    }

    public function test_private_ipv4_resume_url_is_rejected(): void
    {
        $v = $this->validator(['resume_url' => 'https://192.168.1.10/cv.pdf']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('resume_url', $v->errors()->toArray());
    }

    public function test_cloud_metadata_address_is_rejected(): void
    {
        $v = $this->validator(['resume_url' => 'https://169.254.169.254/latest/meta-data/']);
        $this->assertTrue($v->fails());
    }

    public function test_loopback_host_is_rejected(): void
    {
        $v = $this->validator(['resume_url' => 'https://localhost/cv.pdf']);
        $this->assertTrue($v->fails());
    }

    public function test_plain_http_url_is_rejected(): void
    {
        $v = $this->validator(['resume_url' => 'http://example.com/cv.pdf']);
        $this->assertTrue($v->fails());
    }

    public function test_public_https_url_is_accepted(): void
    {
        // Hôte fictif autorisé en environnement de test (règle NotPrivateUrl,
        // hôtes .example.com acceptés en testing uniquement — zéro risque
        // SSRF, aucun résolution DNS requise).
        $v = $this->validator(['resume_url' => 'https://cv.example.com/resume.pdf']);
        $this->assertFalse($v->fails());
    }

    public function test_missing_resume_url_is_still_accepted(): void
    {
        $v = $this->validator([
            'first_name' => 'Karim',
            'last_name' => 'Ben',
            'email' => 'karim@example.com',
        ]);
        $this->assertFalse($v->fails());
    }
}
