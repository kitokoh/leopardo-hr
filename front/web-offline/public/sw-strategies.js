/**
 * Leopardo Edge — stratégies de cache du Service Worker (issue #3971).
 *
 * Module pur, chargé par `public/sw.js` via `importScripts('/sw-strategies.js')`
 * ET importable par les tests Vitest (Node) : la logique de routage
 * cache-first / network-first est critique (offline-first PWA) et n'avait
 * aucune couverture.
 */

(function (global) {
  'use strict';

  var API_PREFIX = '/api/';
  var NAVIGATION_FALLBACK = '/index.html';

  /**
   * Décide si une requête relève du Network First (API Edge) ou du Cache
   * First (assets statiques).
   *
   * @param {string} urlPathname — `new URL(request.url).pathname`
   * @returns {'api'|'static'}
   */
  function classifyRequest(urlPathname) {
    if (urlPathname.startsWith(API_PREFIX)) {
      return 'api';
    }
    return 'static';
  }

  /**
   * Corps de réponse hors-ligne pour les appels API (Network First, fallback).
   */
  function offlineApiResponse() {
    return JSON.stringify({
      error: 'offline',
      message: 'Edge non joignable',
    });
  }

  /**
   * Fallback de navigation hors-ligne : l'app shell pré-cachée, sinon 503.
   *
   * @param {string} urlPathname
   * @param {string} requestMode — `event.request.mode`
   * @returns {string} chemin à servir depuis le cache
   */
  function navigationFallback(urlPathname, requestMode) {
    if (requestMode === 'navigate' || urlPathname === '/') {
      return NAVIGATION_FALLBACK;
    }
    return null;
  }

  /**
   * Une réponse est-elle cachable ? (200 non-opaque uniquement.)
   */
  function isCacheable(responseStatus, responseType) {
    return responseStatus === 200 && responseType !== 'opaque';
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
      classifyRequest: classifyRequest,
      offlineApiResponse: offlineApiResponse,
      navigationFallback: navigationFallback,
      isCacheable: isCacheable,
      API_PREFIX: API_PREFIX,
      NAVIGATION_FALLBACK: NAVIGATION_FALLBACK,
    };
  }

  global.LeopardoSwStrategies = {
    classifyRequest: classifyRequest,
    offlineApiResponse: offlineApiResponse,
    navigationFallback: navigationFallback,
    isCacheable: isCacheable,
  };
})(typeof globalThis !== 'undefined' ? globalThis : this);
