'use client';

import { useState, useId } from 'react';
import { motion } from 'framer-motion';
import Link from 'next/link';
import { Calculator, ArrowRight, Clock, TrendingUp, ShieldCheck, CheckCircle2 } from 'lucide-react';

export interface RoiCalculatorProps {
  title?: string;
  subtitle?: string;
  badge?: string;
  ctaText?: string;
  ctaHref?: string;
}

interface SectorConfig {
  id: string;
  name: string;
  hourlyCost: number;
  adminHoursPerEmployee: number;
  softwareSavingsMultiplier: number;
}

const SECTORS: SectorConfig[] = [
  { id: 'services', name: 'Services & PME', hourlyCost: 28, adminHoursPerEmployee: 1.8, softwareSavingsMultiplier: 0.75 },
  { id: 'restaurant', name: 'Restauration & Hôtellerie', hourlyCost: 22, adminHoursPerEmployee: 2.4, softwareSavingsMultiplier: 0.82 },
  { id: 'retail', name: 'Commerce & Retail', hourlyCost: 24, adminHoursPerEmployee: 2.1, softwareSavingsMultiplier: 0.78 },
  { id: 'btp', name: 'BTP & Terrain', hourlyCost: 26, adminHoursPerEmployee: 2.6, softwareSavingsMultiplier: 0.85 },
  { id: 'education', name: 'Écoles & Centres', hourlyCost: 25, adminHoursPerEmployee: 2.0, softwareSavingsMultiplier: 0.80 },
];

export function RoiCalculator({
  title = 'Simulateur de Rentabilité & Gain de Temps',
  subtitle = 'Estimez en temps réel le nombre d’heures et les coûts opérationnels économisés chaque mois avec Leopardo.',
  badge = 'SIMULATEUR DE ROI',
  ctaText = 'Démarrer l’essai gratuit de 14 jours',
  ctaHref = '/signup',
}: RoiCalculatorProps) {
  const [employeeCount, setEmployeeCount] = useState<number>(35);
  const [selectedSectorId, setSelectedSectorId] = useState<string>('restaurant');
  const sliderId = useId();

  const currentSector = SECTORS.find((s) => s.id === selectedSectorId) || SECTORS[0];

  // Calculs financiers & opérationnels
  const totalHoursLostWithoutSoftware = Math.round(employeeCount * currentSector.adminHoursPerEmployee);
  const monthlyHoursSaved = Math.round(totalHoursLostWithoutSoftware * currentSector.softwareSavingsMultiplier);
  const monthlySavingsEuro = Math.round(monthlyHoursSaved * currentSector.hourlyCost);
  const annualSavingsEuro = monthlySavingsEuro * 12;

  // Coût estimé logiciel Leopardo (base indicative ~3€/salarié/mois)
  const estimatedSoftwareCostYearly = Math.round(employeeCount * 3.5 * 12);
  const roiPercentage = Math.round(
    ((annualSavingsEuro - estimatedSoftwareCostYearly) / Math.max(1, estimatedSoftwareCostYearly)) * 100
  );

  return (
    <section className="relative py-24 overflow-hidden bg-slate-950 text-white">
      {/* Halo Stitch de fond */}
      <div
        aria-hidden="true"
        className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[450px] bg-emerald-500/10 blur-[130px] rounded-full pointer-events-none"
      />
      <div
        aria-hidden="true"
        className="absolute bottom-0 right-1/4 w-[500px] h-[350px] bg-amber-400/5 blur-[120px] rounded-full pointer-events-none"
      />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* En-tête de section */}
        <div className="text-center max-w-3xl mx-auto mb-16">
          <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-4">
            <Calculator className="w-3.5 h-3.5" />
            <span>{badge}</span>
          </div>
          <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight mb-4">
            {title}
          </h2>
          <p className="text-slate-400 text-base sm:text-lg leading-relaxed">
            {subtitle}
          </p>
        </div>

        {/* Grille du simulateur */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
          {/* Colonne Paramètres (7 colonnes) */}
          <div className="lg:col-span-7 rounded-3xl bg-slate-900/80 border border-slate-800/80 backdrop-blur-xl p-8 sm:p-10 flex flex-col justify-between shadow-2xl shadow-black/40">
            <div>
              {/* Choix du secteur */}
              <div className="mb-8">
                <label className="block text-sm font-bold text-slate-300 uppercase tracking-wider mb-3">
                  1. Votre Secteur d’Activité
                </label>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                  {SECTORS.map((sector) => {
                    const isSelected = sector.id === selectedSectorId;
                    return (
                      <button
                        key={sector.id}
                        type="button"
                        onClick={() => setSelectedSectorId(sector.id)}
                        className={`px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold transition-all text-left flex items-center justify-between border ${
                          isSelected
                            ? 'bg-emerald-500/15 border-emerald-500/60 text-white shadow-lg shadow-emerald-500/10'
                            : 'bg-slate-800/40 border-slate-700/50 text-slate-400 hover:border-slate-600 hover:text-slate-200'
                        }`}
                      >
                        <span>{sector.name}</span>
                        {isSelected && <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0 ml-1.5" />}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Slider Nombre de collaborateurs */}
              <div className="mb-8">
                <div className="flex justify-between items-baseline mb-3">
                  <label htmlFor={sliderId} className="text-sm font-bold text-slate-300 uppercase tracking-wider">
                    2. Effectif de votre Entreprise
                  </label>
                  <span className="text-2xl sm:text-3xl font-black text-amber-400">
                    {employeeCount} <span className="text-sm font-semibold text-slate-400">salariés</span>
                  </span>
                </div>
                <input
                  id={sliderId}
                  type="range"
                  min="5"
                  max="250"
                  step="5"
                  value={employeeCount}
                  onChange={(e) => setEmployeeCount(Number(e.target.value))}
                  className="w-full h-3 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-400 focus:outline-none"
                  aria-label="Nombre de collaborateurs"
                />
                <div className="flex justify-between text-xs text-slate-500 font-semibold mt-2">
                  <span>5 salariés</span>
                  <span>50</span>
                  <span>100</span>
                  <span>150</span>
                  <span>250+</span>
                </div>
              </div>

              {/* Invariants & Garanties */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-800/60">
                <div className="flex items-start gap-2.5">
                  <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                  <span className="text-xs text-slate-400 leading-snug">
                    Pointage biométrique &amp; mobile sans ressaisie
                  </span>
                </div>
                <div className="flex items-start gap-2.5">
                  <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                  <span className="text-xs text-slate-400 leading-snug">
                    Clôture de paie certifiée en moins de 3 minutes
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Colonne Résultats & ROI (5 colonnes) */}
          <div className="lg:col-span-5 rounded-3xl bg-gradient-to-b from-emerald-950/60 via-slate-900/90 to-slate-950 border border-emerald-500/30 backdrop-blur-xl p-8 sm:p-10 flex flex-col justify-between shadow-2xl shadow-emerald-950/30 relative overflow-hidden">
            {/* Lueur d'or supérieure */}
            <div
              aria-hidden="true"
              className="absolute -top-20 -right-20 w-44 h-44 bg-amber-400/10 rounded-full blur-2xl pointer-events-none"
            />

            <div>
              <div className="text-xs font-bold text-emerald-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                <TrendingUp className="w-4 h-4" />
                <span>Impact Annuel Constaté</span>
              </div>

              {/* Économie Annuelle Principale */}
              <div className="mb-8">
                <p className="text-xs sm:text-sm font-semibold text-slate-400 uppercase tracking-wider mb-1">
                  Économie Nette Estimée / An
                </p>
                <div className="text-4xl sm:text-5xl font-black text-white tracking-tight">
                  {annualSavingsEuro.toLocaleString('fr-FR')} <span className="text-2xl text-emerald-400 font-bold">€</span>
                </div>
                <p className="text-xs text-slate-400 mt-2">
                  Soit environ <strong className="text-slate-200">{monthlySavingsEuro.toLocaleString('fr-FR')} €/mois</strong> d’économies directes.
                </p>
              </div>

              {/* Cartes Métriques */}
              <div className="grid grid-cols-2 gap-3 mb-8">
                <div className="p-4 rounded-2xl bg-slate-900/70 border border-slate-800">
                  <div className="flex items-center gap-1.5 text-slate-400 text-xs font-bold mb-1">
                    <Clock className="w-3.5 h-3.5 text-amber-400" />
                    <span>Temps Gagné</span>
                  </div>
                  <div className="text-2xl font-black text-amber-300">
                    +{monthlyHoursSaved}h
                  </div>
                  <span className="text-[11px] text-slate-400">par mois</span>
                </div>

                <div className="p-4 rounded-2xl bg-slate-900/70 border border-slate-800">
                  <div className="flex items-center gap-1.5 text-slate-400 text-xs font-bold mb-1">
                    <TrendingUp className="w-3.5 h-3.5 text-emerald-400" />
                    <span>ROI Estimé</span>
                  </div>
                  <div className="text-2xl font-black text-emerald-300">
                    +{roiPercentage}%
                  </div>
                  <span className="text-[11px] text-slate-400">sur 12 mois</span>
                </div>
              </div>
            </div>

            {/* CTA Final */}
            <div>
              <Link
                href={ctaHref}
                className="w-full inline-flex items-center justify-center gap-3 px-6 py-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-emerald-500/25 transition-all group"
              >
                <span>{ctaText}</span>
                <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
              </Link>
              <p className="text-center text-[11px] text-slate-400 mt-3">
                Sans engagement • Aucune carte bancaire requise • Setup en 10 minutes
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
