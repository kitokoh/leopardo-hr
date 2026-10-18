'use client';

/**
 * HeroBusinessOS3D — scène WebGL du héro : la « constellation Business OS ».
 *
 * Lecture produit : un NOYAU (la plateforme — identité, tenant, audit) autour
 * duquel ORBITENT les modules métier (paie, pointage, CRM, compta…), chacun
 * relié au noyau par un lien vivant. C'est la promesse « un seul outil pour
 * toute l'entreprise » rendue visible dès le premier écran.
 *
 * Interactions :
 *   • parallaxe souris — la constellation suit doucement le pointeur ;
 *   • survol d'un module — il s'illumine et grossit, son étiquette et son
 *     anneau se mettent en évidence (#8404) ;
 *   • clic sur un module — impulsion (flash + onde) ; avec `moduleLinks`
 *     (#8390), le clic navigue vers la page du module (packs, entrées clés).
 *     Sans liens (fond de page), aucun clic n'est intercepté.
 *
 * Affordance (#8404 — retour propriétaire : « rien n'indique au niveau des
 * losanges ») : chaque module lié porte une ÉTIQUETTE HTML (nom du pack ou de
 * l'entrée) projetée à l'écran à chaque image, et un indice « cliquez pour
 * explorer » flotte sous la scène. Les étiquettes sont des divs à
 * `pointer-events: none` : elles ne détournent jamais un clic.
 *
 * Rendu premium (#8405, renforcé #8438) : liens fins entre modules d'un même
 * anneau (effet constellation), halo lumineux derrière le noyau, lueur propre
 * à chaque module (renforcée au survol), surbrillance de l'anneau actif,
 * dérive lente de la caméra, noyau à facettes (flat shading), et — vague 3 —
 * NÉBULEUSES additives en dérive lente + poussière d'étoiles enrichie : le
 * ciel est étoilé en toutes circonstances (retour propriétaire : « en mode
 * nuit la constellation semble plus belle » — le héro est désormais une scène
 * nuit permanente, cf. `HeroSection` layout `stage`). Vague 4 (#8461) :
 * ÉTOILES FILANTES occasionnelles (pool pré-alloué) et scintillement doux de
 * la poussière ; la FRANCE en constellation vit un plan derrière, en couche
 * DOM (`FranceConstellation`, montée par `HeroSection`).
 *
 * Performance / robustesse :
 *   • three.js en imports nommés, monté uniquement par `HeroScene3D`
 *     (dynamic import, ssr:false, WebGL vérifié, idle callback) ;
 *   • rendu mis en pause hors écran (IntersectionObserver) et onglet caché ;
 *   • un seul `requestAnimationFrame`, aucune allocation three.js en boucle
 *     (vecteurs/matrices pré-alloués — les seules écritures par image sont
 *     les styles des étiquettes HTML, indispensables et bornées à 12) ;
 *   • canvas en arrière-plan (pointer-events:none) hors mode interactif,
 *     écoute sur `window` ;
 *   • `prefers-reduced-motion` neutralise rotation continue, parallaxe,
 *     dérive caméra et respiration.
 */

import { useEffect, useRef } from 'react';
import { MousePointerClick } from 'lucide-react';
import {
  ACESFilmicToneMapping,
  AdditiveBlending,
  AmbientLight,
  BufferGeometry,
  CanvasTexture,
  Color,
  DirectionalLight,
  EdgesGeometry,
  Float32BufferAttribute,
  Group,
  IcosahedronGeometry,
  LineBasicMaterial,
  LineSegments,
  Mesh,
  MeshBasicMaterial,
  MeshStandardMaterial,
  OctahedronGeometry,
  PerspectiveCamera,
  PointLight,
  Points,
  PointsMaterial,
  Raycaster,
  Scene,
  Sprite,
  SpriteMaterial,
  SRGBColorSpace,
  TorusGeometry,
  Vector2,
  Vector3,
  WebGLRenderer,
} from 'three';

export interface HeroBusinessOS3DProps {
  /** Neutralise rotation continue et parallaxe (accessibilité). */
  reducedMotion?: boolean;
  /**
   * Mission Leopardo — un module orbital cliqué (index = ordre de création) :
   * le parent reçoit cet INDEX (anneau intérieur ×5 puis anneau extérieur ×5)
   * et affiche la fiche interactive de la solution (`SolutionCard`). Le canvas
   * devient alors cliquable (pointer-events actifs + curseur pointer au
   * survol). À ne fournir QUE quand le héros porte les solutions : en fond de
   * page, un clic sur le contenu ne doit jamais être détourné par un module
   * situé « derrière ».
   */
  onSelectModule?: (index: number) => void;
  /**
   * #8404 — un libellé par module orbital (même ordre que la scène) :
   * chaque losange porte son nom à l'écran — fini les modules anonymes dont
   * on ignore qu'ils sont cliquables ni où ils mènent.
   */
  moduleLabels?: string[];
  /**
   * #8404 — indice d'interaction affiché sous la scène (mode interactif
   * uniquement), ex. « Cliquez sur un module pour explorer ».
   */
  hint?: string;
  /**
   * Estompe la scène (usage « fond de page ») : le texte par-dessus reste
   * lisible — retour propriétaire 2026-10-11 (#8389).
   */
  dimmed?: boolean;
}

// ── Composition de la constellation ──────────────────────────────────────
// #8438 — 12 modules sur 2 anneaux (6 + 6) : la constellation présente
// DAVANTAGE de solutions (les 5 packs offerts + hub en anneau intérieur, les
// entrées clés de la plateforme en anneau extérieur) — retour propriétaire
// « qu'elle présente encore plus nos solutions ». Modules légèrement plus
// petits (0.19) pour que les 12 étiquettes respirent.
const MODULES_PER_RING = [6, 6];
// Refonte vitrine #8461 — la constellation est AGRANDIE (retour propriétaire :
// « agrandir la taille globale ») : rayons +29 % / +27 %.
const RING_RADII = [3.35, 4.95];
const RING_TILT = [0.42, -0.28];
// Refonte vitrine #8461 — le mouvement orbital était quasi imperceptible
// (≈ 0.013 rad/s). Les vitesses de base sont ~2× plus élevées et,
// surtout, DÉSYNCHRONISÉES par étoile via `speedMul`/`radiusMul` (voir
// ModuleNode) : plus deux satellites ne tournent au même rythme ni à la
// même distance, ce qui évite l'effet mécanique/rouage.
const RING_SPEED = [0.00044, -0.00031];

const EMERALD = new Color('#10b981');
const CYAN = new Color('#06b6d4');
const CORE_COLOR = new Color('#0d9488');

/** Étiquette HTML d'un module — Tailwind (classes scannées dans ce fichier). */
// Vague 6 — les étiquettes passent en VERRE SOMBRE. Sur un héros nuit
// permanente, les pastilles blanches étaient les éléments les plus lumineux de
// l'écran : elles disputaient la vedette à la constellation et faisaient
// « bruit » sur le titre. Le verre sombre les rend lisibles sans les allumer.
// Vague 6 (correctif a11y) — l'étiquette est un BOUTON : elle doit donc
// recevoir le pointeur et le focus clavier. `pointer-events-none` est retiré
// et un anneau `focus-visible` rend le focus visible sur le fond nuit.
const LABEL_CLASS =
  'absolute left-0 top-0 z-10 flex items-center gap-1.5 whitespace-nowrap rounded-full border border-white/10 bg-slate-950/55 px-2 py-0.5 text-[10px] font-semibold text-slate-200/90 shadow-lg shadow-slate-950/40 backdrop-blur-md transition-opacity focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400';
const LABEL_DOT_CLASS = 'h-1.5 w-1.5 rounded-full';

type ModuleNode = {
  mesh: Mesh<OctahedronGeometry, MeshStandardMaterial>;
  glow: Sprite;
  ring: number;
  angle: number;
  /** Multiplicateur de vitesse angulaire propre au satellite (0.82–1.32). */
  speedMul: number;
  /** Multiplicateur de rayon d'orbite propre au satellite (0.9–1.18). */
  radiusMul: number;
  baseScale: number;
  hoverT: number;
  pulse: number;
  /** Index du module dans l'ordre de création (0 = 1er module d'anneau int.). */
  index: number;
  labelEl?: HTMLButtonElement;
};

/** Texture de lueur radiale (créée une fois à l'init, jamais en boucle). */
function makeGlowTexture(hex: string): CanvasTexture {
  const size = 128;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  if (ctx) {
    const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
    gradient.addColorStop(0, hex);
    gradient.addColorStop(0.35, `${hex}66`);
    gradient.addColorStop(1, `${hex}00`);
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, size, size);
  }
  const texture = new CanvasTexture(canvas);
  texture.colorSpace = SRGBColorSpace;
  return texture;
}

/** Texture de traînée d'étoile filante (créée une fois à l'init, #8461). */
function makeStreakTexture(): CanvasTexture {
  const width = 256;
  const height = 16;
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  if (ctx) {
    const gradient = ctx.createLinearGradient(0, 0, width, 0);
    gradient.addColorStop(0, 'rgba(248,250,252,0)');
    gradient.addColorStop(0.55, 'rgba(248,250,252,0.55)');
    gradient.addColorStop(1, 'rgba(248,250,252,0.95)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);
  }
  const texture = new CanvasTexture(canvas);
  texture.colorSpace = SRGBColorSpace;
  return texture;
}

/** Étoile filante (#8461) — tête + traînée, réutilisée à chaque passage. */
type ShootingStar = {
  head: Sprite;
  trail: Sprite;
  active: boolean;
  startAt: number;
  duration: number;
  nextAt: number;
  fromX: number;
  fromY: number;
  fromZ: number;
  toX: number;
  toY: number;
};

export function HeroBusinessOS3D({
  reducedMotion = false,
  onSelectModule,
  moduleLabels,
  hint,
  dimmed = false,
}: HeroBusinessOS3DProps) {
  const hostRef = useRef<HTMLDivElement>(null);
  const reducedRef = useRef(reducedMotion);
  reducedRef.current = reducedMotion;
  // Le callback/libellés sont figés au montage (la scène ne se reconstruit
  // pas) — passage par ref pour garder l'effet en deps [] sans warning
  // exhaustive-deps.
  const onSelectRef = useRef(onSelectModule);
  onSelectRef.current = onSelectModule;
  const labelsRef = useRef(moduleLabels);
  const interactive = Boolean(onSelectModule);
  const interactiveRef = useRef(interactive);

  useEffect(() => {
    const host = hostRef.current;
    if (!host) return;

    // ── Rendu ──────────────────────────────────────────────────────────
    const renderer = new WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.outputColorSpace = SRGBColorSpace;
    renderer.domElement.style.position = 'absolute';
    renderer.domElement.style.inset = '0';
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    host.appendChild(renderer.domElement);

    const scene = new Scene();
    // Passe Fondateur (#8704) — champ rétréci (42° → 39°) : la constellation
    // gagne ~8 % de présence à cadre égal — retour propriétaire : elle doit
    // DOMINER la première page, pas survivre au fond.
    const camera = new PerspectiveCamera(39, 1, 0.1, 60);
    camera.position.set(0, 0.4, 10.8);
    camera.lookAt(0, 0, 0);

    scene.add(new AmbientLight(0xffffff, 0.75));
    const keyLight = new DirectionalLight(0xffffff, 1.1);
    keyLight.position.set(4, 6, 8);
    scene.add(keyLight);
    const glowLight = new PointLight(EMERALD, 26, 15);
    glowLight.position.set(0, 0, 0);
    scene.add(glowLight);

    const world = new Group();
    // #8437 — en layout `stage`, la phrase occupe le tiers haut du héro : la
    // constellation descend d'un demi-monde pour que le noyau (la grosse
    // gemme) ne croise jamais le texte — seuls les petits modules en orbite
    // le frôlent, ce qui fait tout le charme de la composition.
    // Passe Fondateur (#8704) — la scène vit désormais DANS LE FLUX, entre
    // le titre et le CTA : plus aucune raison de la descendre (le −0.95 de la
    // vague 6 datait de l'époque où elle était la toile de fond absolue de
    // tout le héros — elle finissait cachée sous le CTA, mesuré en
    // navigateur). Elle se centre dans son cadre pour le DOMINER.
    world.position.y = -0.15;
    scene.add(world);

    // ── Noyau : la plateforme (gemme à facettes + halo) ────────────────
    const coreGeo = new IcosahedronGeometry(0.92, 1);
    const core = new Mesh(
      coreGeo,
      new MeshStandardMaterial({
        color: CORE_COLOR,
        emissive: new Color('#064e3b'),
        emissiveIntensity: 1.15,
        metalness: 0.35,
        roughness: 0.32,
        transparent: true,
        opacity: 0.85,
        flatShading: true,
      }),
    );
    world.add(core);
    const coreEdges = new LineSegments(
      new EdgesGeometry(coreGeo),
      new LineBasicMaterial({ color: EMERALD, transparent: true, opacity: 0.35 }),
    );
    core.add(coreEdges);

    // Halo lumineux derrière le noyau (#8405) — sprite additif, zéro coût
    // de géométrie.
    const coreHaloTexture = makeGlowTexture('#10b981');
    const coreHalo = new Sprite(
      new SpriteMaterial({
        map: coreHaloTexture,
        transparent: true,
        opacity: 0.62,
        depthWrite: false,
        blending: AdditiveBlending,
      }),
    );
    coreHalo.scale.setScalar(7.4);
    coreHalo.position.set(0, 0, -0.4);
    world.add(coreHalo);

    // Anneaux orbitaux (repères visuels des trajectoires).
    const ringMeshes: Mesh<TorusGeometry, MeshBasicMaterial>[] = [];
    RING_RADII.forEach((radius, ringIndex) => {
      const ring = new Mesh(
        new TorusGeometry(radius, 0.008, 8, 96),
        new MeshBasicMaterial({
          color: ringIndex === 0 ? EMERALD : CYAN,
          transparent: true,
          opacity: 0.16,
        }),
      );
      ring.rotation.x = Math.PI / 2 + RING_TILT[ringIndex];
      world.add(ring);
      ringMeshes.push(ring);
    });

    // ── Modules métier en orbite (avec lueur propre) ───────────────────
    const nodes: ModuleNode[] = [];
    const nodeGeo = new OctahedronGeometry(0.21, 0);
    const glowTextures = [makeGlowTexture('#10b981'), makeGlowTexture('#06b6d4')];
    RING_RADII.forEach((radius, ringIndex) => {
      const count = MODULES_PER_RING[ringIndex];
      for (let i = 0; i < count; i += 1) {
        const tint = (ringIndex + i) % 2 === 0 ? EMERALD : CYAN;
        const material = new MeshStandardMaterial({
          color: tint,
          emissive: tint,
          emissiveIntensity: 0.35,
          metalness: 0.4,
          roughness: 0.3,
          flatShading: true,
        });
        const mesh = new Mesh(nodeGeo, material);
        const glow = new Sprite(
          new SpriteMaterial({
            map: glowTextures[(ringIndex + i) % 2],
            transparent: true,
            opacity: 0.16,
            depthWrite: false,
            blending: AdditiveBlending,
          }),
        );
        glow.scale.setScalar(0.7);
        world.add(glow);
        const angle = (i / count) * Math.PI * 2 + ringIndex * 0.7;
        const nodeIndex = nodes.length;
        // Variance DÉTERMINISTE (pas de Math.random : le rendu reste stable
        // entre deux visites) — vitesses et rayons d'orbite différents par
        // satellite, pour un ballet organique plutôt qu'un rouage.
        const speedMul = 0.82 + ((nodeIndex * 37) % 11) * (0.5 / 11);
        const radiusMul = 0.9 + ((nodeIndex * 53) % 7) * (0.28 / 7);
        nodes.push({
          mesh,
          glow,
          ring: ringIndex,
          angle,
          speedMul,
          radiusMul,
          baseScale: 1,
          hoverT: 0,
          pulse: 0,
          index: nodeIndex,
        });
        world.add(mesh);
      }
    });

    // Liens module → noyau (un seul LineSegments, positions mises à jour).
    const linkPositions = new Float32Array(nodes.length * 6);
    const linkGeo = new BufferGeometry();
    linkGeo.setAttribute('position', new Float32BufferAttribute(linkPositions, 3));
    const links = new LineSegments(
      linkGeo,
      new LineBasicMaterial({ color: EMERALD, transparent: true, opacity: 0.22 }),
    );
    world.add(links);

    // #8405 — liens entre modules consécutifs d'un même anneau (polygone
    // fermé) : l'effet « constellation », pas seulement des rayons vers le
    // noyau. Un LineSegments par anneau, positions mises à jour en boucle.
    const ringLinkGeos: BufferGeometry[] = [];
    const ringLinkPositions: Float32Array[] = [];
    RING_RADII.forEach((_, ringIndex) => {
      const positions = new Float32Array(MODULES_PER_RING[ringIndex] * 6);
      const geo = new BufferGeometry();
      geo.setAttribute('position', new Float32BufferAttribute(positions, 3));
      const tint = ringIndex === 0 ? EMERALD : CYAN;
      const segments = new LineSegments(
        geo,
        new LineBasicMaterial({ color: tint, transparent: true, opacity: 0.3 }),
      );
      world.add(segments);
      ringLinkGeos.push(geo);
      ringLinkPositions.push(positions);
    });

    // Tableau des meshes pré-calculé : le raycast de la boucle ne doit rien
    // allouer (`nodes.map` à chaque frame créerait un tableau par image).
    const nodeMeshes = nodes.map((n) => n.mesh);

    // Poussière d'étoiles pour la profondeur (deux tons, deux vitesses).
    // #8438 — densité et dispersion augmentées : la scène est plein cadre
    // (layout `stage`), le ciel étoilé doit couvrir tout l'écran.
    const DUST_COUNT = 420;
    const dustPositions = new Float32Array(DUST_COUNT * 3);
    for (let i = 0; i < DUST_COUNT; i += 1) {
      const r = 4.5 + Math.random() * 5;
      const theta = Math.random() * Math.PI * 2;
      const phi = Math.acos(2 * Math.random() - 1);
      dustPositions[i * 3] = r * Math.sin(phi) * Math.cos(theta);
      dustPositions[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta) * 0.6;
      dustPositions[i * 3 + 2] = r * Math.cos(phi) * 0.5 - 2;
    }
    const dustGeo = new BufferGeometry();
    dustGeo.setAttribute('position', new Float32BufferAttribute(dustPositions, 3));
    const dust = new Points(
      dustGeo,
      new PointsMaterial({ color: CYAN, size: 0.045, transparent: true, opacity: 0.55 }),
    );
    world.add(dust);

    const DUST2_COUNT = 260;
    const dust2Positions = new Float32Array(DUST2_COUNT * 3);
    for (let i = 0; i < DUST2_COUNT; i += 1) {
      const r = 4 + Math.random() * 5.5;
      const theta = Math.random() * Math.PI * 2;
      const phi = Math.acos(2 * Math.random() - 1);
      dust2Positions[i * 3] = r * Math.sin(phi) * Math.cos(theta);
      dust2Positions[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta) * 0.7;
      dust2Positions[i * 3 + 2] = r * Math.cos(phi) * 0.5 - 1.5;
    }
    const dust2Geo = new BufferGeometry();
    dust2Geo.setAttribute('position', new Float32BufferAttribute(dust2Positions, 3));
    const dust2 = new Points(
      dust2Geo,
      new PointsMaterial({ color: EMERALD, size: 0.055, transparent: true, opacity: 0.45 }),
    );
    world.add(dust2);

    // Refonte vitrine #8461 — TROISIÈME couche d'étoiles, lointaine, fine et
    // discrète : c'est elle qui crée la profondeur/parallaxe demandée (trois
    // plans de tailles et d'opacités différentes : 0.045 / 0.055 / 0.03).
    const DUST3_COUNT = 340;
    const dust3Positions = new Float32Array(DUST3_COUNT * 3);
    for (let i = 0; i < DUST3_COUNT; i += 1) {
      const r = 7.5 + Math.random() * 8;
      const theta = Math.random() * Math.PI * 2;
      const phi = Math.acos(2 * Math.random() - 1);
      dust3Positions[i * 3] = r * Math.sin(phi) * Math.cos(theta);
      dust3Positions[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta) * 0.8;
      dust3Positions[i * 3 + 2] = r * Math.cos(phi) * 0.45 - 3;
    }
    const dust3Geo = new BufferGeometry();
    dust3Geo.setAttribute('position', new Float32BufferAttribute(dust3Positions, 3));
    const dust3 = new Points(
      dust3Geo,
      new PointsMaterial({ color: CYAN, size: 0.03, transparent: true, opacity: 0.3 }),
    );
    world.add(dust3);

    // #8438 — nébuleuses additives en dérive lente (émeraude, cyan, indigo) :
    // la « magie » du mode nuit reproduite en permanence. Sprites plats,
    // zéro géométrie, trois textures partagées — coût négligeable.
    const NEBULA_SPECS = [
      { texture: makeGlowTexture('#10b981'), scale: 11, x: -4.6, y: 1.6, z: -4.5, opacity: 0.13, drift: 0.9 },
      { texture: makeGlowTexture('#06b6d4'), scale: 13, x: 4.8, y: -1.4, z: -5, opacity: 0.11, drift: -0.7 },
      { texture: makeGlowTexture('#6366f1'), scale: 9, x: 2.4, y: 2.6, z: -5.5, opacity: 0.1, drift: 0.6 },
    ];
    const nebulas = NEBULA_SPECS.map((spec) => {
      const sprite = new Sprite(
        new SpriteMaterial({
          map: spec.texture,
          transparent: true,
          opacity: spec.opacity,
          depthWrite: false,
          blending: AdditiveBlending,
        }),
      );
      sprite.scale.setScalar(spec.scale);
      sprite.position.set(spec.x, spec.y, spec.z);
      world.add(sprite);
      return { sprite, baseX: spec.x, baseY: spec.y, baseOpacity: spec.opacity, drift: spec.drift };
    });

    // #8461 — étoiles filantes (vague 4, « waw ») : deux traînées réutilisées
    // en boucle fermée, spawn toutes les 4 à 9 s dans le haut du ciel, zéro
    // allocation par image. Jamais en reduced-motion (le bloc `!still`).
    const shootingGlowTexture = makeGlowTexture('#f8fafc');
    const shootingStreakTexture = makeStreakTexture();
    const shootingStars: ShootingStar[] = [0, 1].map((i) => {
      const head = new Sprite(
        new SpriteMaterial({
          map: shootingGlowTexture,
          transparent: true,
          opacity: 0,
          depthWrite: false,
          blending: AdditiveBlending,
        }),
      );
      head.scale.setScalar(0.34);
      head.visible = false;
      const trail = new Sprite(
        new SpriteMaterial({
          map: shootingStreakTexture,
          transparent: true,
          opacity: 0,
          depthWrite: false,
          blending: AdditiveBlending,
        }),
      );
      trail.scale.set(1.9, 0.08, 1);
      trail.visible = false;
      world.add(head, trail);
      return {
        head,
        trail,
        active: false,
        startAt: 0,
        duration: 1,
        nextAt: 2.5 + i * 3.5 + Math.random() * 3,
        fromX: 0,
        fromY: 0,
        fromZ: 0,
        toX: 0,
        toY: 0,
      };
    });

    // ── Étiquettes des modules (#8404 — affordance) ────────────────────
    // Créées une fois (mode interactif avec libellés uniquement), déplacées
    // par projection à chaque image.
    // Vague 6 (correctif a11y) — l'étiquette est un VRAI bouton : la fiche
    // s'ouvre au clavier (Entrée/Espace) comme à la souris, et le focus revient
    // ici à la fermeture. Défaut mesuré en navigateur : la constellation
    // n'était atteignable qu'au pointeur, et le focus retombait sur <body>.
    let focusedLabel: HTMLButtonElement | null = null;
    const labelEls: HTMLButtonElement[] = [];
    if (interactiveRef.current && labelsRef.current && labelsRef.current.length > 0) {
      nodes.forEach((node, index) => {
        const text = labelsRef.current?.[index];
        if (!text) return;
        const el = document.createElement('button');
        el.type = 'button';
        el.className = LABEL_CLASS;
        el.setAttribute('aria-label', text);
        el.tabIndex = 0;
        const dot = document.createElement('span');
        dot.className = LABEL_DOT_CLASS;
        dot.style.backgroundColor = `#${(node.mesh.material.color as Color).getHexString()}`;
        const caption = document.createElement('span');
        caption.textContent = text;
        el.append(dot, caption);
        el.addEventListener('click', () => onSelectRef.current?.(index));
        el.addEventListener('focus', () => {
          focusedLabel = el;
        });
        el.addEventListener('blur', () => {
          if (focusedLabel === el) focusedLabel = null;
        });
        host.appendChild(el);
        node.labelEl = el;
        labelEls.push(el);
      });
    }

    // ── Interaction (références réutilisées — zéro allocation en boucle) ─
    const pointer = new Vector2(0, 0);
    const pointerNdc = new Vector2(-10, -10);
    const raycaster = new Raycaster();
    const labelVec = new Vector3();
    let hovered: ModuleNode | null = null;
    let targetRotX = 0;
    let targetRotY = 0;
    // Surbrillance d'anneau au survol (#8405) — easing comme les modules.
    const ringHoverT = [0, 0];
    // Taille de vue courante pour projeter les étiquettes (mise à jour au
    // resize — pas de lecture layout dans la boucle).
    let viewW = 1;
    let viewH = 1;
    // Vague 6 — anti-chevauchement : tampon réutilisé (aucune allocation par
    // image) + ordre de placement trié par ordonnée.
    const labelSlots = nodes.map(() => ({
      el: null as HTMLButtonElement | null,
      x: 0,
      y: 0,
      depth: 1,
      visible: false,
      hovered: false,
    }));
    const labelOrder: number[] = [];
    const coreVec = new Vector3();
    let coreScreenX = 0;
    let coreScreenY = 0;
    let coreClear = 0;

    const onPointerMove = (event: PointerEvent): void => {
      const rect = host.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > window.innerHeight) return;
      const nx = ((event.clientX - rect.left) / rect.width) * 2 - 1;
      const ny = -(((event.clientY - rect.top) / rect.height) * 2 - 1);
      pointerNdc.set(nx, ny);
      pointer.set(nx, ny);
      if (!reducedRef.current) {
        targetRotY = nx * 0.45;
        targetRotX = -ny * 0.22;
      }
    };

    const onPointerDown = (): void => {
      if (!hovered) return;
      hovered.pulse = 1;
      // Mission Leopardo — le clic n'ouvre plus directement une page : le
      // parent affiche la fiche interactive de la solution (l'impulsion reste
      // l'accusé de réception visuel du clic).
      if (interactiveRef.current) {
        onSelectRef.current?.(hovered.index);
      }
    };

    window.addEventListener('pointermove', onPointerMove, { passive: true });
    window.addEventListener('pointerdown', onPointerDown, { passive: true });

    // ── Dimensionnement ────────────────────────────────────────────────
    const resize = (): void => {
      const width = host.clientWidth || 1;
      const height = host.clientHeight || 1;
      viewW = width;
      viewH = height;
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      // Sur petit écran on resserre la constellation pour qu'elle reste lisible.
      // #8403 — cadrage resserré : la scène remplit son cadre — retour
      // propriétaire « qu'elle occupe tout l'espace » — tout en gardant les
      // étiquettes des modules extérieurs dans le panneau.
      // #8438 — plein cadre (layout `stage`) : l'échelle tient compte de la
      // hauteur autant que de la largeur. Retour utilisateur vague 5 : la
      // constellation doit DOMINER l'écran (« l'agrandir un peu »), on relève
      // donc le plafond (1,12 → 1,3) et le plancher (0,45 → 0,55) : les
      // écrans larges gagnent ~16 % et le mobile ne devient pas illisible.
      // Vague 6 — la constellation doit DOMINER l'écran : plafond relevé
      // (1.42 → 1.52) tout en gardant un plancher lisible sur mobile.
      const scale = Math.min(1.52, Math.min(width / 860, height / 690));
      world.scale.setScalar(Math.max(0.62, scale));
    };
    resize();
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(host);

    // ── Boucle de rendu, pausée hors écran / onglet caché ─────────────
    let raf = 0;
    let inView = true;
    let pageVisible = !document.hidden;

    // Delta-temps borné (0.05 s) : la vitesse orbitale devient indépendante du
    // taux de rafraîchissement (60/120/144 Hz) sans dériver après un onglet
    // en arrière-plan.
    let lastTickAt = performance.now() * 0.001;

    const tick = (): void => {
      raf = requestAnimationFrame(tick);
      if (!inView || !pageVisible) return;

      const still = reducedRef.current;
      const t = performance.now() * 0.001;
      const dt = Math.min(0.05, t - lastTickAt);
      lastTickAt = t;

      // Orbites des modules + liens vers le noyau + liens inter-modules.
      nodes.forEach((node, index) => {
        if (!still) {
          // `* dt * 60` restitue l'échelle per-frame historique tout en la
          // rendant indépendante du frame-rate ; `speedMul` désynchro par étoile.
          node.angle +=
            RING_SPEED[node.ring] * (node.ring === 0 ? 1.6 : 1) * node.speedMul * dt * 60;
        }
        const radius = RING_RADII[node.ring] * node.radiusMul;
        const tilt = RING_TILT[node.ring];
        const x = Math.cos(node.angle) * radius;
        const z = Math.sin(node.angle) * radius * Math.cos(tilt);
        const y = Math.sin(node.angle) * radius * Math.sin(tilt);
        node.mesh.position.set(x, y, z);
        node.glow.position.set(x, y, z);
        if (!still) node.mesh.rotation.y += 0.006;

        // Survol / impulsion : easing sans allocation.
        const wantHover = hovered === node ? 1 : 0;
        node.hoverT += (wantHover - node.hoverT) * 0.12;
        if (node.pulse > 0) node.pulse = Math.max(0, node.pulse - 0.03);
        const scale = node.baseScale + node.hoverT * 0.55 + node.pulse * 0.9;
        node.mesh.scale.setScalar(scale);
        node.mesh.material.emissiveIntensity = 0.35 + node.hoverT * 0.8 + node.pulse * 1.6;
        node.glow.scale.setScalar(0.7 + node.hoverT * 1.1 + node.pulse * 0.9);
        (node.glow.material as SpriteMaterial).opacity = 0.16 + node.hoverT * 0.5 + node.pulse * 0.34;

        linkPositions[index * 6] = 0;
        linkPositions[index * 6 + 1] = 0;
        linkPositions[index * 6 + 2] = 0;
        linkPositions[index * 6 + 3] = x;
        linkPositions[index * 6 + 4] = y;
        linkPositions[index * 6 + 5] = z;

        // Segment inter-modules : chaque node pointe vers le suivant de son
        // anneau (les nodes d'un anneau sont contigus dans le tableau).
        const ringStart = node.ring === 0 ? 0 : MODULES_PER_RING[0];
        const local = index - ringStart;
        const ringPos = ringLinkPositions[node.ring];
        ringPos[local * 6] = x;
        ringPos[local * 6 + 1] = y;
        ringPos[local * 6 + 2] = z;
        const next = nodes[ringStart + ((local + 1) % MODULES_PER_RING[node.ring])];
        ringPos[local * 6 + 3] = next.mesh.position.x;
        ringPos[local * 6 + 4] = next.mesh.position.y;
        ringPos[local * 6 + 5] = next.mesh.position.z;
      });
      linkGeo.attributes.position.needsUpdate = true;
      ringLinkGeos.forEach((geo) => {
        geo.attributes.position.needsUpdate = true;
      });

      // Parallaxe douce + respiration du noyau + dérive caméra (#8405).
      world.rotation.y += (targetRotY - world.rotation.y) * 0.045;
      world.rotation.x += (targetRotX - world.rotation.x) * 0.045;
      if (!still) {
        world.rotation.y += 0.0016;
        const breathe = 1 + Math.sin(t * 1.4) * 0.03;
        core.scale.setScalar(breathe);
        // #8418 — le noyau TOURNE lentement sur lui-même : la gemme à
        // facettes capte la lumière en mouvement, la scène vit au premier
        // regard (et non seulement au survol).
        core.rotation.y += 0.0022;
        core.rotation.x = Math.sin(t * 0.18) * 0.12;
        glowLight.intensity = 16 + Math.sin(t * 1.4) * 3;
        dust.rotation.y += 0.0004;
        dust2.rotation.y -= 0.0003;
        // Couche lointaine : rotation plus lente → parallaxe (les plans
        // proches défilent plus vite que les lointains).
        dust3.rotation.y += 0.00016;
        // #8438 — dérive lente des nébuleuses (respiration de la lueur incluse).
        nebulas.forEach((nebula, i) => {
          nebula.sprite.position.x = nebula.baseX + Math.sin(t * 0.07 * nebula.drift + i * 2.1) * 0.8;
          nebula.sprite.position.y = nebula.baseY + Math.cos(t * 0.05 * nebula.drift + i * 1.3) * 0.5;
          (nebula.sprite.material as SpriteMaterial).opacity =
            nebula.baseOpacity + Math.sin(t * 0.3 + i * 1.7) * 0.03;
        });
        // #8461 — scintillement doux de la poussière d'étoiles.
        dust.material.opacity = 0.55 + Math.sin(t * 0.7) * 0.12;
        dust2.material.opacity = 0.45 + Math.cos(t * 0.55) * 0.1;
        dust3.material.opacity = 0.3 + Math.sin(t * 0.42) * 0.07;
        // #8461 — étoiles filantes : spawn différé, traversée ~1 s, estompage
        // en sinusoïde. Sprites pré-alloués, aucune allocation ici.
        shootingStars.forEach((star) => {
          if (!star.active) {
            if (t >= star.nextAt) {
              const fromX = -5 + Math.random() * 8;
              const fromY = 1.2 + Math.random() * 3;
              const dx = 4.5 + Math.random() * 2.5;
              const dy = -(1.8 + Math.random() * 1.6);
              star.fromX = fromX;
              star.fromY = fromY;
              star.fromZ = -4 + Math.random() * 2;
              star.toX = fromX + dx;
              star.toY = fromY + dy;
              star.startAt = t;
              star.duration = 0.8 + Math.random() * 0.5;
              star.active = true;
              star.head.visible = true;
              star.trail.visible = true;
              (star.trail.material as SpriteMaterial).rotation = Math.atan2(dy, dx);
            }
            return;
          }
          const progress = (t - star.startAt) / star.duration;
          if (progress >= 1) {
            star.active = false;
            star.head.visible = false;
            star.trail.visible = false;
            star.nextAt = t + 4 + Math.random() * 6;
            return;
          }
          const x = star.fromX + (star.toX - star.fromX) * progress;
          const y = star.fromY + (star.toY - star.fromY) * progress;
          const envelope = Math.sin(Math.PI * progress);
          star.head.position.set(x, y, star.fromZ);
          star.trail.position.set(
            x - (star.toX - star.fromX) * 0.06,
            y - (star.toY - star.fromY) * 0.06,
            star.fromZ,
          );
          (star.head.material as SpriteMaterial).opacity = 0.85 * envelope;
          (star.trail.material as SpriteMaterial).opacity = 0.5 * envelope;
        });
        // Dérive lente de la caméra : la scène « vit » même sans pointeur.
        camera.position.x = Math.sin(t * 0.13) * 0.38;
        camera.position.y = 0.4 + Math.sin(t * 0.17) * 0.26;
        camera.lookAt(0, 0, 0);
      }

      // Surbrillance de l'anneau du module survolé.
      ringHoverT[0] += ((hovered?.ring === 0 ? 1 : 0) - ringHoverT[0]) * 0.1;
      ringHoverT[1] += ((hovered?.ring === 1 ? 1 : 0) - ringHoverT[1]) * 0.1;
      ringMeshes[0].material.opacity = 0.16 + ringHoverT[0] * 0.3;
      ringMeshes[1].material.opacity = 0.16 + ringHoverT[1] * 0.3;

      // Raycast survol (les modules seuls sont testés, tableau pré-calculé).
      raycaster.setFromCamera(pointerNdc, camera);
      const hits = raycaster.intersectObjects(nodeMeshes, false);
      const hit = hits.length > 0 ? hits[0].object : null;
      hovered = nodes.find((n) => n.mesh === hit) ?? null;
      // Curseur pointer au survol d'un module cliquable (canvas interactif).
      if (interactiveRef.current) {
        host.style.cursor = hovered ? 'pointer' : '';
      }

      // Étiquettes : projection écran à chaque image (#8404). La distance à
      // la caméra estompe doucement les modules à l'arrière de l'orbite ;
      // le module survolé passe en pleine lumière.
      if (labelEls.length > 0) {
        // Position écran du noyau : les étiquettes qui tomberaient dessus sont
        // masquées (elles se lisaient comme des taches sur la gemme).
        core.getWorldPosition(coreVec);
        coreVec.project(camera);
        coreScreenX = (coreVec.x * 0.5 + 0.5) * viewW;
        coreScreenY = (-coreVec.y * 0.5 + 0.5) * viewH;
        coreClear = Math.min(78, viewW * 0.075);

        let slotCount = 0;
        nodes.forEach((node) => {
          const el = node.labelEl;
          if (!el) return;
          node.mesh.getWorldPosition(labelVec);
          const distance = labelVec.distanceTo(camera.position);
          labelVec.project(camera);
          const x = (labelVec.x * 0.5 + 0.5) * viewW;
          const y = (-labelVec.y * 0.5 + 0.5) * viewH;
          const slot = labelSlots[slotCount];
          slotCount += 1;
          slot.el = el;
          slot.x = x;
          slot.y = y;
          // Le survol souris OU le focus clavier allume l'étoile : un
          // utilisateur au clavier doit voir la même mise en avant.
          slot.hovered = hovered === node || (focusedLabel !== null && node.labelEl === focusedLabel);
          slot.depth = Math.min(1, Math.max(0.45, 1.5 - distance / 12));
          // Derrière la caméra ou très loin hors cadre : rien à afficher.
          const onCore =
            Math.hypot(x - coreScreenX, y - coreScreenY) < coreClear;
          slot.visible =
            labelVec.z < 1 && x > -32 && x < viewW + 32 && y > -32 && y < viewH + 32 && !onCore;
        });

        // Anti-chevauchement : on place du haut vers le bas et on repousse vers
        // le bas toute étiquette qui empiéterait sur la précédente.
        labelOrder.length = slotCount;
        for (let i = 0; i < slotCount; i += 1) labelOrder[i] = i;
        labelOrder.sort((a, b) => labelSlots[a].y - labelSlots[b].y);
        let lastY = Number.NEGATIVE_INFINITY;
        const minGap = 19;
        for (let i = 0; i < slotCount; i += 1) {
          const slot = labelSlots[labelOrder[i]];
          if (slot.y - lastY < minGap) slot.y = lastY + minGap;
          lastY = slot.y;
        }

        for (let i = 0; i < slotCount; i += 1) {
          const slot = labelSlots[i];
          const el = slot.el;
          if (!el) continue;
          // Garde-fou de bord (#8438, vague 5) : sur mobile la sphère déborde
          // du cadre et les étiquettes sortaient tronquées (« OS » seul
          // visible à gauche). Marge ramenée à 12 % — les libellés restent
          // entiers sur les trois formats.
          const margin = viewW * 0.12;
          const clampedX = Math.min(viewW - margin, Math.max(margin, slot.x));
          const clampedY = Math.min(viewH - 26, Math.max(26, slot.y));
          el.style.transform = `translate(-50%, -135%) translate(${clampedX.toFixed(1)}px, ${clampedY.toFixed(1)}px)`;
          el.style.opacity = slot.visible ? (slot.hovered ? '1' : slot.depth.toFixed(2)) : '0';
          // Une étiquette hors cadre ne doit ni se cliquer ni se tabuler :
          // sinon des boutons invisibles polluent l'ordre de tabulation.
          el.style.visibility = slot.visible ? 'visible' : 'hidden';
          el.tabIndex = slot.visible ? 0 : -1;
        }
      }

      renderer.render(scene, camera);
    };
    raf = requestAnimationFrame(tick);

    const intersectionObserver = new IntersectionObserver(
      (entries) => {
        inView = entries[0]?.isIntersecting ?? true;
      },
      { threshold: 0.02 },
    );
    intersectionObserver.observe(host);

    const onVisibility = (): void => {
      pageVisible = !document.hidden;
    };
    document.addEventListener('visibilitychange', onVisibility);

    return () => {
      cancelAnimationFrame(raf);
      resizeObserver.disconnect();
      intersectionObserver.disconnect();
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('pointermove', onPointerMove);
      window.removeEventListener('pointerdown', onPointerDown);

      labelEls.forEach((el) => el.remove());

      coreGeo.dispose();
      coreEdges.geometry.dispose();
      (coreEdges.material as LineBasicMaterial).dispose();
      (core.material as MeshStandardMaterial).dispose();
      coreHaloTexture.dispose();
      (coreHalo.material as SpriteMaterial).dispose();
      ringMeshes.forEach((ring) => {
        ring.geometry.dispose();
        ring.material.dispose();
      });
      nodeGeo.dispose();
      nodes.forEach((node) => {
        node.mesh.material.dispose();
        (node.glow.material as SpriteMaterial).dispose();
      });
      glowTextures.forEach((texture) => texture.dispose());
      linkGeo.dispose();
      (links.material as LineBasicMaterial).dispose();
      ringLinkGeos.forEach((geo) => geo.dispose());
      dustGeo.dispose();
      (dust.material as PointsMaterial).dispose();
      dust2Geo.dispose();
      (dust2.material as PointsMaterial).dispose();
      dust3Geo.dispose();
      (dust3.material as PointsMaterial).dispose();
      nebulas.forEach((nebula) => {
        (nebula.sprite.material as SpriteMaterial).dispose();
        (nebula.sprite.material as SpriteMaterial).map?.dispose();
      });
      shootingStars.forEach((star) => {
        (star.head.material as SpriteMaterial).dispose();
        (star.trail.material as SpriteMaterial).dispose();
      });
      shootingGlowTexture.dispose();
      shootingStreakTexture.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    };
  }, []);

  return (
    <>
      <div
        ref={hostRef}
        // En mode interactif, les étiquettes-boutons sont de vrais contrôles :
        // `aria-hidden` les retirerait de l'arbre d'accessibilité tout en les
        // laissant focusables (violation). Le canvas, lui, reste décoratif.
        aria-hidden={interactive ? undefined : true}
        className={`absolute inset-0 overflow-hidden ${interactive ? '' : 'pointer-events-none'} ${dimmed ? 'opacity-[0.55]' : ''}`}
      />
      {/* #8404 — indice d'interaction : sans lui, personne ne devine que les
          losanges sont cliquables. Visuel uniquement (aria-hidden) : les
          mêmes destinations restent portées par la navbar, les CTA et le hub
          /packs pour clavier et lecteur d'écran.
          #8438 — placé en HAUT de la scène : en layout `stage` (plein cadre),
          le bas de l'écran appartient aux CTA. */}
      {interactive && hint ? (
        <div
          aria-hidden="true"
          className="pointer-events-none absolute left-1/2 top-20 z-10 flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm backdrop-blur dark:border-slate-700/60 dark:bg-slate-900/80 dark:text-slate-400"
        >
          <MousePointerClick className="h-3.5 w-3.5 text-emerald-500" />
          {hint}
        </div>
      ) : null}
    </>
  );
}

export default HeroBusinessOS3D;
