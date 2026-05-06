'use client';

import React from 'react';
import { motion } from 'framer-motion';

interface GradientOrbsProps {
  className?: string;
  intensity?: 'low' | 'medium' | 'high';
}

export function GradientOrbs({ className = '', intensity = 'medium' }: GradientOrbsProps) {
  const orbCount = intensity === 'low' ? 2 : intensity === 'medium' ? 3 : 4;
  const duration = intensity === 'low' ? 20 : intensity === 'medium' ? 15 : 10;

  return (
    <div className={`absolute inset-0 overflow-hidden pointer-events-none ${className}`}>
      {Array.from({ length: orbCount }).map((_, i) => (
        <motion.div
          key={i}
          className="absolute rounded-full blur-3xl"
          style={{
            width: `${300 + i * 100}px`,
            height: `${300 + i * 100}px`,
            background: i % 2 === 0
              ? 'radial-gradient(circle, rgba(16, 185, 129, 0.3) 0%, transparent 70%)'
              : 'radial-gradient(circle, rgba(34, 211, 238, 0.2) 0%, transparent 70%)',
          }}
          animate={{
            x: [0, 100, -50, 0],
            y: [0, -100, 50, 0],
          }}
          transition={{
            duration: duration + i * 2,
            repeat: Infinity,
            ease: 'linear',
          }}
          initial={{
            x: i % 2 === 0 ? -200 : 200,
            y: i % 2 === 0 ? -200 : 200,
          }}
        />
      ))}
    </div>
  );
}
