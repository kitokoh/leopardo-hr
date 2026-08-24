<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Pdf\ArabicPdfText;
use PHPUnit\Framework\TestCase;

/**
 * Issue #5242 — shaping arabe pour dompdf (rendu « sans cassure »).
 *
 * Vérifie les deux corrections : formes contextuelles (lettres jointes) et
 * inversion RTL (ordre visuel correct dans un rendu LTR).
 */
class ArabicPdfTextTest extends TestCase
{
    public function test_latin_text_is_untouched(): void
    {
        $this->assertSame('Bulletin de Paie', ArabicPdfText::shape('Bulletin de Paie'));
        $this->assertSame('60 000,00 DZD', ArabicPdfText::shape('60 000,00 DZD'));
        $this->assertSame('', ArabicPdfText::shape(''));
    }

    public function test_arabic_word_is_shaped_and_reversed(): void
    {
        $shaped = ArabicPdfText::shape('مرحبا'); // م ر ح ب ا

        // Plus aucune lettre de base (tout converti en formes de présentation).
        $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $shaped));
        $this->assertSame(1, preg_match('/[\x{FB50}-\x{FEFF}]/u', $shaped));

        // Inversion RTL : le premier caractère affiché est la forme FINALE du
        // dernier caractère logique (ا → FE8E puisque précédé d'une lettre
        // connectante).
        $this->assertStringStartsWith("\u{FE8E}", $shaped);

        // Lettres jointes : م initiale (FEE3), ح initiale (FEA3), ب médiane (FE92).
        $this->assertStringContainsString("\u{FEE3}", $shaped);
        $this->assertStringContainsString("\u{FEA3}", $shaped);
        $this->assertStringContainsString("\u{FE92}", $shaped);
    }

    public function test_lam_alef_ligature(): void
    {
        // لا : ل + ا → ligature ﻻ (FEFB isolée).
        $shaped = ArabicPdfText::shape('لا');
        $this->assertStringContainsString("\u{FEFB}", $shaped);
        $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $shaped));

        // ل non suivi d'alef : forme initiale (FEDF), pas de ligature.
        $this->assertStringContainsString("\u{FEDF}", ArabicPdfText::shape('الراتب'));
    }

    public function test_mixed_label_value_keeps_number_readable_and_rtl_order(): void
    {
        // Étiquette arabe + valeur latine : l'ordre des runs est inversé
        // (valeur à gauche, arabe à droite) et la valeur reste intacte.
        $shaped = ArabicPdfText::shape('صافي المستحق: 47 558');

        $this->assertStringContainsString('47 558', $shaped);
        $this->assertStringContainsString("\u{FED6}", $shaped); // ـق finale FED6 (dernier caractère logique de "المستحق")
        // La valeur précède l'arabe dans le résultat (bidi minimal).
        $posValue = mb_strpos($shaped, '47 558');
        $posArabic = mb_strpos($shaped, "\u{FED6}");
        $this->assertNotFalse($posValue);
        $this->assertNotFalse($posArabic);
        $this->assertLessThan($posArabic, $posValue);
    }

    public function test_payslip_title_shape(): void
    {
        // كشف الراتب — titre du bulletin (pas de ل+ا ici : ا ل ر).
        $shaped = ArabicPdfText::shape('كشف الراتب');
        $this->assertSame(0, preg_match('/[\x{0600}-\x{06FF}]/u', $shaped));
        // ك initiale (suivie d.une connectante) = FEDB.
        $this->assertStringContainsString("\u{FEDB}", $shaped);
        // ب finale (dernier caractère logique) — premier caractère affiché.
        $this->assertStringStartsWith("\u{FE90}", $shaped);
    }
}
