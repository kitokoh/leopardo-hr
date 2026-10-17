<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

/**
 * #8547 (TRAVEL-OS) — extraction de l'image embarquée dans un PDF, en PHP pur.
 *
 * Pourquoi : le critère d'acceptation #8547 §3 exige de « décoder le QR depuis
 * le PDF ». Le PDF est produit par dompdf, qui **ré-encode** l'image (vérifié :
 * le PNG d'origine n'apparaît pas tel quel dans le flux, `pdfimages` liste une
 * image RGB 320×320 + un masque). Il faut donc lire l'image telle qu'elle est
 * réellement dans le PDF, pas celle qu'on y a mise.
 *
 * Ce que ce lecteur gère — et rien de plus :
 *  - un PDF **non chiffré**, à un seul flux d'image, produit par dompdf ;
 *  - les images `FlateDecode` (zlib) en `DeviceRGB` 8 bits, avec ou sans
 *    `SMask` (le masque de transparence est ignoré : le QR est opaque) ;
 *  - les images `DCTDecode` (JPEG) sont **rejetées** (`null`) plutôt que
 *    devinées.
 *
 * Il n'est utilisé QUE par les tests : aucune route ne l'expose.
 */
final class PdfEmbeddedImageExtractor
{
    /**
     * Extrait la première image RGB 8 bits du PDF.
     *
     * @return array{width: int, height: int, rgb: string}|null
     */
    public function firstRgbImage(string $pdf): ?array
    {
        if (! preg_match_all('/<<(.*?)>>\s*stream\r?\n/s', $pdf, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $dictionary = $match[1];

            if (! str_contains($dictionary, '/Image')) {
                continue;
            }

            // Une image JPEG ne peut pas être lue sans décodeur : on refuse.
            if (str_contains($dictionary, '/DCTDecode')) {
                continue;
            }

            if (! str_contains($dictionary, '/FlateDecode')) {
                continue;
            }

            $width = $this->dictionaryInt($dictionary, 'Width');
            $height = $this->dictionaryInt($dictionary, 'Height');

            if ($width === null || $height === null) {
                continue;
            }

            $stream = $this->streamAfter($pdf, $match[0]);

            if ($stream === null) {
                continue;
            }

            $decoded = @gzuncompress($stream);

            if ($decoded === false) {
                continue;
            }

            // dompdf applique le prédicteur PNG (`/Predictor 15`) : le flux
            // décompressé est filtré ligne par ligne (filtres PNG 0-4). Sans
            // inversion, on lit du bruit — c'est ce qui faisait échouer le
            // décodage avant.
            $decoded = $this->reversePngPredictor($decoded, $dictionary, $width, $height);

            if ($decoded === null) {
                continue;
            }

            // DeviceRGB 8 bits : 3 octets par pixel. Un masque (SMask) est en
            // niveaux de gris et ne nous intéresse pas.
            if (str_contains($dictionary, '/DeviceRGB') && strlen($decoded) >= $width * $height * 3) {
                return [
                    'width' => $width,
                    'height' => $height,
                    'rgb' => substr($decoded, 0, $width * $height * 3),
                ];
            }
        }

        return null;
    }

    /**
     * Inverse le prédicteur PNG (filtres 0=None, 1=Sub, 2=Up, 3=Average,
     * 4=Paeth) appliqué par dompdf sur les flux d'image.
     */
    private function reversePngPredictor(string $data, string $dictionary, int $width, int $height): ?string
    {
        if (preg_match('/\/Predictor\s+(\d+)/', $dictionary, $match) !== 1) {
            return $data; // pas de prédicteur : flux brut
        }

        if ((int) $match[1] < 10) {
            return $data; // prédicteurs TIFF (2) non utilisés par dompdf
        }

        $colors = $this->dictionaryInt($dictionary, 'Colors') ?? 3;
        $bits = $this->dictionaryInt($dictionary, 'BitsPerComponent') ?? 8;
        $columns = $this->dictionaryInt($dictionary, 'Columns') ?? $width;

        $bytesPerPixel = max(1, intdiv($colors * $bits, 8));
        $rowLength = intdiv($columns * $colors * $bits, 8);

        $expected = ($rowLength + 1) * $height;

        if (strlen($data) < $expected) {
            return null;
        }

        $output = '';
        $previous = str_repeat("\x00", $rowLength);

        for ($row = 0; $row < $height; $row++) {
            $offset = $row * ($rowLength + 1);
            $filter = ord($data[$offset]);
            $line = substr($data, $offset + 1, $rowLength);
            $reconstructed = '';

            for ($i = 0; $i < $rowLength; $i++) {
                $raw = ord($line[$i]);
                $left = $i >= $bytesPerPixel ? ord($reconstructed[$i - $bytesPerPixel]) : 0;
                $up = ord($previous[$i]);
                $upLeft = $i >= $bytesPerPixel ? ord($previous[$i - $bytesPerPixel]) : 0;

                $value = match ($filter) {
                    0 => $raw,
                    1 => $raw + $left,
                    2 => $raw + $up,
                    3 => $raw + intdiv($left + $up, 2),
                    4 => $raw + $this->paeth($left, $up, $upLeft),
                    default => null,
                };

                if ($value === null) {
                    return null;
                }

                $reconstructed .= chr($value & 0xFF);
            }

            $output .= $reconstructed;
            $previous = $reconstructed;
        }

        return $output;
    }

    /**
     * Prédicteur de Paeth (filtre PNG 4).
     */
    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }

    private function dictionaryInt(string $dictionary, string $key): ?int
    {
        if (preg_match('/\/'.$key.'\s+(\d+)/', $dictionary, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * Contenu binaire du flux qui suit l'en-tête de dictionnaire donné.
     */
    private function streamAfter(string $pdf, string $header): ?string
    {
        $position = strpos($pdf, $header);

        if ($position === false) {
            return null;
        }

        $start = strpos($pdf, 'stream', $position);

        if ($start === false) {
            return null;
        }

        $start += strlen('stream');

        // Fin de ligne après le mot-clé `stream` (CRLF ou LF).
        if (substr($pdf, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (substr($pdf, $start, 1) === "\n") {
            $start += 1;
        }

        $end = strpos($pdf, 'endstream', $start);

        if ($end === false) {
            return null;
        }

        return rtrim(substr($pdf, $start, $end - $start), "\r\n");
    }
}
