<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Extraction;

use App\Modules\Recruitment\Domain\Contracts\ResumeTextExtraction;
use Psr\Log\LoggerInterface;

/**
 * Extraction du texte d'un CV, sans dépendance PHP supplémentaire.
 *
 * Trois formats sont acceptés par le formulaire de candidature (PDF, DOC,
 * DOCX) et chacun a son outil système :
 *
 *  - **PDF**  → `pdftotext` (poppler-utils). `-layout` conserve la disposition
 *    en colonnes, ce qui compte pour un CV : sans lui, un CV sur deux colonnes
 *    ressort en mots entremêlés et les compétences deviennent introuvables.
 *  - **DOCX** → lecture directe de `word/document.xml` dans l'archive ZIP.
 *    Aucun binaire externe : `ZipArchive` et `DOMDocument` sont dans l'image
 *    PHP standard, et c'est le format le plus courant des CV modernes.
 *  - **DOC**  → `antiword`, avec repli sur `catdoc`. L'ancien format binaire
 *    Word n'est pas lisible sans outil dédié.
 *
 * ## Pourquoi `proc_open` et pas `shell_exec`
 *
 * Les arguments sont passés en TABLEAU, jamais concaténés dans une chaîne :
 * un nom de fichier contenant `; rm -rf /` reste un nom de fichier. Le nom
 * d'origine du CV vient d'un visiteur non authentifié — c'est la seule
 * injection qui compte ici.
 *
 * ## Pourquoi tout échoue en `null` et non en exception
 *
 * Un CV scanné (image dans un PDF) ne contient aucun texte extractible : ce
 * n'est pas une erreur, c'est un cas normal. Idem pour un PDF chiffré. Lever
 * une exception ferait échouer la candidature d'un candidat parfaitement
 * légitime. On journalise et on rend `null` ; le score ATS sera alors
 * simplement non calculable.
 */
final class ShellResumeTextExtractor implements ResumeTextExtraction
{
    /** Au-delà, on considère que le document n'est pas un CV mais un piège à mémoire. */
    private const MAX_TEXT_LENGTH = 200_000;

    /** Un CV de 40 pages n'existe pas ; au-delà, l'extraction est suspecte. */
    private const TIMEOUT_SECONDS = 20;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function extract(string $absolutePath, string $originalName): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $text = match ($extension) {
            'pdf' => $this->fromPdf($absolutePath),
            'docx' => $this->fromDocx($absolutePath),
            'doc' => $this->fromDoc($absolutePath),
            default => null,
        };

        if ($text === null) {
            return null;
        }

        $normalized = $this->normalize($text);

        return $normalized === '' ? null : $normalized;
    }

    private function fromPdf(string $path): ?string
    {
        // `-layout` : garde la disposition. `-` en sortie = stdout.
        return $this->run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
    }

    private function fromDoc(string $path): ?string
    {
        // antiword est le plus fidèle sur les .doc ; catdoc sert de repli sur
        // les installations qui ne l'ont pas.
        return $this->run(['antiword', '-m', 'UTF-8.txt', $path])
            ?? $this->run(['catdoc', '-d', 'utf-8', $path]);
    }

    /**
     * DOCX = archive ZIP contenant `word/document.xml`.
     *
     * On ne cherche pas à reconstituer la mise en forme : on veut les mots.
     * Les paragraphes (`w:p`) deviennent des sauts de ligne pour que deux
     * compétences voisines ne se collent pas en un mot inexistant.
     */
    private function fromDocx(string $path): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->logger->warning('recruitment.resume_text.zip_unavailable');

            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        // Le XML d'un DOCX est du XML : on le parse, on ne le dépouille pas
        // avec des expressions régulières. `LIBXML_NONET` interdit toute
        // résolution d'entité externe — un DOCX piégé ne doit pas pouvoir
        // faire lire un fichier local au serveur.
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $parts = [];
        foreach ($dom->getElementsByTagName('p') as $paragraph) {
            $parts[] = $paragraph->textContent;
        }

        return implode("\n", $parts);
    }

    /**
     * Exécute un binaire et rend sa sortie, ou `null` s'il est absent/échoue.
     *
     * @param  list<string>  $command
     */
    private function run(array $command): ?string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            // Binaire absent : cas normal sur une image minimale, pas une
            // anomalie. On le trace une fois pour l'exploitant.
            $this->logger->info('recruitment.resume_text.binary_unavailable', [
                'binary' => $command[0] ?? 'unknown',
            ]);

            return null;
        }

        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $stdout === '') {
            return null;
        }

        return $stdout;
    }

    /**
     * Normalise le texte extrait.
     *
     * On écrase les espaces multiples et les sauts de ligne redondants : un
     * PDF en sort souvent avec des colonnes de blancs, et le score compare des
     * mots, pas une mise en page.
     */
    private function normalize(string $text): string
    {
        // Certains PDF sortent en UTF-16 malgré `-enc UTF-8` : on retire le BOM
        // et les octets nuls, qui casseraient `mb_strtolower` plus loin.
        $text = str_replace(["\xEF\xBB\xBF", "\x00"], '', $text);

        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            $text = is_string($converted) ? $converted : '';
        }

        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);
        }

        return $text;
    }
}
