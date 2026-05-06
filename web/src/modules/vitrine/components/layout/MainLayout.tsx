'use client';

import React, { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Navbar } from '../Navbar';
import { Footer } from '../Footer';
import { useDarkMode } from '../../hooks/useDarkMode';

interface MainLayoutProps {
  children: React.ReactNode;
}

export function MainLayout({ children }: MainLayoutProps) {
  const { isDark, toggleDarkMode, isMounted } = useDarkMode();
  const [scrollToTop, setScrollToTop] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setScrollToTop(window.scrollY > 300);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const scrollToTopHandler = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  if (!isMounted) {
    return null;
  }

  return (
    <div className={isDark ? 'dark' : ''}>
      <div className="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-white transition-colors duration-300">
        <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

        <motion.main
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -20 }}
          transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
          className="pt-20"
        >
          {children}
        </motion.main>

        <Footer />

        {/* Scroll to Top Button */}
        <AnimatePresence>
          {scrollToTop && (
            <motion.button
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.8 }}
              onClick={scrollToTopHandler}
              className="fixed bottom-8 right-8 z-40 p-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-full shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-all hover:scale-110"
              aria-label="Scroll to top"
            >
              <svg
                className="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M5 10l7-7m0 0l7 7m-7-7v18"
                />
              </svg>
            </motion.button>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}
