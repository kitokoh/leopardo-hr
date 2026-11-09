<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Contracts;

use App\Modules\Recruitment\Domain\ValueObjects\ResumeScanVerdict;

/**
 * BC-10 (#9217) — analyse antivirus d'un CV déposé par un candidat.
 *
 * Le fichier est ensuite téléchargeable par un recruteur : un document piégé
 * est un vecteur d'attaque classique contre le poste de travail de celui qui
 * l'ouvre. La validation par type MIME et taille (`PublicApplyRequest`) ne
 * regarde que l'enveloppe, jamais le contenu.
 *
 * Contrat volontairement étroit : on passe un chemin, on reçoit un verdict.
 * Aucune implémentation ne doit lever d'exception pour un fichier infecté —
 * « infecté » est un verdict, pas une erreur. En revanche une PANNE du scanner
 * est un état distinct (`ResumeScanVerdict::unavailable()`), parce qu'un
 * scanner en panne ne doit pas bloquer une candidature légitime, et ne doit
 * surtout pas être confondu avec « fichier sain ».
 */
interface ResumeScanner
{
    /**
     * Analyse le fichier et rend un verdict.
     *
     * @param  string  $absolutePath  chemin absolu du fichier à analyser
     * @param  string  $originalName  nom d'origine, pour la traçabilité
     */
    public function scan(string $absolutePath, string $originalName): ResumeScanVerdict;

    /**
     * Identifiant de l'implémentation, journalisé avec le verdict.
     *
     * Permet de savoir APRÈS COUP avec quel moteur un CV a été jugé sain —
     * indispensable pour re-scanner après une mise à jour de la base virale.
     */
    public function name(): string;
}
