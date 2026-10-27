<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Support\CountryDefaults;

/**
 * REST-OS-8 (#8610) — Mentions légales d'un reçu client, paramétrables par
 * juridiction.
 *
 * L'issue demande un reçu « conforme (paramétrable selon juridiction) ». La
 * conformité d'un ticket de caisse n'est PAS universelle : la France impose la
 * mention de l'identifiant TVA et le taux appliqué, la Turquie impose le numéro
 * de contribuable (VKN) et un libellé de document normalisé, la zone CEMAC /
 * CEDEAO impose le numéro de contribuable et la mention du régime.
 *
 * Décision de conception — les mentions sont RÉSOLUES ICI, pas dans le
 * renderer : le renderer ESC/POS reste pur et sans connaissance fiscale, et
 * une évolution réglementaire se fait dans un seul fichier testable sans
 * toucher au format d'impression.
 *
 * Décision de conception — aucune mention n'est INVENTÉE. Un pays sans règle
 * connue reçoit les mentions génériques (nom, adresse, téléphone, référence,
 * date) et rien de plus : imprimer une mention fiscale fausse serait pire que
 * de n'en imprimer aucune. Les identifiants fiscaux (TVA, VKN, NIU) ne sont
 * imprimés que s'ils sont RÉELLEMENT configurés sur l'entreprise — jamais un
 * placeholder du type « TVA : à compléter ».
 */
final class ReceiptMentionsResolver
{
    /**
     * Mentions génériques, valables partout : elles identifient l'émetteur et
     * la transaction, ce qu'exige tout reçu.
     *
     * @param  array<string, string|null>  $issuer
     * @return list<string>
     */
    public function resolve(
        ?string $country,
        array $issuer,
        string $reference,
        string $printedAt,
    ): array {
        $mentions = [];

        $name = $this->clean($issuer['name'] ?? null);
        if ($name !== null) {
            $mentions[] = $name;
        }

        $address = $this->clean($issuer['address'] ?? null);
        if ($address !== null) {
            $mentions[] = $address;
        }

        $phone = $this->clean($issuer['phone'] ?? null);
        if ($phone !== null) {
            $mentions[] = $phone;
        }

        // Identifiant fiscal : imprimé UNIQUEMENT s'il est configuré, et sous
        // le libellé attendu par la juridiction.
        $taxId = $this->clean($issuer['tax_id'] ?? null);
        if ($taxId !== null) {
            $label = $this->taxIdLabel($country);
            $mentions[] = $label.': '.$taxId;
        }

        $mentions[] = 'Ref. '.$reference;
        $mentions[] = $printedAt;

        return $mentions;
    }

    /**
     * Libellé de l'identifiant fiscal selon la juridiction.
     *
     * - FR : « TVA intracommunautaire » (art. 242 nonies A CGI) ;
     * - TR : « VKN » (Vergi Kimlik Numarası) ;
     * - CEMAC / CEDEAO (CM, GA, CG, TD, CF, GQ, SN, CI, ML, BF, BJ, TG, NE) :
     *   « N° contribuable » (NIU / IFU selon le pays) ;
     * - CA : « Business Number » (ARC/BN) ;
     * - défaut : « Tax ID », libellé neutre et non trompeur.
     */
    private function taxIdLabel(?string $country): string
    {
        $code = strtoupper(trim((string) $country));

        return match ($code) {
            'FR' => 'TVA intracommunautaire',
            'TR' => 'VKN',
            'CA' => 'Business Number',
            'CM', 'GA', 'CG', 'TD', 'CF', 'GQ', 'SN', 'CI', 'ML', 'BF', 'BJ', 'TG', 'NE' => 'N° contribuable',
            default => 'Tax ID',
        };
    }

    /**
     * Indique si la juridiction exige l'affichage du taux de TVA sur le reçu.
     *
     * La France l'impose (art. 286-I-3° bis CGI : le taux applicable doit
     * figurer sur le ticket). Ailleurs, l'affichage du taux reste autorisé mais
     * n'est pas forcé — on ne prétend pas connaître toutes les législations.
     */
    public function requiresTaxRate(?string $country): bool
    {
        return strtoupper(trim((string) $country)) === 'FR';
    }

    /**
     * Devise par défaut de la juridiction (repli si l'entreprise n'en déclare
     * aucune) — évite d'imprimer un montant sans unité.
     */
    public function defaultCurrency(?string $country): string
    {
        return CountryDefaults::for($country)['currency'];
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
