'use client';

import { useRef } from 'react';
import { motion, useScroll, useTransform } from 'framer-motion';
import { CheckCircle2, BarChart3, Clock, Users, Shield } from 'lucide-react';

const highlights = [
  'Tableau de bord en temps reel',
  'Rapports automatises avec IA',
  'Integration multi-device native',
  'Notifications intelligentes',
  'Compatibilite ZKTeco',
];

export function DemoSection() {
  const ref = useRef<HTMLElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start end', 'end start'] });
  const imgY = useTransform(scrollYProgress, [0, 1], [60, -60]);

  return (
    <section ref={ref} className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-br from-emerald-500/[0.03] via-transparent to-cyan-500/[0.03]" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-2 gap-16 items-center">
          {/* Left: Text */}
          <div className="gsap-reveal">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-cyan-500/[0.08] border border-cyan-500/15 text-cyan-700 dark:text-cyan-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse" />
              Interface moderne
            </div>
            <h2 className="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              Une experience{' '}
              <span className="bg-gradient-to-r from-cyan-500 to-blue-500 bg-clip-text text-transparent">
                revolutionnaire
              </span>
            </h2>
            <p className="text-lg text-slate-500 dark:text-slate-400 mb-10 leading-relaxed">
              Decouvrez une interface pensee pour la productivite. Chaque pixel est concu
              pour simplifier vos operations RH quotidiennes.
            </p>

            <div className="space-y-4">
              {highlights.map((item, i) => (
                <motion.div
                  key={i}
                  initial={{ opacity: 0, x: -20 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: i * 0.08, duration: 0.5 }}
                  className="flex items-center gap-3 group"
                >
                  <div className="w-8 h-8 rounded-xl bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500/20 transition-colors duration-300">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                  </div>
                  <span className="text-slate-700 dark:text-slate-300 font-medium">{item}</span>
                </motion.div>
              ))}
            </div>
          </div>

          {/* Right: Mock Dashboard */}
          <motion.div style={{ y: imgY }} className="gsap-reveal">
            <div className="relative">
              {/* Glow */}
              <div className="absolute -inset-4 bg-gradient-to-r from-emerald-500/20 to-cyan-500/20 rounded-3xl blur-2xl opacity-40" />

              {/* Dashboard mock */}
              <div className="relative rounded-2xl overflow-hidden shadow-2xl border border-slate-200/50 dark:border-slate-700/50 bg-white dark:bg-slate-900">
                {/* Title bar */}
                <div className="flex items-center gap-2 px-4 py-3 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                  <div className="flex gap-1.5">
                    <div className="w-3 h-3 rounded-full bg-red-400" />
                    <div className="w-3 h-3 rounded-full bg-amber-400" />
                    <div className="w-3 h-3 rounded-full bg-emerald-400" />
                  </div>
                  <div className="flex-1 text-center text-xs text-slate-500 font-mono">app.leopardo-rh.com/dashboard</div>
                </div>

                {/* Content */}
                <div className="p-6 space-y-4">
                  {/* Mini stat cards */}
                  <div className="grid grid-cols-3 gap-3">
                    {[
                      { icon: Users, label: 'Employes', value: '247', color: 'text-emerald-500' },
                      { icon: Clock, label: 'Presents', value: '231', color: 'text-blue-500' },
                      { icon: Shield, label: 'Securite', value: '100%', color: 'text-violet-500' },
                    ].map((stat, i) => (
                      <motion.div
                        key={i}
                        initial={{ opacity: 0, scale: 0.9 }}
                        whileInView={{ opacity: 1, scale: 1 }}
                        viewport={{ once: true }}
                        transition={{ delay: 0.3 + i * 0.1 }}
                        className="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 text-center"
                      >
                        <stat.icon className={`w-5 h-5 mx-auto mb-1.5 ${stat.color}`} />
                        <div className="text-lg font-black text-slate-900 dark:text-white">{stat.value}</div>
                        <div className="text-[10px] text-slate-500 font-medium uppercase tracking-wider">{stat.label}</div>
                      </motion.div>
                    ))}
                  </div>

                  {/* Chart area */}
                  <div className="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 aspect-[2/1] flex items-center justify-center">
                    <BarChart3 className="w-16 h-16 text-emerald-300 dark:text-emerald-800" />
                  </div>
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
