'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { LogOut, ShieldCheck } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

export default function LogoutPage() {
  const router = useRouter();
  const [progress, setProgress] = useState(0);
  const locale = getPreferredLocale() as AppLocale;
  const labels = getCopy(locale);

  useEffect(() => {
    const performLogout = async () => {
      // Start progress
      setTimeout(() => setProgress(100), 100);

      try {
        await apiFetch('/auth/logout', { method: 'POST' });
      } catch (err) {
        console.error('Logout error:', err);
      } finally {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
      }

      // Redirect after animation
      setTimeout(() => {
        router.replace('/auth/login');
      }, 2500);
    };

    void performLogout();
  }, [router]);

  return (
    <main className="min-h-screen flex items-center justify-center bg-slate-950 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans text-slate-200">
      {/* Animated Background */}
      <div className="absolute inset-0 z-0">
        <div className="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-emerald-600/10 rounded-full blur-[120px]"></div>
        <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-cyan-500/10 rounded-full blur-[120px]"></div>
      </div>

      <div className="max-w-md w-full space-y-8 relative z-10 text-center">
        <div className="mx-auto h-24 w-24 flex items-center justify-center rounded-3xl bg-white/5 backdrop-blur-2xl border border-white/20 shadow-xl overflow-hidden group">
          <LogOut className="h-10 w-10 text-emerald-400 relative z-10" />
        </div>

        <div className="bg-slate-900/40 backdrop-blur-3xl p-10 rounded-[2rem] border border-white/10 shadow-2xl">
          <h2 className="text-3xl font-black tracking-tight text-white uppercase italic">
            Déconnexion <span className="text-emerald-500 not-italic font-black">en cours</span>
          </h2>
          <p className="mt-4 text-slate-400 font-bold tracking-[0.1em] text-sm leading-relaxed">
            Merci de votre visite sur Leopardo RH.<br />
            Nous sécurisons votre session...
          </p>

          <div className="mt-10 relative">
            <div className="overflow-hidden h-1.5 mb-4 text-xs flex rounded-full bg-white/5">
              <div
                className="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-emerald-500 transition-all duration-[2500ms] ease-linear"
                style={{ width: `${progress}%` }}
              ></div>
            </div>
            <div className="flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-500">
              <ShieldCheck className="h-3 w-3" />
              <span className="animate-pulse">Sécurisation des données terminée</span>
            </div>
          </div>
        </div>

        <p className="text-[10px] font-black uppercase tracking-widest text-slate-600">
          © 2026 Leopardo Systems • Sécurité Approuvée
        </p>
      </div>
    </main>
  );
}
