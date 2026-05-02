'use client';

import { useEffect, useMemo, useState, useSyncExternalStore, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Mail, Lock, ArrowRight, Globe, Sparkles, Eye, EyeOff,
  Shield, Zap, CheckCircle2, AlertCircle, Moon, Sun
} from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import {
  applyDocumentLocale,
  getCopy,
  getPreferredLocale,
  normalizeLocale,
  storeAuthSession,
  storePreferredLocale,
  type AppLocale,
  type StoredAuthUser,
} from '@/lib/i18n';

const emptySubscribe = () => () => {};

// Particle Background Component
const ParticleField = () => {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;

    const particles: Array<{x: number; y: number; vx: number; vy: number; size: number}> = [];
    for (let i = 0; i < 30; i++) {
      particles.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        vx: (Math.random() - 0.5) * 0.5,
        vy: (Math.random() - 0.5) * 0.5,
        size: Math.random() * 2 + 1,
      });
    }

    let animationId: number;
    const animate = () => {
      ctx.fillStyle = 'rgba(0, 0, 0, 0.02)';
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      particles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
        if (p.y < 0 || p.y > canvas.height) p.vy *= -1;

        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
        ctx.fill();
      });

      animationId = requestAnimationFrame(animate);
    };
    animate();

    return () => cancelAnimationFrame(animationId);
  }, []);

  return <canvas ref={canvasRef} className="absolute inset-0 pointer-events-none" />;
};

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isDark, setIsDark] = useState(false);
  const storedLocale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const [localeOverride, setLocaleOverride] = useState<AppLocale | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [focusedInput, setFocusedInput] = useState<string | null>(null);
  const locale: AppLocale = localeOverride ?? storedLocale;
  const labels = useMemo(() => getCopy(locale), [locale]);

  useEffect(() => {
    applyDocumentLocale(locale);
  }, [locale]);

  const handleLocaleChange = (value: string) => {
    const nextLocale = normalizeLocale(value);
    setLocaleOverride(nextLocale);
    storePreferredLocale(nextLocale);
    applyDocumentLocale(nextLocale);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      const loginResponse = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          email,
          password,
          device_name: 'Web App',
        }),
      });

      const loginPayload = await loginResponse.json() as Record<string, unknown>;
      const rootToken = typeof loginPayload.token === 'string' ? loginPayload.token : null;
      const nestedData = loginPayload.data && typeof loginPayload.data === 'object'
        ? loginPayload.data as Record<string, unknown>
        : null;
      const nestedToken = nestedData && typeof nestedData.token === 'string' ? nestedData.token : null;
      const token = rootToken || nestedToken;

      if (!token) {
        throw new Error('Authentication token missing from login response.');
      }

      localStorage.setItem('auth_token', token);

      const meResponse = await apiFetch('/auth/me');
      const mePayload = await meResponse.json() as { data?: StoredAuthUser };
      const user = mePayload.data;

      if (!user) {
        throw new Error('Authenticated user missing from profile response.');
      }

      storeAuthSession(token, user);
      applyDocumentLocale(normalizeLocale(user.language), user.is_rtl);
      router.push('/dashboard');
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else if (err instanceof Error) {
        setError(err.message);
      } else {
        setError('Une erreur est survenue.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className={`min-h-screen flex transition-colors duration-500 ${isDark ? 'dark' : ''}`}>
      {/* Left Side - Visual */}
      <div className="hidden lg:flex lg:w-1/2 relative overflow-hidden">
        {/* Animated Gradient Background */}
        <div className="absolute inset-0 bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900" />
        <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmZmZmYiIGZpbGwtb3BhY2l0eT0iMC4xIj48Y2lyY2xlIGN4PSIzMCIgY3k9IjMwIiByPSIyIi8+PC9nPjwvZz48L3N2Zz4=')] opacity-50" />
        <ParticleField />

        {/* Floating Orbs */}
        <motion.div
          animate={{ y: [0, -20, 0], scale: [1, 1.1, 1] }}
          transition={{ duration: 6, repeat: Infinity }}
          className="absolute top-20 left-20 w-64 h-64 bg-emerald-400/20 rounded-full blur-3xl"
        />
        <motion.div
          animate={{ y: [0, 30, 0], scale: [1, 1.2, 1] }}
          transition={{ duration: 8, repeat: Infinity, delay: 1 }}
          className="absolute bottom-20 right-20 w-96 h-96 bg-cyan-400/20 rounded-full blur-3xl"
        />

        {/* Content */}
        <div className="relative z-10 flex flex-col justify-center px-16 text-white">
          <motion.div
            initial={{ opacity: 0, x: -50 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.8 }}
          >
            <div className="flex items-center gap-3 mb-8">
              <div className="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-xl border border-white/20 flex items-center justify-center shadow-xl">
                <span className="text-2xl font-bold">L</span>
              </div>
              <span className="text-2xl font-bold">Leopardo RH</span>
            </div>

            <h1 className="text-5xl font-bold mb-6 leading-tight">
              Bienvenue sur<br />
              <span className="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-transparent">
                l&apos;avenir des RH
              </span>
            </h1>

            <p className="text-xl text-emerald-100 mb-12 max-w-md leading-relaxed">
              La solution moderne pour gérer vos ressources humaines. Simple, rapide et puissante.
            </p>

            {/* Feature Pills */}
            <div className="flex flex-wrap gap-3">
              {[
                { icon: Shield, text: 'Sécurisé' },
                { icon: Zap, text: 'Rapide' },
                { icon: CheckCircle2, text: 'Fiable' },
              ].map((item, i) => (
                <motion.div
                  key={i}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.5 + i * 0.1 }}
                  className="flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20"
                >
                  <item.icon className="w-4 h-4" />
                  <span className="text-sm font-medium">{item.text}</span>
                </motion.div>
              ))}
            </div>
          </motion.div>
        </div>
      </div>

      {/* Right Side - Form */}
      <div className="w-full lg:w-1/2 flex items-center justify-center px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-50 to-white dark:from-slate-950 dark:to-slate-900">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.2 }}
          className="w-full max-w-md"
        >
          {/* Glass Card */}
          <div className="relative">
            {/* Glow Effect */}
            <div className="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-3xl opacity-20 blur-xl" />

            <div className="relative bg-white/80 dark:bg-slate-900/80 backdrop-blur-2xl rounded-3xl p-8 sm:p-10 border border-white/50 dark:border-slate-700/50 shadow-2xl">
              {/* Header */}
              <div className="flex items-center justify-between mb-8">
                <div className="lg:hidden flex items-center gap-2">
                  <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center">
                    <span className="text-white font-bold">L</span>
                  </div>
                </div>

                <div className="flex items-center gap-3 ml-auto">
                  <button
                    onClick={() => setIsDark(!isDark)}
                    className="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition-colors"
                  >
                    {isDark ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                  </button>

                  <div className="relative">
                    <Globe className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <select
                      value={locale}
                      onChange={(e) => handleLocaleChange(e.target.value)}
                      className="pl-9 pr-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border-0 text-sm text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-emerald-500 outline-none appearance-none cursor-pointer"
                    >
                      <option value="fr">Français</option>
                      <option value="en">English</option>
                      <option value="ar">العربية</option>
                      <option value="tr">Türkçe</option>
                    </select>
                  </div>
                </div>
              </div>

              {/* Title */}
              <div className="text-center mb-8">
                <motion.div
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  transition={{ type: "spring", stiffness: 200, delay: 0.3 }}
                  className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-emerald-100 to-cyan-100 dark:from-emerald-900/30 dark:to-cyan-900/30 text-emerald-700 dark:text-emerald-400 text-xs font-semibold mb-4"
                >
                  <Sparkles className="w-3 h-3" />
                  Espace sécurisé
                </motion.div>
                <h2 className="text-3xl font-bold text-slate-900 dark:text-white mb-2">
                  {labels.login.title}
                </h2>
                <p className="text-slate-500 dark:text-slate-400">
                  Connectez-vous pour continuer
                </p>
              </div>

              {/* Error */}
              <AnimatePresence>
                {error && (
                  <motion.div
                    initial={{ opacity: 0, y: -10, height: 0 }}
                    animate={{ opacity: 1, y: 0, height: 'auto' }}
                    exit={{ opacity: 0, y: -10, height: 0 }}
                    className="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-start gap-3"
                  >
                    <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-red-700 dark:text-red-400">{error}</span>
                  </motion.div>
                )}
              </AnimatePresence>

              {/* Form */}
              <form onSubmit={handleSubmit} className="space-y-5">
                {/* Email */}
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-slate-700 dark:text-slate-300 ml-1">
                    {labels.login.email}
                  </label>
                  <motion.div
                    animate={{
                      scale: focusedInput === 'email' ? 1.02 : 1,
                      boxShadow: focusedInput === 'email' ? '0 0 0 4px rgba(16, 185, 129, 0.2)' : '0 0 0 0px rgba(16, 185, 129, 0)'
                    }}
                    className="relative rounded-xl"
                  >
                    <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      onFocus={() => setFocusedInput('email')}
                      onBlur={() => setFocusedInput(null)}
                      placeholder="vous@entreprise.com"
                      className="w-full pl-12 pr-4 py-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-transparent focus:border-emerald-500 outline-none transition-all text-slate-900 dark:text-white placeholder:text-slate-400"
                      required
                    />
                  </motion.div>
                </div>

                {/* Password */}
                <div className="space-y-1.5">
                  <label className="text-sm font-medium text-slate-700 dark:text-slate-300 ml-1">
                    {labels.login.password}
                  </label>
                  <motion.div
                    animate={{
                      scale: focusedInput === 'password' ? 1.02 : 1,
                      boxShadow: focusedInput === 'password' ? '0 0 0 4px rgba(16, 185, 129, 0.2)' : '0 0 0 0px rgba(16, 185, 129, 0)'
                    }}
                    className="relative rounded-xl"
                  >
                    <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                    <input
                      type={showPassword ? 'text' : 'password'}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      onFocus={() => setFocusedInput('password')}
                      onBlur={() => setFocusedInput(null)}
                      placeholder="••••••••"
                      className="w-full pl-12 pr-12 py-3.5 rounded-xl bg-slate-100 dark:bg-slate-800 border-2 border-transparent focus:border-emerald-500 outline-none transition-all text-slate-900 dark:text-white placeholder:text-slate-400"
                      required
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                    >
                      {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                    </button>
                  </motion.div>
                </div>

                {/* Options */}
                <div className="flex items-center justify-between text-sm">
                  <label className="flex items-center gap-2 cursor-pointer group">
                    <div className="relative">
                      <input type="checkbox" className="peer sr-only" />
                      <div className="w-5 h-5 rounded-md border-2 border-slate-300 peer-checked:bg-emerald-500 peer-checked:border-emerald-500 transition-all" />
                      <CheckCircle2 className="absolute inset-0 w-5 h-5 text-white opacity-0 peer-checked:opacity-100 transition-opacity p-0.5" />
                    </div>
                    <span className="text-slate-600 dark:text-slate-400 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
                      {labels.login.remember}
                    </span>
                  </label>
                  <Link
                    href="#"
                    className="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 font-medium transition-colors"
                  >
                    {labels.login.forgot}
                  </Link>
                </div>

                {/* Submit */}
                <motion.button
                  type="submit"
                  disabled={submitting}
                  whileHover={{ scale: submitting ? 1 : 1.02 }}
                  whileTap={{ scale: submitting ? 1 : 0.98 }}
                  className="w-full relative overflow-hidden py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-semibold shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                >
                  <span className={`flex items-center justify-center gap-2 transition-all ${submitting ? 'opacity-0' : 'opacity-100'}`}>
                    {labels.login.submit}
                    <ArrowRight className="w-5 h-5" />
                  </span>
                  {submitting && (
                    <div className="absolute inset-0 flex items-center justify-center">
                      <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                      </svg>
                    </div>
                  )}
                </motion.button>
              </form>

              {/* Back */}
              <p className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                <Link
                  href="/"
                  className="inline-flex items-center gap-2 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                >
                  <ArrowRight className="w-4 h-4 rotate-180" />
                  {labels.login.back}
                </Link>
              </p>
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
