'use client';

import { useEffect, useState } from 'react';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

/**
 * PWAProvider Component
 * Handles PWA installation, service worker registration, and offline support
 */
export function PWAProvider({ children }: { children: React.ReactNode }) {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [isInstalled, setIsInstalled] = useState(false);
  const [isOnline, setIsOnline] = useState(true);
  const [swRegistration, setSwRegistration] = useState<ServiceWorkerRegistration | null>(null);

  // Register service worker
  useEffect(() => {
    if (!('serviceWorker' in navigator)) {
      console.log('[PWA] Service Workers not supported');
      return;
    }

    const registerServiceWorker = async () => {
      try {
        const registration = await navigator.serviceWorker.register('/sw.js', {
          scope: '/',
        });

        console.log('[PWA] Service Worker registered:', registration);
        setSwRegistration(registration);

        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every minute

        // Listen for updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          if (newWorker) {
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // New service worker available
                console.log('[PWA] New service worker available');
                notifyUpdate();
              }
            });
          }
        });
      } catch (error) {
        console.error('[PWA] Service Worker registration failed:', error);
      }
    };

    registerServiceWorker();
  }, []);

  // Handle beforeinstallprompt event
  useEffect(() => {
    const handleBeforeInstallPrompt = (e: Event) => {
      e.preventDefault();
      setDeferredPrompt(e as BeforeInstallPromptEvent);
      console.log('[PWA] Install prompt available');
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    };
  }, []);

  // Handle app installed event
  useEffect(() => {
    const handleAppInstalled = () => {
      setIsInstalled(true);
      setDeferredPrompt(null);
      console.log('[PWA] App installed');
    };

    window.addEventListener('appinstalled', handleAppInstalled);

    return () => {
      window.removeEventListener('appinstalled', handleAppInstalled);
    };
  }, []);

  // Handle online/offline events
  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      console.log('[PWA] Back online');
      syncPendingData();
    };

    const handleOffline = () => {
      setIsOnline(false);
      console.log('[PWA] Offline');
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Prompt for installation
  const promptInstall = async () => {
    if (!deferredPrompt) {
      console.log('[PWA] Install prompt not available');
      return;
    }

    try {
      await deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      console.log('[PWA] User response:', outcome);
      setDeferredPrompt(null);
    } catch (error) {
      console.error('[PWA] Installation prompt failed:', error);
    }
  };

  // Sync pending data when back online
  const syncPendingData = async () => {
    if (!swRegistration) return;

    try {
      // Sync forms
      if ('sync' in swRegistration) {
        await swRegistration.sync.register('sync-forms');
        console.log('[PWA] Sync forms registered');
      }

      // Sync analytics
      if ('sync' in swRegistration) {
        await swRegistration.sync.register('sync-analytics');
        console.log('[PWA] Sync analytics registered');
      }
    } catch (error) {
      console.error('[PWA] Sync registration failed:', error);
    }
  };

  // Notify user of update
  const notifyUpdate = () => {
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('Leopardo', {
        body: 'Une nouvelle version est disponible. Rechargez la page.',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
      });
    }
  };

  // Expose PWA functions to window
  useEffect(() => {
    (window as any).leopardoPWA = {
      promptInstall,
      isInstalled,
      isOnline,
      deferredPrompt: !!deferredPrompt,
      swRegistration,
    };
  }, [deferredPrompt, isInstalled, isOnline, swRegistration]);

  return <>{children}</>;
}

/**
 * usePWA Hook
 * Access PWA functionality from components
 */
export function usePWA() {
  const [pwaState, setPwaState] = useState({
    isInstalled: false,
    isOnline: true,
    canInstall: false,
  });

  useEffect(() => {
    const updateState = () => {
      setPwaState({
        isInstalled: (window as any).leopardoPWA?.isInstalled || false,
        isOnline: (window as any).leopardoPWA?.isOnline ?? true,
        canInstall: (window as any).leopardoPWA?.deferredPrompt || false,
      });
    };

    updateState();

    window.addEventListener('online', updateState);
    window.addEventListener('offline', updateState);

    return () => {
      window.removeEventListener('online', updateState);
      window.removeEventListener('offline', updateState);
    };
  }, []);

  const promptInstall = async () => {
    if ((window as any).leopardoPWA?.promptInstall) {
      await (window as any).leopardoPWA.promptInstall();
    }
  };

  return {
    ...pwaState,
    promptInstall,
  };
}

export default PWAProvider;
