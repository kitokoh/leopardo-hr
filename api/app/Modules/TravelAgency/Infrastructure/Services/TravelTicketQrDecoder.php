<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

/**
 * #8547 (TRAVEL-OS) — décodeur QR minimal, SANS dépendance externe.
 *
 * Pourquoi ce fichier existe : le critère d'acceptation #8547 §3 exige « un
 * test qui décode le QR depuis le PDF ». Comparer le PNG produit à un PNG
 * régénéré ne prouverait rien (on comparerait le générateur à lui-même) : il
 * faut un LECTEUR indépendant du chemin d'écriture.
 *
 * Le dépôt n'embarque aucun décodeur (`bacon/bacon-qr-code` ne fournit que
 * l'ENCODAGE — `src/` ne contient ni `Decoder/` ni lecteur), et la CI backend
 * n'installe ni `zbar` ni OpenCV. Ce décodeur est donc écrit ici, en PHP pur,
 * pour que le test tourne partout sans nouvelle dépendance système.
 *
 * PORTÉE HONNÊTE — ce que ce décodeur fait et ne fait pas :
 *  - il décode une image **propre** (QR généré, sans bruit, sans perspective,
 *    sans rotation) : c'est exactement le cas du test (le QR du PDF) ;
 *  - il lit l'information de format, applique le démasquage, lit les
 *    codewords dans l'ordre en zigzag, dé-entrelace les blocs et vérifie que
 *    les syndromes de Reed-Solomon sont NULS (aucune erreur) ;
 *  - il ne CORRIGE pas d'erreur : une image dégradée est rejetée (`null`)
 *    plutôt que devinée. Un lecteur caméra réel reste du ressort de l'app
 *    mobile (#8546), pas de ce service.
 *
 * Il n'est utilisé QUE par les tests et l'outillage de vérification : aucune
 * route ne l'expose.
 */
final class TravelTicketQrDecoder
{
    /** Côté du motif de repérage (finder pattern), en modules. */
    private const FINDER_SIZE = 7;

    /**
     * Structure des blocs par (version, niveau de correction).
     *
     * `[ecCodewordsPerBlock, [[numBlocks, dataCodewordsPerBlock], ...]]`
     * Versions 1 à 10 — largement au-delà de la charge utile d'un billet
     * (`GV1|<référence>|<code>`, une quarantaine d'octets).
     *
     * @var array<int, array<string, array{0: int, 1: list<array{0: int, 1: int}>}>>
     */
    private const BLOCKS = [
        1 => ['L' => [7, [[1, 19]]], 'M' => [10, [[1, 16]]], 'Q' => [13, [[1, 13]]], 'H' => [17, [[1, 9]]]],
        2 => ['L' => [10, [[1, 34]]], 'M' => [16, [[1, 28]]], 'Q' => [22, [[1, 22]]], 'H' => [28, [[1, 16]]]],
        3 => ['L' => [15, [[1, 55]]], 'M' => [26, [[1, 44]]], 'Q' => [18, [[2, 17]]], 'H' => [22, [[2, 13]]]],
        4 => ['L' => [20, [[1, 80]]], 'M' => [18, [[2, 32]]], 'Q' => [26, [[2, 24]]], 'H' => [16, [[4, 9]]]],
        5 => ['L' => [26, [[1, 108]]], 'M' => [24, [[2, 43]]], 'Q' => [18, [[2, 15], [2, 16]]], 'H' => [22, [[2, 11], [2, 12]]]],
        6 => ['L' => [18, [[2, 68]]], 'M' => [16, [[4, 27]]], 'Q' => [24, [[4, 19]]], 'H' => [28, [[4, 15]]]],
        7 => ['L' => [20, [[2, 78]]], 'M' => [18, [[4, 31]]], 'Q' => [18, [[2, 14], [4, 15]]], 'H' => [26, [[4, 13], [1, 14]]]],
        8 => ['L' => [24, [[2, 97]]], 'M' => [22, [[2, 38], [2, 39]]], 'Q' => [22, [[4, 18], [2, 19]]], 'H' => [26, [[4, 14], [2, 15]]]],
        9 => ['L' => [30, [[2, 116]]], 'M' => [22, [[3, 36], [2, 37]]], 'Q' => [20, [[4, 16], [4, 17]]], 'H' => [24, [[4, 12], [4, 13]]]],
        10 => ['L' => [18, [[2, 68], [2, 69]]], 'M' => [26, [[4, 43], [1, 44]]], 'Q' => [24, [[6, 19], [2, 20]]], 'H' => [28, [[6, 15], [2, 16]]]],
    ];

    /**
     * Centres des motifs d'alignement par version (versions 1 à 10).
     *
     * @var array<int, list<int>>
     */
    private const ALIGNMENT = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /**
     * Décode un PNG de QR code et retourne la chaîne encodée.
     *
     * `null` si l'image est illisible, mal formée, ou si les syndromes de
     * Reed-Solomon ne sont pas nuls (image dégradée) — jamais de valeur
     * devinée.
     */
    public function decodePng(string $png): ?string
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return null;
        }

        // Un PNG en palette ferait retourner un INDEX à `imagecolorat` (0/1),
        // pas une couleur : la luminance serait fausse et toute l'image
        // paraîtrait sombre. On normalise en truecolor avant lecture.
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $matrix = [];

        for ($y = 0; $y < $height; $y++) {
            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $luminance = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 3;
                $row[] = $luminance < 128;
            }

            $matrix[] = $row;
        }

        imagedestroy($image);

        return $this->decodeMatrix($matrix, $width, $height);
    }

    /**
     * Décode un QR depuis des pixels RGB bruts (3 octets par pixel, ligne par
     * ligne) — utilisé pour lire l'image telle qu'elle est EMBARQUÉE dans le
     * PDF, sans repasser par un PNG intermédiaire.
     */
    public function decodeRgb(string $rgb, int $width, int $height): ?string
    {
        if ($width < 21 || $height < 21 || strlen($rgb) < $width * $height * 3) {
            return null;
        }

        $matrix = [];

        for ($y = 0; $y < $height; $y++) {
            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $offset = ($y * $width + $x) * 3;
                $luminance = (ord($rgb[$offset]) + ord($rgb[$offset + 1]) + ord($rgb[$offset + 2])) / 3;
                $row[] = $luminance < 128;
            }

            $matrix[] = $row;
        }

        return $this->decodeMatrix($matrix, $width, $height);
    }

    /**
     * @param  list<list<bool>>  $matrix
     */
    private function decodeMatrix(array $matrix, int $width, int $height): ?string
    {
        $bounds = $this->darkBounds($matrix, $width, $height);

        if ($bounds === null) {
            return null;
        }

        [$minX, $minY, $maxX, $maxY] = $bounds;

        // Le motif de repérage haut-gauche fait exactement 7 modules : la
        // longueur de la première plage sombre de la ligne supérieure donne
        // la taille d'un module. Valable pour une image propre et non tournée.
        $run = 0;

        for ($x = $minX; $x <= $maxX && $matrix[$minY][$x]; $x++) {
            $run++;
        }

        if ($run < self::FINDER_SIZE) {
            return null;
        }

        $moduleSize = $run / self::FINDER_SIZE;
        $size = (int) round(($maxX - $minX + 1) / $moduleSize);

        if ($size < 21 || $size > 57 || ($size - 17) % 4 !== 0) {
            return null;
        }

        $version = intdiv($size - 17, 4);

        if (! isset(self::BLOCKS[$version])) {
            return null;
        }

        $grid = $this->sampleGrid($matrix, $minX, $minY, $moduleSize, $size);

        $format = $this->readFormat($grid, $size);

        if ($format === null) {
            return null;
        }

        [$ecLevel, $mask] = $format;

        $this->unmask($grid, $size, $mask);

        $codewords = $this->readCodewords($grid, $size, $version);

        if ($codewords === null) {
            return null;
        }

        $data = $this->deinterleave($codewords, $version, $ecLevel);

        if ($data === null) {
            return null;
        }

        return $this->parseData($data, $version);
    }

    /**
     * Boîte englobante des pixels sombres (le QR hors zone de silence).
     *
     * @param  list<list<bool>>  $matrix
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    private function darkBounds(array $matrix, int $width, int $height): ?array
    {
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $matrix[$y][$x]) {
                    continue;
                }

                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        return $maxX < 0 ? null : [$minX, $minY, $maxX, $maxY];
    }

    /**
     * Échantillonne le centre de chaque module.
     *
     * @param  list<list<bool>>  $matrix
     * @return list<list<bool>>
     */
    private function sampleGrid(array $matrix, int $minX, int $minY, float $moduleSize, int $size): array
    {
        $grid = [];

        for ($row = 0; $row < $size; $row++) {
            $line = [];

            for ($col = 0; $col < $size; $col++) {
                $x = (int) round($minX + ($col + 0.5) * $moduleSize);
                $y = (int) round($minY + ($row + 0.5) * $moduleSize);
                $line[] = $matrix[$y][$x] ?? false;
            }

            $grid[] = $line;
        }

        return $grid;
    }

    /**
     * Lit l'information de format (15 bits, XOR 0x5412) et en extrait le
     * niveau de correction et le motif de masque.
     *
     * @param  list<list<bool>>  $grid
     * @return array{0: string, 1: int}|null
     */
    private function readFormat(array $grid, int $size): ?array
    {
        $positions = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];

        $bits = 0;

        // Le bit de POIDS FORT (bit 14) est le premier de la liste : la
        // première position porte le bit 14, la dernière le bit 0. Vérifié
        // contre le code BCH (générateur 0x537) : dans l'autre sens, le reste
        // n'est jamais nul.
        foreach ($positions as $index => [$row, $col]) {
            if ($grid[$row][$col] ?? false) {
                $bits |= 1 << (14 - $index);
            }
        }

        $format = $bits ^ 0x5412;

        // Les 10 bits de poids faible sont un code BCH : on vérifie qu'ils
        // sont cohérents, sinon l'image n'est pas un QR exploitable.
        if ($this->bchRemainder($format, 0x537) !== 0) {
            return null;
        }

        $ecBits = ($format >> 13) & 0x3;
        $mask = ($format >> 10) & 0x7;

        $ecLevel = match ($ecBits) {
            0 => 'M',
            1 => 'L',
            2 => 'H',
            default => 'Q',
        };

        return [$ecLevel, $mask];
    }

    /**
     * Reste de la division polynomiale binaire (BCH).
     */
    private function bchRemainder(int $value, int $polynomial): int
    {
        $degree = 0;
        $probe = $polynomial;

        while ($probe > 1) {
            $probe >>= 1;
            $degree++;
        }

        for ($bit = 14; $bit >= $degree; $bit--) {
            if (($value & (1 << $bit)) !== 0) {
                $value ^= $polynomial << ($bit - $degree);
            }
        }

        return $value;
    }

    /**
     * Applique le masque du QR (les modules de données uniquement).
     *
     * @param  array<int, array<int, bool>>  $grid
     */
    private function unmask(array &$grid, int $size, int $mask): void
    {
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($this->isFunctionModule($row, $col, $size)) {
                    continue;
                }

                $invert = match ($mask) {
                    0 => ($row + $col) % 2 === 0,
                    1 => $row % 2 === 0,
                    2 => $col % 3 === 0,
                    3 => ($row + $col) % 3 === 0,
                    4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
                    5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
                    6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
                    7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
                    default => false,
                };

                if ($invert) {
                    $grid[$row][$col] = ! $grid[$row][$col];
                }
            }
        }
    }

    /**
     * Un module est-il fonctionnel (repères, synchronisation, format,
     * alignement, version) — donc hors données ?
     */
    private function isFunctionModule(int $row, int $col, int $size): bool
    {
        // Repères + séparateurs (9×9 dans les trois coins).
        if ($row <= 8 && $col <= 8) {
            return true;
        }

        if ($row <= 8 && $col >= $size - 8) {
            return true;
        }

        if ($row >= $size - 8 && $col <= 8) {
            return true;
        }

        // Motifs de synchronisation.
        if ($row === 6 || $col === 6) {
            return true;
        }

        // Information de format (deux copies).
        if ($row === 8 && $col >= $size - 8) {
            return true;
        }

        if ($col === 8 && $row >= $size - 8) {
            return true;
        }

        $version = intdiv($size - 17, 4);

        // Motifs d'alignement.
        $centers = self::ALIGNMENT[$version];

        foreach ($centers as $centerRow) {
            foreach ($centers as $centerCol) {
                // Ceux qui chevauchent un repère ne sont pas dessinés.
                if (($centerRow <= 8 && $centerCol <= 8)
                    || ($centerRow <= 8 && $centerCol >= $size - 9)
                    || ($centerRow >= $size - 9 && $centerCol <= 8)) {
                    continue;
                }

                if (abs($row - $centerRow) <= 2 && abs($col - $centerCol) <= 2) {
                    return true;
                }
            }
        }

        // Information de version (versions 7+).
        if ($version >= 7) {
            if ($col >= $size - 11 && $col <= $size - 9 && $row <= 5) {
                return true;
            }

            if ($row >= $size - 11 && $row <= $size - 9 && $col <= 5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lit les codewords dans l'ordre en zigzag (deux colonnes à la fois, en
     * partant du bas-droite, en sautant la colonne de synchronisation).
     *
     * @param  array<int, array<int, bool>>  $grid
     * @return list<int>|null
     */
    private function readCodewords(array $grid, int $size, int $version): ?array
    {
        $bits = [];
        $upward = true;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col = 5;
            }

            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? $size - 1 - $step : $step;

                foreach ([$col, $col - 1] as $currentCol) {
                    if ($this->isFunctionModule($row, $currentCol, $size)) {
                        continue;
                    }

                    $bits[] = $grid[$row][$currentCol] ? 1 : 0;
                }
            }

            $upward = ! $upward;
        }

        $structure = self::BLOCKS[$version];
        $totalCodewords = 0;

        foreach ($structure as [, $groups]) {
            foreach ($groups as [$numBlocks, $dataCodewords]) {
                $totalCodewords += $numBlocks * $dataCodewords;
            }
        }

        // Le total de codewords dépend du niveau de correction : on le
        // recalcule après lecture du format, dans `deinterleave`. Ici on
        // retourne simplement tous les bits utiles.
        $codewords = [];

        for ($i = 0; $i + 8 <= count($bits); $i += 8) {
            $byte = 0;

            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | $bits[$i + $b];
            }

            $codewords[] = $byte;
        }

        return $codewords === [] ? null : $codewords;
    }

    /**
     * Dé-entrelace les blocs et vérifie les syndromes de Reed-Solomon.
     *
     * @param  list<int>  $codewords
     * @return list<int>|null
     */
    private function deinterleave(array $codewords, int $version, string $ecLevel): ?array
    {
        [$ecPerBlock, $groups] = self::BLOCKS[$version][$ecLevel];

        $blocks = [];

        foreach ($groups as [$numBlocks, $dataCodewords]) {
            for ($i = 0; $i < $numBlocks; $i++) {
                $blocks[] = ['data' => $dataCodewords, 'ec' => $ecPerBlock, 'codewords' => []];
            }
        }

        $totalData = 0;
        $maxData = 0;

        foreach ($blocks as $block) {
            $totalData += $block['data'];
            $maxData = max($maxData, $block['data']);
        }

        $totalCodewords = $totalData + count($blocks) * $ecPerBlock;

        if (count($codewords) < $totalCodewords) {
            return null;
        }

        $codewords = array_slice($codewords, 0, $totalCodewords);

        // Les codewords de données sont entrelacés : tour à tour, un octet
        // par bloc, en sautant les blocs plus courts.
        $cursor = 0;

        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $index => $block) {
                if ($i < $block['data']) {
                    $blocks[$index]['codewords'][] = $codewords[$cursor++];
                }
            }
        }

        // Puis les codewords de correction, bloc par bloc.
        foreach ($blocks as $index => $block) {
            for ($i = 0; $i < $ecPerBlock; $i++) {
                $blocks[$index]['codewords'][] = $codewords[$cursor++];
            }
        }

        $data = [];

        foreach ($blocks as $block) {
            $blockCodewords = $block['codewords'];
            $dataCodewords = array_slice($blockCodewords, 0, $block['data']);
            $ecCodewords = array_slice($blockCodewords, $block['data']);

            // Aucune correction : on refuse une image dont les syndromes ne
            // sont pas nuls plutôt que de deviner le contenu.
            if (! $this->syndromesAreZero($dataCodewords, $ecCodewords)) {
                return null;
            }

            foreach ($dataCodewords as $codeword) {
                $data[] = $codeword;
            }
        }

        return $data;
    }

    /**
     * Les syndromes de Reed-Solomon sont-ils tous nuls (aucune erreur) ?
     *
     * @param  list<int>  $data
     * @param  list<int>  $ec
     */
    private function syndromesAreZero(array $data, array $ec): bool
    {
        $count = count($ec);

        for ($i = 0; $i < $count; $i++) {
            $syndrome = 0;

            foreach ($data as $codeword) {
                $syndrome = $this->gfMultiply($syndrome, $this->gfPower($i)) ^ $codeword;
            }

            foreach ($ec as $codeword) {
                $syndrome = $this->gfMultiply($syndrome, $this->gfPower($i)) ^ $codeword;
            }

            if ($syndrome !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Multiplication dans GF(256) (polynôme primitif 0x11D).
     */
    private function gfMultiply(int $a, int $b): int
    {
        $result = 0;

        while ($b > 0) {
            if (($b & 1) !== 0) {
                $result ^= $a;
            }

            $a <<= 1;

            if (($a & 0x100) !== 0) {
                $a ^= 0x11D;
            }

            $b >>= 1;
        }

        return $result & 0xFF;
    }

    /**
     * α^i dans GF(256).
     */
    private function gfPower(int $exponent): int
    {
        $value = 1;

        for ($i = 0; $i < $exponent; $i++) {
            $value = $this->gfMultiply($value, 2);
        }

        return $value;
    }

    /**
     * Décode le flux de données (mode + longueur + contenu).
     *
     * @param  list<int>  $data
     */
    private function parseData(array $data, int $version): ?string
    {
        $bits = '';

        foreach ($data as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $offset = 0;
        $result = '';

        while ($offset + 4 <= strlen($bits)) {
            $mode = bindec(substr($bits, $offset, 4));
            $offset += 4;

            // 0000 = fin de message.
            if ($mode === 0) {
                break;
            }

            // 0100 = mode octet (le seul utilisé par BaconQrCode pour une
            // charge utile ASCII comme celle du billet).
            if ($mode !== 4) {
                return null;
            }

            $lengthBits = $version >= 10 ? 16 : 8;

            if ($offset + $lengthBits > strlen($bits)) {
                return null;
            }

            $length = bindec(substr($bits, $offset, $lengthBits));
            $offset += $lengthBits;

            if ($offset + $length * 8 > strlen($bits)) {
                return null;
            }

            for ($i = 0; $i < $length; $i++) {
                $result .= chr((int) bindec(substr($bits, $offset, 8)));
                $offset += 8;
            }
        }

        return $result === '' ? null : $result;
    }
}
