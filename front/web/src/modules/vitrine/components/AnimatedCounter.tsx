'use client';

import { useEffect, useRef, useState } from 'react';
import { useInView } from 'framer-motion';

interface AnimatedCounterProps {
  value: string;
  className?: string;
  duration?: number;
}

export function AnimatedCounter({ value, className = '', duration = 1.4 }: AnimatedCounterProps) {
  const ref = useRef<HTMLSpanElement>(null);
  const isInView = useInView(ref, { once: true, margin: '-40px' });
  const [displayValue, setDisplayValue] = useState('0');

  // Parse numeric part and suffix/prefix (e.g., "1000+" -> num: 1000, suffix: "+", "21" -> num: 21, suffix: "")
  const numericMatch = value.match(/^(\D*)(\d+)(\D*)$/);

  useEffect(() => {
    if (!isInView || !numericMatch) {
      if (!numericMatch) setDisplayValue(value);
      return;
    }

    const prefix = numericMatch[1] || '';
    const targetNumber = parseInt(numericMatch[2], 10);
    const suffix = numericMatch[3] || '';

    const startTime = performance.now();
    const durationMs = duration * 1000;

    let frameId: number;

    const update = (now: number) => {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / durationMs, 1);
      // Ease out cubic
      const easedProgress = 1 - Math.pow(1 - progress, 3);
      const currentNumber = Math.round(easedProgress * targetNumber);

      setDisplayValue(`${prefix}${currentNumber}${suffix}`);

      if (progress < 1) {
        frameId = requestAnimationFrame(update);
      } else {
        setDisplayValue(value);
      }
    };

    frameId = requestAnimationFrame(update);

    return () => cancelAnimationFrame(frameId);
  }, [isInView, value, duration, numericMatch]);

  return (
    <span ref={ref} className={className}>
      {displayValue}
    </span>
  );
}
