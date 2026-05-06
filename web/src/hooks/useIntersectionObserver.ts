'use client';

import { useEffect, useRef, useState, RefObject } from 'react';

interface UseIntersectionObserverOptions {
  threshold?: number | number[];
  root?: Element | null;
  rootMargin?: string;
  triggerOnce?: boolean;
}

interface UseIntersectionObserverReturn {
  ref: RefObject<HTMLDivElement | null>;
  isVisible: boolean;
  hasBeenVisible: boolean;
}

/**
 * useIntersectionObserver Hook
 * Detects when an element enters the viewport
 * Useful for lazy loading images, animations, and content
 */
export function useIntersectionObserver(
  options: UseIntersectionObserverOptions = {}
): UseIntersectionObserverReturn {
  const {
    threshold = 0.1,
    root = null,
    rootMargin = '0px',
    triggerOnce = true,
  } = options;

  const ref = useRef<HTMLDivElement>(null);
  const [isVisible, setIsVisible] = useState(false);
  const [hasBeenVisible, setHasBeenVisible] = useState(false);

  useEffect(() => {
    if (!ref.current) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          setHasBeenVisible(true);

          // Stop observing if triggerOnce is true
          if (triggerOnce) {
            observer.unobserve(entry.target);
          }
        } else if (!triggerOnce) {
          setIsVisible(false);
        }
      },
      {
        threshold,
        root,
        rootMargin,
      }
    );

    observer.observe(ref.current);

    return () => {
      if (ref.current) {
        observer.unobserve(ref.current);
      }
    };
  }, [threshold, root, rootMargin, triggerOnce]);

  return { ref, isVisible, hasBeenVisible };
}

/**
 * useIntersectionObserverCallback Hook
 * Calls a callback when an element enters the viewport
 */
export function useIntersectionObserverCallback(
  callback: (isVisible: boolean) => void,
  options: UseIntersectionObserverOptions = {}
): RefObject<HTMLDivElement | null> {
  const {
    threshold = 0.1,
    root = null,
    rootMargin = '0px',
    triggerOnce = false,
  } = options;

  const ref = useRef<HTMLDivElement>(null);
  const hasTriggered = useRef(false);

  useEffect(() => {
    if (!ref.current) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          if (!hasTriggered.current || !triggerOnce) {
            callback(true);
            hasTriggered.current = true;

            if (triggerOnce) {
              observer.unobserve(entry.target);
            }
          }
        } else if (!triggerOnce) {
          callback(false);
        }
      },
      {
        threshold,
        root,
        rootMargin,
      }
    );

    observer.observe(ref.current);

    return () => {
      if (ref.current) {
        observer.unobserve(ref.current);
      }
    };
  }, [callback, threshold, root, rootMargin, triggerOnce]);

  return ref;
}

/**
 * useIntersectionObserverMultiple Hook
 * Observes multiple elements and returns their visibility states
 */
export function useIntersectionObserverMultiple(
  options: UseIntersectionObserverOptions = {}
): {
  ref: RefObject<HTMLDivElement | null>;
  visibleElements: Set<Element>;
} {
  const {
    threshold = 0.1,
    root = null,
    rootMargin = '0px',
  } = options;

  const ref = useRef<HTMLDivElement>(null);
  const [visibleElements, setVisibleElements] = useState<Set<Element>>(new Set());

  useEffect(() => {
    if (!ref.current) return;

    const observer = new IntersectionObserver(
      (entries) => {
        setVisibleElements((prev) => {
          const newSet = new Set(prev);
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              newSet.add(entry.target);
            } else {
              newSet.delete(entry.target);
            }
          });
          return newSet;
        });
      },
      {
        threshold,
        root,
        rootMargin,
      }
    );

    // Observe all children
    const children = ref.current.querySelectorAll('[data-observe]');
    children.forEach((child) => observer.observe(child));

    return () => {
      children.forEach((child) => observer.unobserve(child));
    };
  }, [threshold, root, rootMargin]);

  return { ref, visibleElements };
}

export default useIntersectionObserver;
