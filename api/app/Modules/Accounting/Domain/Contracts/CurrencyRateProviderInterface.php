<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Contracts;

/**
 * Fournisseur de taux de change — issue #5270 (multi-devises).
 *
 * Contrat d'extension pour les sources de taux EXTERNES (ex. BCE/ECB,
 * Open Exchange Rates, banque locale). L'implémentation par défaut du
 * module est le taux manuel (`ManualCurrencyRateProvider`) porté par
 * `accounting_documents.exchange_rate` ; une source temps réel s'intègre
 * par une nouvelle implémentation de ce contrat, injectée via le provider
 * du module — aucun appel réseau dans la v1 (#5272 hors périmètre).
 */
interface CurrencyRateProviderInterface
{
    /**
     * Taux de change de 1 unité de $from exprimé dans $to (multiplication) :
     * `montant_converti = montant * rate(from, to)`.
     */
    public function rate(string $from, string $to): float;

    /**
     * Identifiant stable de la source, ex. 'manual', 'ecb', 'open_exchange_rates'.
     */
    public function source(): string;

    /**
     * La source peut-elle fournir un taux pour cette paire ?
     */
    public function supports(string $from, string $to): bool;
}
