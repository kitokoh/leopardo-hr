<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Extraction de texte depuis un PDF dompdf v3.
 *
 * dompdf compresse les flux de contenu (FlateDecode) et encode le texte en
 * UTF-16 par runs. Cette classe décompresse chaque stream puis reconstruit le
 * texte affiché, ce qui permet d'assertir sur le contenu réel du bulletin
 * plutôt que sur les octets bruts du binaire.
 *
 * Mutualisé par PaySlipDzMentionsTest (F-09 / #1539) et
 * PaySlipLegalMentionsByCountryTest (#9045).
 */
final class PdfTextExtractor
{
    public static function text(string $binary): string
    {
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    continue;
                }
                $text .= self::extractParenthesized($decoded).' ';
            }
        }

        return $text;
    }

    private static function extractParenthesized(string $data): string
    {
        // Chaînes entre parenthèses + décodage des échappements dompdf
        // (`\(`, `\)`, `\\`, séquences octales `\nnn`). dompdf v3 encode les
        // flux en UTF-16 (BE ou LE selon la police/le run) : endianness
        // détectée par run (BOM, puis position du premier octet nul).
        $text = '';
        $inParen = false;
        $buf = '';
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $ch = $data[$i];
            if ($inParen) {
                // Échappement PDF : `\(`, `\)`, `\\` et séquences octales.
                // Le backslash est conservé dans le buffer pour que decodeRun()
                // le résolve — sinon un `\)` fermerait la chaîne trop tôt et
                // désynchroniserait tout le flux (régression observée sur les
                // libellés canadiens « Business Number (BN) »).
                if ($ch === '\\' && ($i + 1) < $length) {
                    $buf .= $ch.$data[$i + 1];
                    $i++;
                } elseif ($ch === ')') {
                    $text .= self::decodeRun($buf).' ';
                    $buf = '';
                    $inParen = false;
                } else {
                    $buf .= $ch;
                }
            } elseif ($ch === '(') {
                $inParen = true;
                $buf = '';
            }
        }

        // Normalisation des espaces Unicode (U+00A0, U+2000–U+200A, U+202F,
        // U+3000) et des doubles espaces (runs de texte scindés par dompdf).
        $text = (string) preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{3000}]/u', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);

        return (string) $text;
    }

    private static function decodeRun(string $run): string
    {
        $decoded = preg_replace_callback(
            '/\\\\([0-7]{3})/',
            static fn (array $m): string => chr((int) octdec($m[1])),
            $run
        );
        $decoded = preg_replace('/\\\\([()\\\\])/', '$1', (string) $decoded);

        if (! str_contains((string) $decoded, "\0")) {
            return (string) $decoded;
        }

        // Endianness : BOM explicite, sinon position du premier octet nul.
        if (str_starts_with((string) $decoded, "\xFE\xFF")) {
            return mb_convert_encoding(substr((string) $decoded, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with((string) $decoded, "\xFF\xFE")) {
            return mb_convert_encoding(substr((string) $decoded, 2), 'UTF-8', 'UTF-16LE');
        }

        $littleEndian = isset($decoded[1]) && $decoded[1] === "\0";

        return mb_convert_encoding((string) $decoded, 'UTF-8', $littleEndian ? 'UTF-16LE' : 'UTF-16BE');
    }
}
