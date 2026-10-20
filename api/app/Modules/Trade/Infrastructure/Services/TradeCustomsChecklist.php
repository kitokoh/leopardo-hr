<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Modules\Trade\Domain\Enums\TradeDocumentType;

/**
 * Checklist documentaire douaniere — BC-36 TRADE, TRADE-007/#8482.
 *
 * REGLES DETERMINISTES ET VERSIONNEES, pas d'IA : la liste des documents
 * potentiellement requis est derivee de faits objectifs (mode de transport,
 * Incoterm, disponibilite du code douanier) selon les pratiques du commerce
 * international.
 *
 * POURQUOI DETERMINISTE : une exigence documentaire inventee par un modele
 * ferait perdre du temps et de l'argent a l'utilisateur, voire bloquerait une
 * expedition. Ce service n'invente rien : il enumere ce qui est CONVENTIONNEL
 * et signale que les exigences reelles dependent de la reglementation du pays
 * d'importation — a faire confirmer par un professionnel.
 *
 * Le texte de non-responsabilite est expose par la constante {@see self::DISCLAIMER}
 * et doit accompagner TOUTE reponse de l'API qui presente une estimation ou une
 * checklist (exigence de conformite legale : Leopardo facilite l'estimation et
 * la preparation, il ne realise aucune declaration officielle).
 */
final class TradeCustomsChecklist
{
    public const DISCLAIMER = 'Ces informations sont une AIDE A LA PREPARATION. '
        .'Elles ne constituent ni une decision douaniere officielle, ni une garantie de conformite. '
        .'Les exigences reelles dependent de la reglementation du pays d\'importation et doivent etre '
        .'confirmees aupres d\'un declarant en douane ou d\'un transitaire agree.';

    /**
     * Documents potentiellement requis, par ordre de priorite decroissante.
     *
     * @return list<string>
     */
    public function requiredDocuments(
        ?string $transportMode = null,
        ?string $incoterm = null,
        bool $hasHsCode = false,
    ): array {
        // Socle documentaire de toute operation d'import/export.
        $documents = [
            TradeDocumentType::CommercialInvoice->value,
            TradeDocumentType::PackingList->value,
        ];

        // Le certificat d'origine conditionne l'eligibilite aux regimes
        // preferentiels : son utilite justifie de le proposer des qu'un
        // Incoterm est connu, donc que le perimetre commercial est defini.
        if ($incoterm !== null) {
            $documents[] = TradeDocumentType::CertificateOfOrigin->value;
        }

        // Connaissement maritime ou LTA aerienne selon le mode retenu.
        if ($transportMode === 'sea') {
            $documents[] = TradeDocumentType::BillOfLading->value;
        }

        if ($transportMode === 'air') {
            $documents[] = TradeDocumentType::AirwayBill->value;
        }

        // L'assurance est due cote vendeur en CIF/CIP ; ailleurs elle peut
        // incomber a l'acheteur — on ne la reclame donc pas d'office.
        if (in_array($incoterm, ['CIF', 'CIP'], true)) {
            $documents[] = TradeDocumentType::InsuranceCertificate->value;
        }

        // Sans code douanier connu, la classification reste a faire : la
        // declaration est attendue mais son contenu serait incomplet.
        if ($hasHsCode) {
            $documents[] = TradeDocumentType::CustomsDeclaration->value;
        }

        return array_values(array_unique($documents));
    }

    /**
     * Informations manquantes, a completer AVANT de pouvoir chiffrer les droits.
     *
     * @return list<string>
     */
    public function missingInformation(
        ?string $hsCode,
        ?string $originCountry,
        ?string $destinationCountry,
        ?string $incoterm,
        ?int $goodsValueMinor,
    ): array {
        $missing = [];

        if ($hsCode === null || trim($hsCode) === '') {
            $missing[] = 'hs_code';
        }

        if ($originCountry === null || trim($originCountry) === '') {
            $missing[] = 'origin_country';
        }

        if ($destinationCountry === null || trim($destinationCountry) === '') {
            $missing[] = 'destination_country';
        }

        if ($incoterm === null || trim($incoterm) === '') {
            $missing[] = 'incoterm';
        }

        if ($goodsValueMinor === null || $goodsValueMinor <= 0) {
            $missing[] = 'goods_value';
        }

        return $missing;
    }

    /**
     * Le dossier est-il chiffrable du point de vue des donnees ?
     * Une liste de manques vide signifie « chiffrable », JAMAIS « conforme ».
     *
     * @param  list<string>  $missingInformation
     */
    public function isComplete(array $missingInformation): bool
    {
        return $missingInformation === [];
    }

    public function disclaimer(): string
    {
        return self::DISCLAIMER;
    }
}
