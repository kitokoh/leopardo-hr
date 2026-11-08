'use client';

import React from 'react';
import { Wifi, ShieldCheck, CheckCircle2, UserCheck, ScanLine, Radio } from 'lucide-react';

interface ZKTecoKioskMockupProps {
  className?: string;
  locale?: string;
}

export function ZKTecoKioskMockup({ className = '', locale = 'fr' }: ZKTecoKioskMockupProps) {
  const isEn = locale === 'en';
  const isTr = locale === 'tr';
  const isAr = locale === 'ar';

  const terminalLabel = isEn
    ? 'TERMINAL KIOSK #04 • LIVE'
    : isTr
    ? 'TERMINAL KIOSK #04 • CANLI'
    : isAr
    ? 'جهاز الحضور الذكي #04 • مباشر'
    : 'TERMINAL KIOSK #04 • SITE PRO';

  const welcomeText = isEn
    ? 'Present badge or look at screen'
    : isTr
    ? 'Kart okutun veya ekrana bakin'
    : isAr
    ? 'يرجى تمرير البطاقة أو توجيه الوجه'
    : 'Présentez votre badge ou votre visage';

  const checkinSuccessText = isEn
    ? 'Check-in recorded'
    : isTr
    ? 'Giris onaylandi'
    : isAr
    ? 'تم تسجيل الدخول بنجاح'
    : 'Pointage validé';

  const employeeName = 'Sophie Diallo';
  const roleText = isEn ? 'Operations • 08:42:15' : isTr ? 'Operasyon • 08:42:15' : isAr ? 'العمليات • 08:42:15' : 'Opérations • 08:42:15';

  const offlineGuaranteeText = isEn
    ? '100% Offline ready'
    : isTr
    ? '%100 Cevrimdisi mod'
    : isAr
    ? 'جاهز 100% بدون إنترنت'
    : '100% Hors-ligne garanti';

  const aesText = isEn
    ? 'AES-256 secure'
    : isTr
    ? 'AES-256 guvenli'
    : isAr
    ? 'تشفير آمن AES-256'
    : 'Chiffrement AES-256';

  return (
    <div className={`relative mx-auto w-full max-w-[280px] sm:max-w-[320px] select-none ${className}`}>
      {/* Halo d'ambiance vert émeraude / ambre derrière la borne */}
      <div
        className="absolute -inset-4 rounded-[2.5rem] bg-gradient-to-br from-emerald-500/25 via-cyan-500/15 to-amber-500/20 blur-2xl opacity-75 pointer-events-none"
        aria-hidden="true"
      />

      {/* Châssis extérieur de la borne en aluminium brossé sombre */}
      <div className="relative rounded-[2rem] p-3 sm:p-3.5 bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 border-2 border-slate-700/80 shadow-[0_25px_60px_-15px_rgba(2,6,23,0.9)] ring-1 ring-white/10">
        {/* Module supérieur : Caméra de reconnaissance faciale & Capteur infrarouge */}
        <div className="flex items-center justify-between px-3 py-1 mb-2 bg-slate-950/60 rounded-full border border-slate-800/80">
          <div className="flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_8px_rgba(52,211,153,0.8)]" />
            <span className="text-[10px] font-mono font-bold tracking-wider text-emerald-400">
              LEOPARDO OS
            </span>
          </div>

          {/* Double lentille caméra optique + IR */}
          <div className="flex items-center gap-1.5 bg-slate-900 px-2 py-0.5 rounded-full border border-slate-700/50">
            <span className="w-1.5 h-1.5 rounded-full bg-cyan-400 shadow-[0_0_6px_rgba(34,211,238,0.8)]" />
            <span className="w-1.5 h-1.5 rounded-full bg-red-400/80" />
            <span className="text-[9px] font-mono text-slate-400">AI-CAM</span>
          </div>
        </div>

        {/* Écran tactile tactile haute définition */}
        <div className="relative rounded-xl overflow-hidden bg-slate-950 border border-slate-800/90 shadow-inner p-3 flex flex-col justify-between min-h-[260px]">
          {/* Lueur de balayage écran */}
          <div
            className="absolute inset-0 bg-gradient-to-b from-emerald-500/5 via-transparent to-cyan-500/5 pointer-events-none"
            aria-hidden="true"
          />

          {/* En-tête de l'écran tactile */}
          <div className="flex items-center justify-between border-b border-slate-800/80 pb-2 mb-2">
            <div className="flex items-center gap-1 text-[10px] text-slate-400 font-medium">
              <Radio className="w-3 h-3 text-emerald-400 animate-pulse" />
              <span className="truncate max-w-[130px]">{terminalLabel}</span>
            </div>
            <div className="text-[11px] font-mono font-bold text-amber-300">
              08:42:15
            </div>
          </div>

          {/* Viseur de reconnaissance faciale holographique */}
          <div className="relative my-2 py-2.5 px-3 rounded-lg bg-gradient-to-b from-slate-900/90 to-slate-950/90 border border-emerald-500/30 flex flex-col items-center justify-center overflow-hidden">
            {/* Ligne laser de balayage animé */}
            <div
              className="absolute inset-x-0 h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_8px_rgba(52,211,153,0.9)] opacity-70 animate-bounce pointer-events-none"
              style={{ animationDuration: '2.5s' }}
              aria-hidden="true"
            />

            {/* Cadre de visée faciale */}
            <div className="w-12 h-12 rounded-xl border border-dashed border-emerald-400/60 flex items-center justify-center mb-1.5 relative">
              <ScanLine className="w-6 h-6 text-emerald-400" />
              <span className="absolute -top-1 -left-1 w-2 h-2 border-t-2 border-l-2 border-emerald-400" />
              <span className="absolute -top-1 -right-1 w-2 h-2 border-t-2 border-r-2 border-emerald-400" />
              <span className="absolute -bottom-1 -left-1 w-2 h-2 border-b-2 border-l-2 border-emerald-400" />
              <span className="absolute -bottom-1 -right-1 w-2 h-2 border-b-2 border-r-2 border-emerald-400" />
            </div>

            <span className="text-[10px] font-medium text-slate-300 text-center">
              {welcomeText}
            </span>
          </div>

          {/* Carte de validation de pointage instantané (Live Badge) */}
          <div className="rounded-lg bg-emerald-950/70 border border-emerald-500/40 p-2.5 flex items-center gap-2.5 shadow-sm shadow-emerald-950">
            <div className="w-8 h-8 rounded-full bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center flex-shrink-0 text-emerald-300">
              <CheckCircle2 className="w-4 h-4 text-emerald-400" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-1.5">
                <span className="text-xs font-bold text-white tracking-tight">
                  {employeeName}
                </span>
                <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-500/30 text-emerald-300 border border-emerald-400/30">
                  {checkinSuccessText}
                </span>
              </div>
              <div className="text-[10px] font-mono text-slate-400 truncate">
                {roleText}
              </div>
            </div>
          </div>

          {/* Pied d'écran : Réassurance technique */}
          <div className="flex items-center justify-between pt-2 border-t border-slate-800/80 text-[9px] text-slate-400">
            <div className="flex items-center gap-1">
              <Wifi className="w-3 h-3 text-cyan-400" />
              <span>{offlineGuaranteeText}</span>
            </div>
            <div className="flex items-center gap-1">
              <ShieldCheck className="w-3 h-3 text-amber-400" />
              <span>{aesText}</span>
            </div>
          </div>
        </div>

        {/* Module inférieur : Capteur d'empreinte optique & Zone RFID / NFC */}
        <div className="mt-3 pt-2 border-t border-slate-800/80 flex items-center justify-around px-2">
          {/* Capteur NFC / RFID */}
          <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-950/80 border border-slate-800">
            <UserCheck className="w-3.5 h-3.5 text-amber-400" />
            <span className="text-[9px] font-mono font-bold text-slate-300">NFC / RFID</span>
          </div>

          {/* Capteur biométrique d'empreinte digitale */}
          <div className="relative w-8 h-8 rounded-full bg-slate-950 border border-emerald-500/50 flex items-center justify-center shadow-[0_0_12px_rgba(52,211,153,0.3)]">
            <div className="w-5 h-5 rounded-full border border-emerald-400/40 border-dashed animate-spin" style={{ animationDuration: '8s' }} />
            <span className="absolute w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.9)]" />
          </div>

          {/* Voyant d'état matériel */}
          <div className="flex items-center gap-1 text-[9px] font-mono text-emerald-400">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
            <span>READY</span>
          </div>
        </div>
      </div>

      {/* Pied de support de la borne */}
      <div className="relative mx-auto w-24 h-4 bg-gradient-to-b from-slate-800 to-slate-900 rounded-b-xl border-x border-b border-slate-700 shadow-lg" />
      <div className="mx-auto w-36 h-1.5 bg-slate-950/80 rounded-full blur-sm mt-0.5" />
    </div>
  );
}
