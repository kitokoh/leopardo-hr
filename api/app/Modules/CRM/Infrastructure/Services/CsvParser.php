<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Enums\CrmImportEntityType;
use App\Modules\CRM\Domain\Exceptions\CrmImportException;
use App\Support\CsvCellSanitizer;
use Illuminate\Http\UploadedFile;

/**
 * #5714 — Moteur de parsing CSV borné et sûr (ADR-CRM-005).
 *
 * Contrôles stricts, dans l'ordre :
 *   1. Extension et taille du fichier (2 Mo max).
 *   2. Encodage : UTF-8 (BOM toléré) ; tentative de conversion depuis
 *      Windows-1252/ISO-8859-1 ; sinon rejet.
 *   3. Bornes structurelles : 10 000 lignes max, 50 colonnes max,
 *      1 024 caractères max par cellule.
 *   4. En-tête : non vide, colonnes uniques (insensible à la casse), toutes
 *      appartenant à la whitelist de l'entité (colonne inconnue = erreur par
 *      ligne, jamais d'élargissement silencieux).
 *   5. Champs requis non vides ; formules CSV neutralisées
 *      (CsvCellSanitizer — OWASP) sur chaque cellule AVANT persistance.
 *
 * Les erreurs sont rapportées par ligne (numéro de ligne réel, en-tête = 1),
 * plafonnées à {@see MAX_REPORTED_ERRORS}.
 */
final class CsvParser
{
    public const MAX_FILE_SIZE_BYTES = 2_097_152; // 2 Mo

    public const MAX_ROWS = 10_000;

    public const MAX_COLUMNS = 50;

    public const MAX_CELL_LENGTH = 1_024;

    public const MAX_REPORTED_ERRORS = 100;

    private const ALLOWED_EXTENSIONS = ['csv', 'txt'];

    /**
     * @var list<string>
     */
    private const ALLOWED_MIMES = [
        'text/csv',
        'text/plain',
        'text/x-csv',
        'application/csv',
        'application/vnd.ms-excel',
        'application/octet-stream',
    ];

    /**
     * Vérifie les bornes du fichier uploadé (extension + taille).
     */
    public function validateUpload(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw CrmImportException::invalidFile('extension non autorisée (.csv ou .txt)');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw CrmImportException::invalidFile('fichier trop volumineux (max 2 Mo)');
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw CrmImportException::invalidFile('type MIME non autorisé');
        }
    }

    /**
     * Parse le contenu du fichier et valide structurellement chaque ligne.
     *
     * @throws CrmImportException si le fichier dépasse les bornes globales
     */
    public function parse(string $path, CrmImportEntityType $entityType): CsvParseResult
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw CrmImportException::invalidFile('lecture du fichier impossible');
        }

        $content = $this->normalizeEncoding($raw);
        $lines = $this->splitLines($content);

        if ($lines === []) {
            throw CrmImportException::invalidFile('fichier vide');
        }

        if (count($lines) > self::MAX_ROWS + 1) {
            throw CrmImportException::invalidFile('trop de lignes (max '.self::MAX_ROWS.' lignes de données)');
        }

        $allowed = $entityType->allowedColumns();
        $sensitive = $entityType->sensitiveColumns();

        // ── En-tête ──────────────────────────────────────────────────────────
        $header = $this->parseLine($lines[0], 1);

        if ($header === [] || count($header) > self::MAX_COLUMNS) {
            throw CrmImportException::invalidFile('en-tête invalide ou trop de colonnes (max '.self::MAX_COLUMNS.')');
        }

        $normalizedHeader = array_map(fn (string $c): string => mb_strtolower(trim($c)), $header);

        if (count($normalizedHeader) !== count(array_unique($normalizedHeader))) {
            throw CrmImportException::invalidFile('colonnes d\'en-tête dupliquées');
        }

        $unknown = array_diff($normalizedHeader, array_keys($allowed));
        if ($unknown !== []) {
            throw CrmImportException::invalidFile('colonnes inconnues : '.implode(', ', $unknown));
        }

        // ── Lignes de données ────────────────────────────────────────────────
        $rows = [];
        $errors = [];
        $validCount = 0;
        $errorCount = 0;

        foreach (array_slice($lines, 1) as $index => $line) {
            $lineNumber = $index + 2; // en-tête = ligne 1

            if (trim($line) === '') {
                continue; // ligne vide tolérée
            }

            $cells = $this->parseLine($line, $lineNumber);

            if (count($cells) !== count($header)) {
                $this->addError($errors, $errorCount, $lineNumber, sprintf(
                    'nombre de colonnes incorrect (%d attendues, %d trouvées)',
                    count($header),
                    count($cells)
                ));
                continue;
            }

            $row = [];
            $rowValid = true;

            foreach ($normalizedHeader as $i => $column) {
                $value = $cells[$i] ?? '';
                $value = trim($value);

                if (mb_strlen($value) > self::MAX_CELL_LENGTH) {
                    $this->addError($errors, $errorCount, $lineNumber, "cellule « {$column} » trop longue (max ".self::MAX_CELL_LENGTH.' caractères)');
                    $rowValid = false;
                    break;
                }

                if ($allowed[$column] && $value === '') {
                    $this->addError($errors, $errorCount, $lineNumber, "colonne requise « {$column} » vide");
                    $rowValid = false;
                    break;
                }

                // Neutralisation des formules CSV (OWASP) AVANT stockage :
                // une cellule « =cmd » ne doit jamais pouvoir s'exécuter lors
                // d'un export ultérieur.
                $row[$column] = CsvCellSanitizer::neutralize($value);
            }

            if (! $rowValid) {
                continue;
            }

            $rows[] = $row;
            $validCount++;
        }

        return new CsvParseResult(
            columns: $normalizedHeader,
            rows: $rows,
            errors: $errors,
            validCount: $validCount,
            errorCount: $errorCount,
        );
    }

    /**
     * Normalise l'encodage vers UTF-8 (BOM + Windows-1252/ISO-8859-1).
     */
    private function normalizeEncoding(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3); // BOM UTF-8
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $converted = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        throw CrmImportException::invalidFile('encodage non supporté (UTF-8 ou Windows-1252 attendu)');
    }

    /**
     * Découpe le contenu en lignes (CRLF, LF, CR) et fusionne les champs
     * entre guillemets multi-lignes (guillemets impairs sur une ligne).
     *
     * @return list<string>
     */
    private function splitLines(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $lines = explode("\n", $content);
        $merged = [];
        $buffer = null;

        foreach ($lines as $line) {
            if ($buffer !== null) {
                $line = $buffer."\n".$line;
                $buffer = null;
            }

            if (substr_count($line, '"') % 2 === 1) {
                $buffer = $line; // champ entre guillemets multi-lignes
                continue;
            }

            $merged[] = $line;
        }

        if ($buffer !== null) {
            $merged[] = $buffer;
        }

        return $merged;
    }

    /**
     * Parse une ligne CSV en cellules.
     *
     * @return list<string>
     */
    private function parseLine(string $line, int $lineNumber): array
    {
        $cells = str_getcsv($line);

        return array_map(static fn (mixed $cell): string => (string) $cell, $cells ?? []);
    }

    /**
     * @param  list<array{line: int, message: string}>  $errors
     */
    private function addError(array &$errors, int &$errorCount, int $line, string $message): void
    {
        $errorCount++;

        if (count($errors) < self::MAX_REPORTED_ERRORS) {
            $errors[] = ['line' => $line, 'message' => $message];
        }
    }
}
