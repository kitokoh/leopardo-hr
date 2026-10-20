<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\PdfEmbeddedImageExtractor;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfGenerator;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketQrCode;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketQrDecoder;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8547 (TRAVEL-OS) — le QR code du billet est réellement SCANNABLE depuis le
 * document imprimé.
 *
 * Pourquoi ce test est le cœur de l'issue : comparer le PNG produit à un PNG
 * régénéré ne prouverait rien (on comparerait le générateur à lui-même). Ici
 * on **décode** le QR depuis l'image telle qu'elle est EMBARQUÉE dans le PDF
 * final, avec un lecteur indépendant du chemin d'écriture
 * ({@see TravelTicketQrDecoder}), et on vérifie que la charge utile décodée
 * correspond exactement à celle du billet.
 *
 * Le PDF est produit par dompdf, qui ré-encode l'image (FlateDecode +
 * prédicteur PNG) : le test lit donc bien le document livré, pas l'entrée.
 */
class TravelTicketPrintingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function ticket(Company $company): TravelTicket
    {
        return app(TenantManager::class)->withinTenant($company, function (): TravelTicket {
            $booking = TravelBooking::factory()->create();
            $class = TravelClass::factory()->create();
            $passenger = $booking->passengers()->create([
                'full_name' => 'Moussa Diop',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);

            return TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
            ]);
        });
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        return $company;
    }

    // ---------------------------------------------------------------------
    // Le QR est scannable depuis le PDF livré
    // ---------------------------------------------------------------------

    public function test_qr_payload_is_decodable_from_the_generated_pdf(): void
    {
        $company = $this->company();
        $ticket = $this->ticket($company);

        $pdf = app(TravelTicketPdfGenerator::class)->generate($ticket);

        $image = app(PdfEmbeddedImageExtractor::class)->firstRgbImage($pdf);

        $this->assertNotNull($image, 'Aucune image RGB exploitable dans le PDF.');

        $decoded = app(TravelTicketQrDecoder::class)->decodeRgb(
            $image['rgb'],
            $image['width'],
            $image['height'],
        );

        $expected = app(TravelTicketQrCode::class)->payload($ticket);

        // Le QR du document imprimé se décode et porte EXACTEMENT la charge
        // utile du billet.
        $this->assertSame($expected, $decoded);
    }

    public function test_decoded_payload_parses_back_to_the_ticket(): void
    {
        $company = $this->company();
        $ticket = $this->ticket($company);

        $pdf = app(TravelTicketPdfGenerator::class)->generate($ticket);
        $image = app(PdfEmbeddedImageExtractor::class)->firstRgbImage($pdf);

        $this->assertNotNull($image);

        $decoded = app(TravelTicketQrDecoder::class)->decodeRgb(
            $image['rgb'],
            $image['width'],
            $image['height'],
        );

        $this->assertNotNull($decoded);

        $parsed = app(TravelTicketQrCode::class)->parse((string) $decoded);

        $this->assertNotNull($parsed);

        $booking = $ticket->booking;
        $this->assertNotNull($booking);

        $this->assertSame($booking->reference, $parsed['reference']);
        $this->assertSame($ticket->plainValidationCode(), $parsed['code']);
    }

    public function test_qr_payload_contains_no_personal_data(): void
    {
        $company = $this->company();
        $ticket = $this->ticket($company);

        $payload = app(TravelTicketQrCode::class)->payload($ticket);

        // Le QR ne contient ni nom, ni document, ni prix : uniquement de quoi
        // retrouver le billet et prouver sa possession.
        $this->assertStringNotContainsString('Moussa Diop', $payload);
        $this->assertStringNotContainsString('15000', $payload);
        $this->assertStringStartsWith('GV1|', $payload);
    }

    public function test_two_tickets_have_different_qr_payloads(): void
    {
        $company = $this->company();

        $first = $this->ticket($company);
        $second = $this->ticket($company);

        $qr = app(TravelTicketQrCode::class);

        // Deux billets ne peuvent pas partager la même charge utile.
        $this->assertNotSame($qr->payload($first), $qr->payload($second));
    }

    // ---------------------------------------------------------------------
    // Le décodeur refuse ce qu'il ne peut pas lire (jamais de valeur devinée)
    // ---------------------------------------------------------------------

    public function test_decoder_returns_null_on_garbage(): void
    {
        $decoder = app(TravelTicketQrDecoder::class);

        $this->assertNull($decoder->decodePng('not a png at all'));
        $this->assertNull($decoder->decodeRgb('', 0, 0));
    }

    public function test_decoder_returns_null_on_blank_image(): void
    {
        $decoder = app(TravelTicketQrDecoder::class);

        // Image blanche uniforme : aucun motif de repérage → refus.
        $blank = str_repeat("\xFF", 100 * 100 * 3);

        $this->assertNull($decoder->decodeRgb($blank, 100, 100));
    }

    public function test_decoder_round_trips_a_known_payload(): void
    {
        $payload = 'GV1|GV-TEST-REF|ABCD-EFGH-JKLM';

        $png = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\GDLibRenderer(320, 2)))
            ->writeString($payload);

        $this->assertSame($payload, app(TravelTicketQrDecoder::class)->decodePng($png));
    }

    public function test_pdf_extractor_returns_null_without_image(): void
    {
        $extractor = app(PdfEmbeddedImageExtractor::class);

        $this->assertNull($extractor->firstRgbImage('%PDF-1.4 no image here %%EOF'));
    }

    // ---------------------------------------------------------------------
    // Le document imprimable contient les informations indispensables
    // ---------------------------------------------------------------------

    public function test_pdf_is_a_valid_document(): void
    {
        $company = $this->company();
        $ticket = $this->ticket($company);

        $pdf = app(TravelTicketPdfGenerator::class)->generate($ticket);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }

    public function test_pdf_embeds_exactly_one_qr_image(): void
    {
        $company = $this->company();
        $ticket = $this->ticket($company);

        $pdf = app(TravelTicketPdfGenerator::class)->generate($ticket);

        // Le QR est aplati sur fond blanc opaque (#8547) : dompdf n'émet donc
        // qu'UN objet image, en DeviceRGB, sans masque de transparence. C'est
        // exactement ce qu'on vérifie — un seul QR, aucun doublon, et aucune
        // dépendance au ré-encodage RGBA/SMask qui variait selon la build GD.
        $this->assertSame(1, substr_count($pdf, '/ColorSpace /DeviceRGB'));
        $this->assertSame(0, substr_count($pdf, '/SMask'));
    }
}
