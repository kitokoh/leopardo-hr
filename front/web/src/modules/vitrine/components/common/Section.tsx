'use client';

import React from 'react';
import { motion } from 'framer-motion';

export type SectionSize = 'sm' | 'md' | 'lg';

interface SectionProps extends React.HTMLAttributes<HTMLElement> {
  size?: SectionSize;
  animated?: boolean;
  children: React.ReactNode;
}

const sizeStyles: Record<SectionSize, string> = {
  sm: 'py-8 sm:py-12 md:py-16',
  md: 'py-12 sm:py-16 md:py-20 lg:py-24',
  lg: 'py-16 sm:py-20 md:py-24 lg:py-32',
};

export function Section({
  size = 'md',
  animated = true,
  className = '',
  children,
  ...props
}: SectionProps) {
  const combinedClassName = `
    ${sizeStyles[size]}
    ${className}
  `.trim();

  if (!animated) {
    return (
      <section className={combinedClassName} {...props}>
        {children}
      </section>
    );
  }

  return (
    <motion.section
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
      viewport={{ once: true, margin: '-100px' }}
      className={combinedClassName}
      {...(props as any)}
    >
      {children}
    </motion.section>
  );
}
