<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

/**
 * TRADE-007 (#8482, BC-36 TRADE, spec §8) — registre d'etat des integrations
 * externes de la verticale Trade.
 *
 * SOURCE DE VERITE : la configuration serveur (`config('services.trade_*')`).
 * Aucun secret ne sort jamais d'ici — seuls `configured` / `available` /
 * `mode` / `reason` sont exposes.
 *
 * REGLE PRODUIT (spec §8, exigence de mission) : une integration non
 * configuree n'est JAMAIS presentee comme operationnelle. `available` reste
 * `false` tant qu'aucune source n'est branchee, et `reason` explique ce qu'il
 * manque. Les surfaces (tenant et superadmin) affichent « non configure » au
 * lieu d'un ecran vide ou d'un resultat invente.
 *
 * AUCUN SERVICE PAYANT N'EST SOUSCRIT et aucune cle n'est inventee : les
 * adaptateurs sont livres en mode DEGRADE explicite (`null` / `manual`). Le
 * jour ou un fournisseur est retenu, il suffit de renseigner les variables
 * d'environnement documentees dans `config/services.php` — `available` passe
 * alors a `true` sans changement de code.
 *
 * @see \App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeMetaController
 */
final class TradeIntegrationRegistry
{
    /** Taux de change (FX trace : rate / date / source). */
    public const KEY_FX = 'fx';

    /** Tarifs douaniers et informations reglementaires. */
    public const KEY_TARIFF = 'tariff';

    /** Verification d'entreprises / certifications. */
    public const KEY_COMPANY_VERIFICATION = 'company_verification';

    /** Cotations transporteurs / transitaires. */
    public const KEY_CARRIER_QUOTE = 'carrier_quote';

    /** Mode « saisi par l'utilisateur » — degrade mais pleinement utilisable. */
    public const MODE_MANUAL = 'manual';

    /** Mode « aucune source » — la capacite est INDISPONIBLE. */
    public const MODE_NULL = 'null';

    /** Mode « adaptateur HTTP configure ». */
    public const MODE_HTTP = 'http';

    /**
     * Etat des 4 integrations de la spec §8, dans un ordre stable (le front
     * affiche le tableau tel quel).
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     purpose: string,
     *     mode: string,
     *     configured: bool,
     *     available: bool,
     *     reason: ?string
     * }>
     */
    public function all(): array
    {
        return [
            $this->fx(),
            $this->tariff(),
            $this->companyVerification(),
            $this->carrierQuote(),
        ];
    }

    /**
     * Etat d'une integration par cle, `null` si la cle est inconnue.
     *
     * @return array{key:string,label:string,purpose:string,mode:string,configured:bool,available:bool,reason:?string}|null
     */
    public function get(string $key): ?array
    {
        foreach ($this->all() as $integration) {
            if ($integration['key'] === $key) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Vrai si TOUTES les integrations optionnelles sont branchees. Utilise par
     * les surfaces pour signaler un mode degrade global, jamais pour masquer
     * une indisponibilite partielle.
     */
    public function allConfigured(): bool
    {
        foreach ($this->all() as $integration) {
            if (! $integration['configured']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{key:string,label:string,purpose:string,mode:string,configured:bool,available:bool,reason:?string}
     */
    private function fx(): array
    {
        // Le taux peut TOUJOURS etre saisi par l'utilisateur (rate + date +
        // source `manual` traces en base) : la capacite est donc disponible,
        // meme sans source automatique. On ne pretend pas pour autant que la
        // source est branchee.
        $mode = $this->mode('trade_fx', self::MODE_MANUAL);
        $configured = $mode === self::MODE_HTTP && $this->hasBaseUrl('trade_fx');

        return [
            'key' => self::KEY_FX,
            'label' => 'Taux de change',
            'purpose' => 'Conversion multi-devises des postes de cout rendu, avec date et source du taux tracees.',
            'mode' => $mode,
            'configured' => $configured,
            'available' => true,
            'reason' => $configured
                ? null
                : 'Aucune source automatique de taux n\'est branchee : le taux est saisi manuellement par l\'utilisateur et sa source est indiquee (« manual »). Aucun taux n\'est jamais invente par la plateforme.',
        ];
    }

    /**
     * @return array{key:string,label:string,purpose:string,mode:string,configured:bool,available:bool,reason:?string}
     */
    private function tariff(): array
    {
        $mode = $this->mode('trade_tariff', self::MODE_NULL);
        $configured = $mode === self::MODE_HTTP && $this->hasBaseUrl('trade_tariff');

        return [
            'key' => self::KEY_TARIFF,
            'label' => 'Tarifs douaniers',
            'purpose' => 'Estimation des droits et taxes a partir de l\'espece tarifaire et des accords applicables.',
            'mode' => $mode,
            'configured' => $configured,
            'available' => $configured,
            'reason' => $configured
                ? null
                : 'Aucune source de tarifs douaniers n\'est configuree : la plateforme ne chiffre pas les droits et taxes. Elle fournit la checklist documentaire et oriente vers un declarant competent.',
        ];
    }

    /**
     * @return array{key:string,label:string,purpose:string,mode:string,configured:bool,available:bool,reason:?string}
     */
    private function companyVerification(): array
    {
        $mode = $this->mode('trade_company_verification', self::MODE_NULL);
        $configured = $mode === self::MODE_HTTP && $this->hasBaseUrl('trade_company_verification');

        return [
            'key' => self::KEY_COMPANY_VERIFICATION,
            'label' => 'Verification d\'entreprises',
            'purpose' => 'Confirmation d\'identite et de certifications des contreparties selon les sources disponibles par pays.',
            'mode' => $mode,
            'configured' => $configured,
            'available' => $configured,
            'reason' => $configured
                ? null
                : 'Aucun registre de verification n\'est branche : le statut de verification d\'une contrepartie reste « non verifie ». La plateforme ne certifie aucune entreprise.',
        ];
    }

    /**
     * @return array{key:string,label:string,purpose:string,mode:string,configured:bool,available:bool,reason:?string}
     */
    private function carrierQuote(): array
    {
        $mode = $this->mode('trade_carrier_quote', self::MODE_NULL);
        $configured = $mode === self::MODE_HTTP && $this->hasBaseUrl('trade_carrier_quote');

        return [
            'key' => self::KEY_CARRIER_QUOTE,
            'label' => 'Cotations transport',
            'purpose' => 'Obtention automatique de tarifs aupres de transporteurs et transitaires.',
            'mode' => $mode,
            'configured' => $configured,
            'available' => $configured,
            'reason' => $configured
                ? null
                : 'Aucun transporteur n\'est connecte par API : les demandes de cotation sont diffusees a des prestataires qui repondent dans la plateforme. Les offres recues sont saisies ou importees, jamais simulees.',
        ];
    }

    /**
     * Mode effectif d'une integration, borne a la liste connue : une valeur
     * exotique en configuration retombe sur le degre degrade plutot que de
     * faire croire a un adaptateur operationnel.
     */
    private function mode(string $configKey, string $fallback): string
    {
        $mode = config("services.{$configKey}.driver", $fallback);

        return in_array($mode, [self::MODE_MANUAL, self::MODE_NULL, self::MODE_HTTP], true)
            ? $mode
            : $fallback;
    }

    /**
     * Vrai si l'adaptateur HTTP dispose d'une URL de base. Sans elle, un mode
     * `http` reste NON configure — et l'integration reste indisponible.
     */
    private function hasBaseUrl(string $configKey): bool
    {
        $baseUrl = config("services.{$configKey}.base_url");

        return is_string($baseUrl) && trim($baseUrl) !== '';
    }
}
