<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Queue\OutboundWebhookSigner;
use App\Jobs\Services\HmacWebhookSigner;
use App\Modules\Billing\Infrastructure\Services\WebhookEnvelopeBuilder;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookDispatcher;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookSigner;
use Tests\TestCase;

/**
 * BOS-021 (#8210) — preuve de non-régression de l'unification des webhooks
 * sortants.
 *
 * L'issue demande « compatibilité des consommateurs existants démontrée (ou
 * version de signature documentée) ». Ce test démontre la compatibilité
 * plutôt que de la documenter : il recalcule les signatures avec la formule
 * HISTORIQUE (celle que les partenaires vérifient aujourd'hui) et exige que
 * les producteurs refactorés produisent exactement les mêmes octets.
 *
 * Si quelqu'un « simplifie » un jour la chaîne signée en croyant unifier les
 * deux schémas, ce test tombe — c'est précisément le piège que l'issue
 * signale (« chaque nouvelle verticale recopie l'une d'elles »).
 */
final class OutboundWebhookSignatureUnificationTest extends TestCase
{
    private const SECRET = 'whsec_test_8210_do_not_use_in_prod';

    /**
     * Billing : schéma Svix `sha256("{timestamp}.{rawBody}")`.
     *
     * C'est le schéma que les bibliothèques partenaires standard vérifient
     * (`Webhook-Signature: v1=…,t=…`). Il ne doit pas bouger.
     */
    public function test_billing_signature_is_byte_identical_to_the_historical_formula(): void
    {
        $body = json_encode([
            'event' => 'invoice.paid',
            'event_version' => 1,
            'company_id' => 'c-1',
            'data' => ['amount' => 1500],
        ], JSON_THROW_ON_ERROR);

        $timestamp = 1_700_000_000;

        $expected = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $actual = WebhookEnvelopeBuilder::sign(self::SECRET, $body, $timestamp);

        $this->assertSame($expected, $actual);
    }

    /**
     * Travel : HMAC du payload canonique (`ksort` + `json_encode`), SANS
     * préfixe d'horodatage — l'horodatage voyage dans `X-Travel-Timestamp`.
     *
     * Unifier la primitive ne doit pas faire glisser Travel vers le schéma
     * Svix : les transporteurs vérifient `X-Travel-Signature` tel quel.
     */
    public function test_travel_signature_is_byte_identical_to_the_historical_formula(): void
    {
        $payload = ['event_type' => 'booking.confirmed', 'booking_id' => 'b-9', 'seats' => 2];

        $canonical = $payload;
        ksort($canonical);
        $expected = hash_hmac('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), self::SECRET);

        $actual = (new TravelWebhookSigner)->sign($payload, self::SECRET);

        $this->assertSame($expected, $actual);
    }

    /**
     * Les deux producteurs partagent désormais la MÊME primitive : la
     * signature d'une chaîne identique est identique, quel que soit le
     * producteur qui la demande.
     */
    public function test_both_producers_route_through_the_same_primitive(): void
    {
        $signer = new HmacWebhookSigner;
        $signedPayload = '1700000000.{"a":1}';

        $this->assertSame(
            $signer->sign(self::SECRET, $signedPayload),
            WebhookEnvelopeBuilder::sign(self::SECRET, '{"a":1}', 1_700_000_000),
        );
    }

    /**
     * Le contrat est résolu par le container : un producteur peut recevoir
     * n'importe quelle implémentation (rotation de secret, KMS) sans changer
     * son code.
     */
    public function test_contract_is_bound_in_the_container(): void
    {
        $this->assertInstanceOf(HmacWebhookSigner::class, app(OutboundWebhookSigner::class));
    }

    /**
     * `verify()` doit accepter la signature légitime et refuser toute
     * altération — y compris un préfixe correct suivi d'octets parasites,
     * que `str_starts_with` laisserait passer.
     */
    public function test_verify_accepts_valid_and_rejects_tampered_signatures(): void
    {
        $signer = new HmacWebhookSigner;
        $payload = 'canonical-payload';
        $signature = $signer->sign(self::SECRET, $payload);

        $this->assertTrue($signer->verify(self::SECRET, $payload, $signature));
        $this->assertFalse($signer->verify(self::SECRET, $payload.'x', $signature));
        $this->assertFalse($signer->verify(self::SECRET, $payload, $signature.'00'));
        $this->assertFalse($signer->verify('wrong-secret', $payload, $signature));
    }

    /**
     * Travel expose toujours `verify()` avec sa signature historique
     * (signature, canonicalPayload, secret) — les appelants existants ne
     * changent pas.
     */
    public function test_travel_verify_keeps_its_historical_argument_order(): void
    {
        $signer = new TravelWebhookSigner;
        $payload = ['b' => 2, 'a' => 1];
        $canonical = $signer->canonicalPayload($payload);
        $signature = $signer->sign($payload, self::SECRET);

        $this->assertTrue($signer->verify($signature, $canonical, self::SECRET));
        $this->assertFalse($signer->verify($signature, $canonical.' ', self::SECRET));
    }

    /**
     * Le DISPATCHER de livraison Travel signait de son côté
     * (`'sha256='.hash_hmac(...)`) alors que le signer du même module passait
     * déjà par le socle : deux endroits où se tromper sur la comparaison, et
     * deux primitives à faire évoluer lors d'une rotation de secret.
     *
     * Il doit désormais produire exactement les mêmes octets que la formule
     * historique que les transporteurs vérifient — préfixe `sha256=` inclus,
     * puisque ce préfixe fait partie du contrat public.
     */
    public function test_travel_dispatcher_signature_is_byte_identical_to_the_historical_formula(): void
    {
        $body = json_encode(['event_type' => 'booking.confirmed', 'booking_id' => 'b-9'], JSON_THROW_ON_ERROR);

        $expected = 'sha256='.hash_hmac('sha256', $body, self::SECRET);
        $actual = (new TravelWebhookDispatcher)->signature($body, self::SECRET);

        $this->assertSame($expected, $actual);
    }

    /**
     * Le dispatcher doit consommer le CONTRAT, pas la classe concrète : un
     * remplacement d'implémentation (rotation de secret, KMS) doit se faire
     * par binding sans toucher au dispatcher.
     */
    public function test_travel_dispatcher_signs_through_the_shared_contract(): void
    {
        $signer = new HmacWebhookSigner;
        $dispatcher = new TravelWebhookDispatcher($signer);
        $body = '{"a":1}';

        $this->assertSame(
            'sha256='.$signer->sign(self::SECRET, $body),
            $dispatcher->signature($body, self::SECRET),
        );
    }
}
