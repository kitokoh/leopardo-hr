<?php

declare(strict_types=1);

namespace Tests\Unit\Recruitment;

use App\Modules\Recruitment\Infrastructure\Extraction\ShellResumeTextExtractor;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * ATS (#9461) — extraction du texte des CV.
 *
 * Ces tests exercent le VRAI extracteur, avec les binaires système
 * (`pdftotext`, `antiword`) et `ZipArchive`. Un test qui simulerait
 * l'extraction ne prouverait rien : le risque ici n'est pas la logique PHP,
 * c'est l'interaction avec des outils externes et des formats binaires.
 *
 * Les fixtures sont générées à la volée plutôt que versionnées : un PDF de
 * test commité devient illisible au premier changement d'outil, et personne
 * ne sait plus ce qu'il contient.
 */
class ResumeTextExtractorTest extends TestCase
{
    private ShellResumeTextExtractor $extractor;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ShellResumeTextExtractor(new NullLogger);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function tempPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cv_').'.'.$extension;
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Construit un PDF minimal mais VALIDE contenant le texte demandé.
     *
     * On écrit la structure PDF à la main : c'est la seule façon de garantir
     * que le test ne dépend pas d'une bibliothèque de génération, et que le
     * contenu est bien du texte (pas une image) — c'est précisément ce que
     * `pdftotext` doit savoir lire.
     */
    private function makePdf(string $text): string
    {
        $path = $this->tempPath('pdf');

        $content = "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        file_put_contents($path, $pdf);

        return $path;
    }

    private function makeDocx(string $text): string
    {
        $path = $this->tempPath('docx');

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="xml" ContentType="application/xml"/></Types>');

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body>'
            .'</w:document>');

        $zip->close();

        return $path;
    }

    public function test_it_extracts_text_from_a_pdf(): void
    {
        $path = $this->makePdf('Developpeur PHP Laravel PostgreSQL');

        $text = $this->extractor->extract($path, 'cv.pdf');

        $this->assertNotNull($text, 'pdftotext doit extraire le texte d un PDF texte.');
        $this->assertStringContainsString('Laravel', $text);
        $this->assertStringContainsString('PostgreSQL', $text);
    }

    public function test_it_extracts_text_from_a_docx(): void
    {
        $path = $this->makeDocx('Experiences: PHP, Laravel, PostgreSQL');

        $text = $this->extractor->extract($path, 'cv.docx');

        $this->assertNotNull($text);
        $this->assertStringContainsString('Laravel', $text);
        $this->assertStringContainsString('PostgreSQL', $text);
    }

    public function test_a_docx_without_document_xml_yields_null(): void
    {
        $path = $this->tempPath('docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'pas un document word');
        $zip->close();

        $this->assertNull($this->extractor->extract($path, 'cv.docx'));
    }

    public function test_a_corrupted_file_yields_null_instead_of_throwing(): void
    {
        $path = $this->tempPath('pdf');
        file_put_contents($path, 'ceci n est pas un pdf');

        // Le point important : pas d'exception. Une candidature ne doit pas
        // echouer parce qu'un CV est illisible.
        $this->assertNull($this->extractor->extract($path, 'cv.pdf'));
    }

    public function test_a_missing_file_yields_null(): void
    {
        $this->assertNull($this->extractor->extract('/tmp/inexistant-'.uniqid().'.pdf', 'cv.pdf'));
    }

    public function test_an_unsupported_extension_yields_null(): void
    {
        $path = $this->tempPath('txt');
        file_put_contents($path, 'du texte brut');

        $this->assertNull($this->extractor->extract($path, 'cv.txt'));
    }

    public function test_extracted_text_is_normalized(): void
    {
        $path = $this->makeDocx('PHP     Laravel');

        $text = $this->extractor->extract($path, 'cv.docx');

        $this->assertNotNull($text);
        // Les espaces multiples sont ecrases : le score compare des mots, pas
        // une mise en page.
        $this->assertStringNotContainsString('     ', $text);
    }
}
