'use client';

import { useEffect, useRef } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

interface UseScrollAnimationOptions {
  type?: 'fadeIn' | 'slideUp' | 'slideDown' | 'slideLeft' | 'slideRight' | 'scaleIn';
  duration?: number;
  delay?: number;
  stagger?: number;
  triggerStart?: string;
  triggerEnd?: string;
  scrub?: boolean | number;
}

export function useScrollAnimation(options: UseScrollAnimationOptions = {}) {
  const {
    type = 'fadeIn',
    duration = 0.8,
    delay = 0,
    stagger = 0,
    triggerStart = 'top 80%',
    triggerEnd = 'top 20%',
    scrub = false,
  } = options;

  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!ref.current) return;

    const animationConfig = {
      fadeIn: { opacity: 0, y: 0 },
      slideUp: { opacity: 0, y: 30 },
      slideDown: { opacity: 0, y: -30 },
      slideLeft: { opacity: 0, x: 30 },
      slideRight: { opacity: 0, x: -30 },
      scaleIn: { opacity: 0, scale: 0.9 },
    };

    const fromState = animationConfig[type];

    gsap.fromTo(
      ref.current,
      fromState,
      {
        opacity: 1,
        y: 0,
        x: 0,
        scale: 1,
        duration,
        delay,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: ref.current,
          start: triggerStart,
          end: triggerEnd,
          scrub,
          markers: false,
        },
      }
    );

    return () => {
      ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
    };
  }, [type, duration, delay, stagger, triggerStart, triggerEnd, scrub]);

  return ref;
}
