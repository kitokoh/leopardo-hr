<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Contracts;

/**
 * Extraction du TEXTE d'un CV.
 *
 * Distinct de `ResumeScanner` (qui dit si le fichier est malveillant) : ici on
 * cherche le contenu lisible, pour que le score de correspondance ATS puisse
 * comparer les compétences demandées par l'offre à ce que le candidat a
 * réellement écrit — et non à sa seule lettre de motivation.
 *
 * Le contrat ne lève jamais d'exception : un CV illisible (scan image, PDF
 * chiffré, format exotique) rend `null`. Une candidature ne doit pas échouer
 * parce qu'on n'a pas su lire le document ; le recruteur verra simplement un
 * score non calculable plutôt qu'un score faux.
 */
interface ResumeTextExtraction
{
    /**
     * @param  string  $absolutePath  chemin absolu du fichier sur le disque
     * @param  string  $originalName  nom d'origine, pour déduire le format
     * @return string|null texte extrait et normalisé, ou `null` si illisible
     */
    public function extract(string $absolutePath, string $originalName): ?string;
}
