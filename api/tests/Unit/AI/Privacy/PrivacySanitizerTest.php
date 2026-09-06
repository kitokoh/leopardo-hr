<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Privacy;

use App\AI\Privacy\PrivacySanitizer;
use Tests\TestCase;

/**
 * Issue #6853 — minimisation RGPD : PII textuelles masquées avant envoi
 * cloud, texte légitime intact.
 */
class PrivacySanitizerTest extends TestCase
{
    public function test_redacts_email(): void
    {
        $this->assertSame(
            'Contacte [email] pour la demande.',
            (new PrivacySanitizer)->sanitize('Contacte jean.dupont@exemple.fr pour la demande.')
        );
    }

    public function test_redacts_phone_numbers(): void
    {
        $sanitizer = new PrivacySanitizer;

        $this->assertSame('Son numéro : [téléphone]', $sanitizer->sanitize('Son numéro : +33 6 12 34 56 78'));
        $this->assertSame('Appelle le [téléphone]', $sanitizer->sanitize('Appelle le 0612345678'));
    }

    public function test_redacts_labelled_national_id(): void
    {
        $sanitizer = new PrivacySanitizer;

        $this->assertSame('Sa pièce : [identifiant national]', $sanitizer->sanitize('Sa pièce : national id 1 88 12 75 123 456 78'));
        $this->assertStringNotContainsString('sécurité sociale', $sanitizer->sanitize('numéro de sécurité sociale 188127512345678'));
    }

    public function test_keeps_legitimate_text_untouched(): void
    {
        $text = 'Combien d’absences cette semaine dans l’équipe du site A ?';
        $this->assertSame($text, (new PrivacySanitizer)->sanitize($text));
    }

    public function test_sanitize_messages_only_string_contents(): void
    {
        $sanitizer = new PrivacySanitizer;

        $messages = [
            ['role' => 'system', 'content' => 'Sois bref.'],
            ['role' => 'user', 'content' => 'écris à a.b@c.fr'],
            ['role' => 'assistant', 'content' => ['type' => 'tool_use', 'id' => 'x']],
        ];

        $cleaned = $sanitizer->sanitizeMessages($messages);

        $this->assertSame('Sois bref.', $cleaned[0]['content']);
        $this->assertSame('écris à [email]', $cleaned[1]['content']);
        $this->assertSame(['type' => 'tool_use', 'id' => 'x'], $cleaned[2]['content']);
    }
}
