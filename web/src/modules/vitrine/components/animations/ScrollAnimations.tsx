'use client';

import React, { useEffect, useRef } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

interface ScrollAnimationsProps {
  children: React.ReactNode;
  className?: string;
  type?: 'fadeIn' | 'slideUp' | 'slideDown' | 'slideLeft' | 'slideRight' | 'scaleIn';
  stagger?: boolean;
  duration?: number;
}

export function ScrollAnimations({
  children,
  className = '',
  type = 'fadeIn',
  stagger = false,
  duration = 0.8,
}: ScrollAnimationsProps) {
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!containerRef.current) return;

    const elements = containerRef.current.querySelectorAll('[data-scroll-animate]');
    if (elements.length === 0) return;

    const animationConfig = {
      fadeIn: { opacity: 0, y: 0 },
      slideUp: { opacity: 0, y: 30 },
      slideDown: { opacity: 0, y: -30 },
      slideLeft: { opacity: 0, x: 30 },
      slideRight: { opacity: 0, x: -30 },
      scaleIn: { opacity: 0, scale: 0.9 },
    };

    const fromState = animationConfig[type];

    elements.forEach((element, index) => {
      gsap.fromTo(
        element,
        fromState,
        {
          opacity: 1,
          y: 0,
          x: 0,
          scale: 1,
          duration,
          ease: 'power2.out',
          scrollTrigger: {
            trigger: element,
            start: 'top 80%',
            end: 'top 20%',
            scrub: false,
            markers: false,
          },
          delay: stagger ? index * 0.1 : 0,
        }
      );
    });

    return () => {
      ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
    };
  }, [type, stagger, duration]);

  return (
    <div ref={containerRef} className={className}>
      {React.Children.map(children, (child) => {
        if (React.isValidElement(child)) {
          return React.cloneElement(child, {
            'data-scroll-animate': true,
          } as any);
        }
        return child;
      })}
    </div>
  );
}
