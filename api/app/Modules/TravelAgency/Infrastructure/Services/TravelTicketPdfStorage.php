<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Illuminate\Support\Facades\Storage;

/**
 * TRAVEL-413 (#6065) — Stockage du billet PDF + URL signée + révocation.
 *
 * Fallback disque tenant (`storage/app/travel/tickets/…`) avec URL signée
 * temporaire (disque `local`, serve true). La révocation se fait en
 * régénérant la clé de signature (le lien signé existant devient invalide)
 * et/ou en passant le billet en `void`. Le contrat documents BC-20 pourra
 * remplacer le fallback sans changer l'interface.
 */
final class TravelTicketPdfStorage
{
    public const DISK = 'local';

    public const PREFIX = 'travel/tickets';

    public function store(TravelTicket $ticket, string $pdfBinary): string
    {
        $path = self::PREFIX.'/'.$ticket->company_id.'/'.$ticket->ticket_number.'.pdf';

        Storage::disk(self::DISK)->put($path, $pdfBinary);

        return $path;
    }

    /**
     * URL signée temporaire (30 min) — jamais un lien public permanent.
     */
    public function signedUrl(string $path): string
    {
        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes(30));
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
