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

    public function test_sanitize_messages_recurses_into_tool_result_arrays(): void
    {
        $sanitizer = new PrivacySanitizer;

        $messages = [
            ['role' => 'system', 'content' => 'Sois bref.'],
            ['role' => 'user', 'content' => 'écris à a.b@c.fr'],
            ['role' => 'assistant', 'content' => ['type' => 'tool_use', 'id' => 'x', 'input' => []]],
        ];

        $cleaned = $sanitizer->sanitizeMessages($messages);

        $this->assertSame('Sois bref.', $cleaned[0]['content']);
        $this->assertSame('écris à [email]', $cleaned[1]['content']);
        $this->assertSame(['type' => 'tool_use', 'id' => 'x', 'input' => []], $cleaned[2]['content']);
    }

    /**
     * BOS-001 (#8141) — le trou de la v1 : la branche Claude envoie ses
     * `tool_result` en TABLEAU de blocs ; un résultat d'outil contenant des PII
     * partait non sanitisé vers Anthropic. Les clés de structure doivent être
     * préservées à l'identique (sinon le protocole provider casse).
     */
    public function test_sanitize_messages_redacts_pii_nested_in_tool_result_blocks(): void
    {
        $sanitizer = new PrivacySanitizer;

        $messages = [[
            'role' => 'user',
            'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => 'toolu_1',
                'is_error' => false,
                'content' => '{"employee":{"email":"jean.dupont@exemple.fr","phone":"+33 6 12 34 56 78"}}',
            ]],
        ]];

        $cleaned = $sanitizer->sanitizeMessages($messages);

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $cleaned[0]['content'];
        $block = $blocks[0];
        $content = $block['content'];
        $this->assertIsString($content);

        $this->assertSame('tool_result', $block['type']);
        $this->assertSame('toolu_1', $block['tool_use_id']);
        $this->assertFalse($block['is_error']);
        $this->assertStringNotContainsString('jean.dupont@exemple.fr', $content);
        $this->assertStringNotContainsString('+33 6 12 34 56 78', $content);
        $this->assertStringContainsString('[email]', $content);
        $this->assertStringContainsString('[téléphone]', $content);
    }

    /**
     * BOS-001 (#8141) — les clés de structure d'un tableau ne sont jamais
     * réécrites, même lorsqu'elles ressemblent à une PII ('email', 'phone').
     */
    public function test_sanitize_messages_preserves_structural_keys(): void
    {
        $sanitizer = new PrivacySanitizer;

        $messages = [[
            'role' => 'user',
            'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => ['email' => 'a.b@c.fr']]],
        ]];

        $cleaned = $sanitizer->sanitizeMessages($messages);

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $cleaned[0]['content'];
        $inner = $blocks[0]['content'];
        $this->assertIsArray($inner);
        $this->assertArrayHasKey('email', $inner, 'la clé de structure ne doit jamais être réécrite');
        $this->assertSame('[email]', $inner['email']);
    }
}
