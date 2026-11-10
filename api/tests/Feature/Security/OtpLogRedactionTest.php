<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Infrastructure\Sms\LogOnlySmsCodeSender;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * #9355 (audit sécurité 2026-11-10, F13) — garde anti-régression sur les logs
 * OTP.
 *
 * La lecture du code confirme qu'aucun `Log::*` de `LogOnlySmsCodeSender` ne
 * trace le code en clair : seuls le numero masque, la locale et un `trace_id`
 * derive d'un SHA-256 y figurent. Rien ne l'empêchait pour autant de régresser
 * (un `Log::info($code, …)` ajouté lors d'un debug, par exemple).
 *
 * Ce test capture le canal de log pendant un envoi réel et échoue si un code
 * à 6 chiffres apparaît dans les traces.
 */
class OtpLogRedactionTest extends TestCase
{
    public function test_no_six_digit_code_reaches_the_log_when_sms_is_not_configured(): void
    {
        // Transport non configuré → mode audit-only : c'est le chemin qui
        // journalise, donc celui qu'il faut surveiller.
        config([
            'services.sms_otp.endpoint' => null,
            'services.sms_otp.api_key' => null,
        ]);

        $captured = [];

        Log::listen(static function ($event) use (&$captured): void {
            $captured[] = $event->message.' '.json_encode($event->context);
        });

        $sender = app(LogOnlySmsCodeSender::class);

        // Numéro jetable au format E.164 (Algérie, comme les fixtures du repo).
        $sender->send('+213555123456', '482913', 'fr');

        $joined = implode("\n", $captured);

        $this->assertNotSame('', $joined, 'Le mode audit-only doit journaliser le FAIT de la demande.');

        $this->assertDoesNotMatchRegularExpression(
            '/(?<!\d)482913(?!\d)/',
            $joined,
            'Un code OTP en clair a atteint le journal (#9355).',
        );

        $this->assertStringNotContainsString('+213555123456', $joined, 'Le numéro complet doit être masqué.');
    }
}
