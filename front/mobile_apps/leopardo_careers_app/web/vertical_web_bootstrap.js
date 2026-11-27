/**
 * Enrichissement social des fiches de **Leopardo Emploi**.
 *
 * ## Le problème que ce script résout — et celui qu'il ne résout PAS
 *
 * Flutter web rend l'application dans un `<canvas>` : le HTML de la page ne
 * contient jamais le nom de l'établissement. Pour un lien
 * `/emploi/le-bistrot` partagé sur WhatsApp, la carte sociale (Open Graph)
 * et l'onglet du navigateur afficheraient donc le libellé statique générique.
 *
 * Ce script lit le chemin et remplace `document.title`, `og:title`,
 * `og:description` par un libellé dérivé du slug.
 *
 * **Ce qu'il ne fait pas :** ce n'est pas du rendu serveur. Les robots qui
 * n'exécutent pas JavaScript verront encore les balises statiques
 * d'`index.html`. Pour une vraie carte sociale par établissement, il faut un
 * rendu serveur du `<head>` — c'est le rôle de la vitrine Next.js
 * (`front/web/src/app/emploi`), déjà en place. Ce script améliore le titre
 * côté navigateur et l'aperçu des crawlers qui exécutent JS.
 *
 * ## Pourquoi une seule verticale ici
 *
 * L'app multi-verticales (`leopardo_public`) porte une table de quatre
 * verticales. Cette app n'en sert qu'UNE : la table d'une entrée rend le
 * contrat explicite — un chemin d'une autre verticale n'est pas reconnu, et
 * l'app ne prétend pas le servir.
 */
(function () {
  'use strict';

  var VERTICAL = {
    prefix: '/emploi/',
    title: '{name} — Emploi | Leopardo Emploi',
    description:
      'Les offres des entreprises près de chez vous, et comment postuler. {name} sur Leopardo Emploi.',
    numeric: false
  };

  /** Segment de chemin qui suit `prefix`, ou `null` si le chemin ne correspond pas. */
  function segmentAfter(prefix) {
    var path = window.location.pathname || '';
    var index = path.indexOf(prefix);
    if (index === -1) return null;

    var rest = path.slice(index + prefix.length);
    var segment = rest.split('/')[0];
    if (!segment) return null;

    try {
      segment = decodeURIComponent(segment);
    } catch (error) {
      // Segment mal encodé : on garde la valeur brute plutôt que de lever.
    }

    return segment.trim() || null;
  }

  /** `le-bistrot-chez-paul` → `Le Bistrot Chez Paul`. */
  function humanize(slug) {
    return slug
      .replace(/[-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/\b\w/g, function (letter) {
        return letter.toUpperCase();
      });
  }

  function setMeta(selector, content) {
    var element = document.querySelector(selector);
    if (!element) return;

    var previous = element.getAttribute('content') || '';
    // Un libellé de plus de 60 caractères n'est pas un slug légitime : on ne
    // remplace pas une description soignée par du bruit.
    if (content.length > 60) return;
    if (previous === content) return;

    element.setAttribute('content', content);
  }

  function run() {
    var segment = segmentAfter(VERTICAL.prefix);
    if (!segment) return;

    var name = VERTICAL.numeric ? segment : humanize(segment);
    var title = VERTICAL.title.replace('{name}', name);
    var description = VERTICAL.description.replace('{name}', name);

    document.title = title;
    setMeta('meta[property="og:title"]', title);
    setMeta('meta[property="og:description"]', description);
    setMeta('meta[name="twitter:title"]', title);
    setMeta('meta[name="description"]', description);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
