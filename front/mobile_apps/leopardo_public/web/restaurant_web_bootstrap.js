/**
 * Enrichissement social d'une fiche restaurant servie par Flutter web.
 *
 * ## Le problème que ce script résout — et celui qu'il ne résout PAS
 *
 * Flutter web rend l'application dans un `<canvas>` : le HTML de la page ne
 * contient jamais le nom de l'établissement. Pour un lien
 * `/restaurants/le-bistrot` partagé sur WhatsApp, la carte sociale (Open Graph)
 * et l'onglet du navigateur afficheraient donc le libellé statique générique.
 *
 * Ce script lit le slug dans `location.pathname` et remplace `document.title`,
 * `og:title`, `og:description` par le nom humanisé du slug :
 * `/restaurants/le-bistrot` → « Le Bistrot — Commander en ligne | Leopardo ».
 *
 * **Ce qu'il ne fait pas :** ce n'est pas du rendu serveur. Les robots qui
 * n'exécutent pas JavaScript (la plupart des crawlers de réseaux sociaux)
 * verront encore les balises statiques d'`index.html`. Pour une vraie carte
 * sociale par établissement, il faut un rendu serveur du `<head>` — c'est le
 * rôle de `front/web/src/app/restaurants/[slug]/page.tsx`, déjà en place côté
 * Next.js. Ce script améliore le titre côté navigateur (onglet, historique,
 * favoris) et l'aperçu des crawlers qui exécutent JS (Google, Bing).
 */
(function () {
  'use strict';

  var SLUG_PREFIX = '/restaurants/';

  function currentSlug() {
    var path = window.location.pathname || '';
    var index = path.indexOf(SLUG_PREFIX);
    if (index === -1) return null;

    var rest = path.slice(index + SLUG_PREFIX.length);
    var slug = rest.split('/')[0];
    if (!slug) return null;

    try {
      slug = decodeURIComponent(slug);
    } catch (error) {
      // Slug mal encodé : on garde la valeur brute plutôt que de lever.
    }

    return slug.trim() || null;
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
    // Un nom d'établissement de plus de 60 caractères n'est pas un slug
    // légitime : on ne remplace pas une description soignée par du bruit.
    if (content.length > 60) return;
    if (previous === content) return;

    element.setAttribute('content', content);
  }

  function run() {
    var slug = currentSlug();
    if (!slug) return;

    var name = humanize(slug);
    var title = name + ' — Commander en ligne | Leopardo';
    var description =
      'Carte, prix et avis de ' +
      name +
      '. Commandez à emporter ou en livraison, sans installer d’application.';

    document.title = title;
    setMeta('meta[property="og:title"]', title);
    setMeta('meta[property="og:description"]', description);
    setMeta('meta[name="twitter:title"]', title);
    setMeta('meta[name="description"]', description);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();
