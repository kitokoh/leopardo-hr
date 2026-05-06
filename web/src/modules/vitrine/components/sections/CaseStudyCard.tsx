'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import Image from 'next/image';

export interface CaseStudyCardProps {
  title: string;
  description: string;
  industry: string;
  metrics: Array<{
    label: string;
    value: string;
  }>;
  image?: string;
  link: string;
  index?: number;
}

export function CaseStudyCard({
  title,
  description,
  industry,
  metrics,
  image,
  link,
  index = 0,
}: CaseStudyCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.6, delay: index * 0.1, ease: [0.22, 1, 0.36, 1] }}
      whileHover={{ y: -8, transition: { duration: 0.25 } }}
      className="group relative"
    >
      {/* Glow effect */}
      <div className="absolute -inset-px rounded-3xl bg-gradient-to-r from-emerald-500 to-cyan-500 opacity-0 group-hover:opacity-10 blur-xl transition-opacity duration-500" />

      <Link href={link}>
        <div className="relative bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-3xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden transition-all duration-300 group-hover:border-emerald-200/50 dark:group-hover:border-emerald-800/50 group-hover:shadow-xl h-full flex flex-col cursor-pointer">
          {/* Image */}
          <div className="relative w-full h-48 overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
            {image ? (
              <Image
                src={image}
                alt={title}
                fill
                className="object-cover group-hover:scale-105 transition-transform duration-300"
              />
            ) : null}
            {/* Industry badge */}
            <div className="absolute top-4 right-4">
              <div className="px-3 py-1 rounded-full bg-emerald-500/90 text-white text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
                {industry}
              </div>
            </div>
          </div>

          {/* Content */}
          <div className="p-8 flex flex-col flex-1">
            <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-3 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
              {title}
            </h3>
            <p className="text-slate-500 dark:text-slate-400 text-sm leading-relaxed mb-6 flex-1">
              {description}
            </p>

            {/* Metrics */}
            <div className="grid grid-cols-2 gap-4 mb-6 pt-6 border-t border-slate-200 dark:border-slate-800">
              {metrics.map((metric, i) => (
                <div key={i}>
                  <div className="text-2xl font-black text-slate-900 dark:text-white">{metric.value}</div>
                  <div className="text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">{metric.label}</div>
                </div>
              ))}
            </div>

            {/* CTA */}
            <div className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-semibold group-hover:gap-3 transition-all">
              Lire le cas d&apos;usage
              <ArrowRight className="w-4 h-4" />
            </div>
          </div>
        </div>
      </Link>
    </motion.div>
  );
}
