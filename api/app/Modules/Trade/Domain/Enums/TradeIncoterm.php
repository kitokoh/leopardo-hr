<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Incoterms 2020 (ICC) — BC-36 TRADE, TRADE-007/#8482.
 *
 * SOURCE : regles Incoterms 2020 de la Chambre de commerce internationale.
 * Cette table est STATIQUE et VERSIONNEE — elle n'est jamais deduite d'un
 * appel reseau et ne remplace pas la lecture des regles ICC applicables.
 *
 * Enjeu metier (§7 de la spec) : la repartition des couts depend de
 * l'Incoterm. Comparer le prix `EXW` d'un fournisseur au prix `DDP` d'un autre
 * comme s'ils etaient equivalents est une ERREUR courante et couteuse — le
 * moteur de comparaison s'appuie sur cette carte pour refuser une comparaison
 * trompeuse.
 *
 * `buyerPaysFreight()` dit si le FRET PRINCIPAL international est a la charge
 * de l'acheteur (true pour EXW/FCA/FAS/FOB) ou du vendeur (false pour
 * CFR/CIF/CPT/CIP/DAP/DPU/DDP). C'est le discriminant principal du perimetre
 * de cout, donc de la comparabilite des offres.
 *
 * @see docs/specifications/VERTICAL_TRADE_OS.md §7
 */
enum TradeIncoterm: string
{
    case EXW = 'EXW';
    case FCA = 'FCA';
    case FAS = 'FAS';
    case FOB = 'FOB';
    case CFR = 'CFR';
    case CIF = 'CIF';
    case CPT = 'CPT';
    case CIP = 'CIP';
    case DAP = 'DAP';
    case DPU = 'DPU';
    case DDP = 'DDP';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $term): string => $term->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::EXW => 'EXW — a l\'usine',
            self::FCA => 'FCA — franco transporteur',
            self::FAS => 'FAS — franco le long du navire',
            self::FOB => 'FOB — franco a bord',
            self::CFR => 'CFR — cout et fret',
            self::CIF => 'CIF — cout, assurance et fret',
            self::CPT => 'CPT — port paye jusqu\'a',
            self::CIP => 'CIP — port paye, assurance comprise',
            self::DAP => 'DAP — livre au lieu convenu',
            self::DPU => 'DPU — livre et decharge',
            self::DDP => 'DDP — droits acquittes',
        };
    }

    /**
     * Le FRET PRINCIPAL international est-il a la charge de l'acheteur ?
     * Discriminant principal du perimetre de cout.
     */
    public function buyerPaysFreight(): bool
    {
        return in_array($this, [self::EXW, self::FCA, self::FAS, self::FOB], true);
    }

    /**
     * L'ASSURANCE du transport principal est-elle a la charge de l'acheteur ?
     * (CIF et CIP l'incluent cote vendeur.)
     */
    public function buyerPaysInsurance(): bool
    {
        return in_array($this, [self::EXW, self::FCA, self::FAS, self::FOB, self::CFR, self::CPT], true);
    }

    /**
     * Les DROITS ET TAXES a l'import sont-ils a la charge de l'acheteur ?
     * Seul DDP les met a la charge du vendeur — un prix DDP et un prix EXW
     * different donc radicalement de nature.
     */
    public function buyerPaysDuty(): bool
    {
        return $this !== self::DDP;
    }

    /**
     * Deux offres sont sur un perimetre de cout COMPARABLE seulement si elles
     * partagent le meme Incoterm. Sinon le systeme declare explicitement
     * « non comparable » plutot que de comparer des prix heterogenes.
     */
    public function isComparableWith(self $other): bool
    {
        return $this === $other;
    }
}
