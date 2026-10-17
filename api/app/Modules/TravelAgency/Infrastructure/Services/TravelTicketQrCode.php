<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * #8538 (TRAVEL-OS) — QR code du billet nominatif.
 *
 * Le QR encode une charge utile MINIMALE et SANS DONNÉE PERSONNELLE :
 *
 *     GV1|<reference de réservation>|<code de contrôle>
 *
 * Le code de contrôle est le code canonique du billet
 * ({@see TravelTicket::canonicalValidationCode()}, HMAC-SHA256 + `APP_KEY`) :
 * il n'est jamais persisté en clair et ne peut pas être recalculé sans le
 * secret applicatif. Le QR ne contient donc ni nom, ni document, ni prix —
 * uniquement de quoi retrouver le billet et prouver sa possession.
 *
 * Le rendu passe par `GDLibRenderer` (extension GD, PNG en mémoire) : aucune
 * dépendance à Imagick, et le PNG est inliné en `data:` URI pour être rendu
 * par dompdf sans fichier temporaire.
 */
final class TravelTicketQrCode
{
    /** Préfixe de version de la charge utile — permet une évolution future. */
    public const PAYLOAD_PREFIX = 'GV1';

    /** Taille du PNG généré, en pixels (carré). */
    private const SIZE = 320;

    /** Marge blanche autour de la matrice, en modules. */
    private const MARGIN = 2;

    /**
     * Charge utile encodée dans le QR — jamais de PII.
     */
    public function payload(TravelTicket $ticket): string
    {
        $reference = (string) ($ticket->booking->reference ?? '');

        return implode('|', [
            self::PAYLOAD_PREFIX,
            $reference,
            $ticket->plainValidationCode(),
        ]);
    }

    /**
     * Décode une charge utile scannée. Retourne `null` si le format est
     * inconnu — l'appelant décide alors du refus (jamais d'exception brute
     * remontée au client).
     *
     * @return array{reference: string, code: string}|null
     */
    public function parse(string $payload): ?array
    {
        $parts = explode('|', trim($payload));

        if (count($parts) !== 3 || $parts[0] !== self::PAYLOAD_PREFIX) {
            return null;
        }

        $reference = trim($parts[1]);
        $code = trim($parts[2]);

        if ($reference === '' || $code === '') {
            return null;
        }

        return ['reference' => $reference, 'code' => $code];
    }

    /**
     * PNG du QR code, encodé en `data:` URI (directement utilisable dans un
     * template Blade rendu par dompdf).
     */
    public function dataUri(TravelTicket $ticket): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($ticket));
    }

    /**
     * PNG binaire du QR code.
     *
     * Régression CI (#8547) : `GDLibRenderer` produit un PNG **transparent**
     * (canal alpha à 0 hors des modules). dompdf doit alors ré-encoder un
     * RGBA + masque `SMask`, et cela dépend de la build GD/dompdf : sur le
     * runner CI l'image couleur n'était plus émise du tout (`firstRgbImage()`
     * renvoyait `null`, PDF sans `/ColorSpace /DeviceRGB`).
     *
     * Le billet est imprimé sur papier blanc : le QR est donc aplati sur un
     * fond **blanc opaque**. dompdf n'a plus qu'une image `DeviceRGB` — un
     * seul objet image, déterministe, sans dépendance au masque de
     * transparence. Le code scanné est identique, seule l'enveloppe l'est
     * aussi.
     */
    public function png(TravelTicket $ticket): string
    {
        $writer = new Writer(new GDLibRenderer(self::SIZE, self::MARGIN));

        $source = imagecreatefromstring($writer->writeString($this->payload($ticket)));

        if ($source === false) {
            // GD indisponible ou PNG illisible : on ne fabrique pas un QR
            // « au hasard », on rend le PNG d'origine (le billet reste
            // imprimable, le QR porte toujours la bonne charge utile).
            return $writer->writeString($this->payload($ticket));
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $opaque = imagecreatetruecolor($width, $height);

        imagealphablending($opaque, true);
        imagefilledrectangle($opaque, 0, 0, $width, $height, (int) imagecolorallocate($opaque, 255, 255, 255));
        imagecopy($opaque, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagepng($opaque);
        $png = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($opaque);

        return $png === '' ? $writer->writeString($this->payload($ticket)) : $png;
    }
}
