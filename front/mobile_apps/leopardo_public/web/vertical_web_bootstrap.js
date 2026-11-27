/**
 * Enrichissement social des fiches servies par Flutter web — toutes verticales.
 *
 * ## Le problème que ce script résout — et celui qu'il ne résout PAS
 *
 * Flutter web rend l'application dans un `<canvas>` : le HTML de la page ne
 * contient jamais le nom de l'établissement. Pour un lien
 * `/restaurants/le-bistrot` partagé sur WhatsApp, la carte sociale (Open Graph)
 * et l'onglet du navigateur afficheraient donc le libellé statique générique.
 *
 * Ce script lit le chemin et remplace `document.title`, `og:title`,
 * `og:description` par un libellé dérivé du slug :
 * `/restaurants/le-bistrot` → « Le Bistrot — Commander en ligne | Leopardo ».
 *
 * **Ce qu'il ne fait pas :** ce n'est pas du rendu serveur. Les robots qui
 * n'exécutent pas JavaScript (la plupart des crawlers de réseaux sociaux)
 * verront encore les balises statiques d'`index.html`. Pour une vraie carte
 * sociale par établissement, il faut un rendu serveur du `<head>` — c'est le
 * rôle des pages Next.js (`front/web/src/app/{restaurants,boutiques,stay,billets}`),
 * déjà en place. Ce script améliore le titre côté navigateur (onglet,
 * historique, favoris) et l'aperçu des crawlers qui exécutent JS (Google, Bing).
 *
 * ## Pourquoi une table de verticales et pas un `if` par chemin
 *
 * La mission de parité (#9997, #9998, #9999) ajoute trois verticales au même
 * mécanisme. Un `if (path.startsWith('/restaurants/'))` dupliqué trois fois
 * aurait produit trois copies du même code, dont deux oubliées à la prochaine
 * évolution. La table ci-dessous est la seule chose à modifier pour ajouter une
 * verticale : le reste du script est générique.
 */
(function () {
  'use strict';

  /**
   * Verticales servies par le build Flutter web.
   *
   * - `prefix` : segment de chemin qui porte le slug (ou l'identifiant).
   * - `title` : gabarit du titre, `{name}` remplacé par le libellé humanisé.
   * - `description` : gabarit de la description.
   * - `numeric` : `true` quand le segment est un identifiant numérique
   *   (`/billets/42`) et non un slug lisible — on n'humanise alors pas.
   */
  var VERTICALS = [
    {
      prefix: '/restaurants/',
      title: '{name} — Commander en ligne | Leopardo',
      description:
        'Carte, prix et avis de {name}. Commandez à emporter ou en livraison, sans installer d’application.',
      numeric: false
    },
    {
      prefix: '/boutiques/',
      title: '{name} — Boutique en ligne | Leopardo',
      description:
        'Catalogue, prix et disponibilités de {name}. Commandez en ligne, sans installer d’application.',
      numeric: false
    },
    {
      prefix: '/stay/',
      title: '{name} — Réserver un séjour | Leopardo',
      description:
        'Chambres, disponibilités et tarifs de {name}. Réservez directement, sans installer d’application.',
      numeric: false
    },
    {
      prefix: '/billets/',
      title: 'Billet {name} — Réserver | Leopardo',
      description:
        'Détail du trajet, horaires et tarifs. Réservez votre billet en ligne, sans installer d’application.',
      numeric: true
    }
  ];

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

  function apply(vertical, segment) {
    // Un identifiant numérique n'a pas de nom lisible : on l'affiche tel quel
    // plutôt que d'inventer « 42 » → « 42 » passé par `humanize` (qui ne
    // changerait rien mais donnerait un titre trompeur « Billet 42 »).
    var name = vertical.numeric ? segment : humanize(segment);

    var title = vertical.title.replace('{name}', name);
    var description = vertical.description.replace('{name}', name);

    document.title = title;
    setMeta('meta[property="og:title"]', title);
    setMeta('meta[property="og:description"]', description);
    setMeta('meta[name="twitter:title"]', title);
    setMeta('meta[name="description"]', description);
  }

  function run() {
    for (var i = 0; i < VERTICALS.length; i++) {
      var vertical = VERTICALS[i];
      var segment = segmentAfter(vertical.prefix);
      if (segment) {
        apply(vertical, segment);
        return;
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();
