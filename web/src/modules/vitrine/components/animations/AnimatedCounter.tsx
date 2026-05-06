'use client';

import React, { useEffect, useRef, useState } from 'react';
import gsap from 'gsap';
import { useIntersectionObserver } from '../../hooks/useIntersectionObserver';

interface AnimatedCounterProps {
  value: number;
  suffix?: string;
  prefix?: string;
  duration?: number;
  decimals?: number;
  className?: string;
}

export function AnimatedCounter({
  value,
  suffix = '',
  prefix = '',
  duration = 2,
  decimals = 0,
  className = '',
}: AnimatedCounterProps) {
  const { ref, hasBeenVisible } = useIntersectionObserver({ threshold: 0.5, triggerOnce: true });
  const counterRef = useRef<HTMLSpanElement>(null);
  const [hasAnimated, setHasAnimated] = useState(false);

  useEffect(() => {
    if (!hasBeenVisible || hasAnimated || !counterRef.current) return;

    const obj = { value: 0 };

    gsap.to(obj, {
      value,
      duration,
      ease: 'power2.out',
      onUpdate: () => {
        if (counterRef.current) {
          counterRef.current.textContent = `${prefix}${obj.value.toFixed(decimals)}${suffix}`;
        }
      },
    });

    setHasAnimated(true);
  }, [hasBeenVisible, value, suffix, prefix, duration, decimals, hasAnimated]);

  return (
    <div ref={ref} className={className}>
      <span ref={counterRef}>
        {prefix}0{suffix}
      </span>
    </div>
  );
}
