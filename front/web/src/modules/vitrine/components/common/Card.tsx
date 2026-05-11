'use client';

import React from 'react';
import { motion } from 'framer-motion';

export type CardVariant = 'default' | 'elevated' | 'outlined';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: CardVariant;
  hover?: boolean;
  children: React.ReactNode;
}

const variantStyles: Record<CardVariant, string> = {
  default: 'bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800',
  elevated: 'bg-white dark:bg-slate-900 rounded-2xl shadow-lg shadow-slate-200/50 dark:shadow-slate-950/50',
  outlined:
    'bg-transparent border-2 border-slate-200 dark:border-slate-700 rounded-xl hover:border-emerald-500 dark:hover:border-emerald-400 transition-colors',
};

export function Card({
  variant = 'default',
  hover = false,
  className = '',
  children,
  ...props
}: CardProps) {
  const baseStyles = 'transition-all duration-300';
  const hoverStyles = hover ? 'hover:shadow-lg hover:-translate-y-1' : '';

  const combinedClassName = `
    ${baseStyles}
    ${variantStyles[variant]}
    ${hoverStyles}
    ${className}
  `.trim();

  return (
    <motion.div
      whileHover={hover ? { y: -4 } : undefined}
      transition={{ duration: 0.3 }}
      className={combinedClassName}
      {...(props as any)}
    >
      {children}
    </motion.div>
  );
}
