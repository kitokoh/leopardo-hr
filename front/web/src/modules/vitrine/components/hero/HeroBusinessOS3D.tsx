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
 * Rendu premium (#8405) : liens fins entre modules d'un même anneau (effet
 * constellation), halo lumineux derrière le noyau, lueur propre à chaque
 * module (renforcée au survol), surbrillance de l'anneau actif, dérive lente
 * de la caméra et noyau à facettes (flat shading).
 *
 * Performance / robustesse (mêmes règles que `SolutionStack3D`) :
 *   • three.js en imports nommés, monté uniquement par `HeroScene3D`
 *     (dynamic import, ssr:false, WebGL vérifié, idle callback) ;
 *   • rendu mis en pause hors écran (IntersectionObserver) et onglet caché ;
 *   • un seul `requestAnimationFrame`, aucune allocation three.js en boucle
 *     (vecteurs/matrices pré-alloués — les seules écritures par image sont
 *     les styles des étiquettes HTML, indispensables et bornées à 8) ;
 *   • canvas en arrière-plan (pointer-events:none) hors mode interactif,
 *     écoute sur `window` ;
 *   • `prefers-reduced-motion` neutralise rotation continue, parallaxe,
 *     dérive caméra et respiration.
 */

import { useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
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
   * #8390 — un href par module orbital (index = ordre de création) : le clic
   * sur un module navigue vers sa page (packs en anneau intérieur, entrées
   * clés de la plateforme en anneau extérieur). Le canvas devient alors
   * cliquable (pointer-events actifs + curseur pointer au survol). À n'activer
   * QUE quand la scène n'est PAS derrière du contenu (colonne dédiée du héro
   * d'accueil) : en fond de page, un clic sur le contenu ne doit jamais être
   * détourné par un module situé « derrière ».
   */
  moduleLinks?: string[];
  /**
   * #8404 — un libellé par module orbital (même ordre que `moduleLinks`) :
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
// 8 modules sur 2 anneaux (4 intérieurs, 4 extérieurs) : assez pour lire
// « plusieurs métiers, un seul noyau », assez peu pour rester élégant
// derrière le texte du héro.
const MODULES_PER_RING = [4, 4];
const RING_RADII = [2.3, 3.5];
const RING_TILT = [0.42, -0.28];
const RING_SPEED = [0.00022, -0.00015];

const EMERALD = new Color('#10b981');
const CYAN = new Color('#06b6d4');
const CORE_COLOR = new Color('#0d9488');

/** Étiquette HTML d'un module — Tailwind (classes scannées dans ce fichier). */
const LABEL_CLASS =
  'pointer-events-none absolute left-0 top-0 z-10 flex items-center gap-1.5 whitespace-nowrap rounded-full border border-slate-200/70 bg-white/85 px-2 py-0.5 text-[10px] font-semibold text-slate-600 shadow-sm backdrop-blur-sm transition-opacity dark:border-slate-700/60 dark:bg-slate-900/85 dark:text-slate-300';
const LABEL_DOT_CLASS = 'h-1.5 w-1.5 rounded-full';

type ModuleNode = {
  mesh: Mesh<OctahedronGeometry, MeshStandardMaterial>;
  glow: Sprite;
  ring: number;
  angle: number;
  baseScale: number;
  hoverT: number;
  pulse: number;
  href?: string;
  labelEl?: HTMLDivElement;
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

export function HeroBusinessOS3D({
  reducedMotion = false,
  moduleLinks,
  moduleLabels,
  hint,
  dimmed = false,
}: HeroBusinessOS3DProps) {
  const hostRef = useRef<HTMLDivElement>(null);
  const reducedRef = useRef(reducedMotion);
  reducedRef.current = reducedMotion;
  const router = useRouter();
  const routerRef = useRef(router);
  routerRef.current = router;
  // Les liens/libellés sont figés au montage (la scène ne se reconstruit pas)
  // — passage par ref pour garder l'effet en deps [] sans warning
  // exhaustive-deps.
  const linksRef = useRef(moduleLinks);
  const labelsRef = useRef(moduleLabels);
  const interactive = Boolean(moduleLinks && moduleLinks.length > 0);
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
    const camera = new PerspectiveCamera(42, 1, 0.1, 60);
    camera.position.set(0, 0.4, 9.2);
    camera.lookAt(0, 0, 0);

    scene.add(new AmbientLight(0xffffff, 0.75));
    const keyLight = new DirectionalLight(0xffffff, 1.1);
    keyLight.position.set(4, 6, 8);
    scene.add(keyLight);
    const glowLight = new PointLight(EMERALD, 18, 14);
    glowLight.position.set(0, 0, 0);
    scene.add(glowLight);

    const world = new Group();
    scene.add(world);

    // ── Noyau : la plateforme (gemme à facettes + halo) ────────────────
    const coreGeo = new IcosahedronGeometry(1.05, 1);
    const core = new Mesh(
      coreGeo,
      new MeshStandardMaterial({
        color: CORE_COLOR,
        emissive: new Color('#064e3b'),
        emissiveIntensity: 0.55,
        metalness: 0.35,
        roughness: 0.32,
        transparent: true,
        opacity: 0.92,
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
        opacity: 0.5,
        depthWrite: false,
        blending: AdditiveBlending,
      }),
    );
    coreHalo.scale.setScalar(5.6);
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
        nodes.push({
          mesh,
          glow,
          ring: ringIndex,
          angle,
          baseScale: 1,
          hoverT: 0,
          pulse: 0,
          href: linksRef.current?.[nodeIndex],
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
    const DUST_COUNT = 140;
    const dustPositions = new Float32Array(DUST_COUNT * 3);
    for (let i = 0; i < DUST_COUNT; i += 1) {
      const r = 4.5 + Math.random() * 4;
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

    const DUST2_COUNT = 90;
    const dust2Positions = new Float32Array(DUST2_COUNT * 3);
    for (let i = 0; i < DUST2_COUNT; i += 1) {
      const r = 4 + Math.random() * 4.5;
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

    // ── Étiquettes des modules (#8404 — affordance) ────────────────────
    // Créées une fois (mode interactif avec libellés uniquement), déplacées
    // par projection à chaque image. Jamais cliquables (pointer-events:none).
    const labelEls: HTMLDivElement[] = [];
    if (interactiveRef.current && labelsRef.current && labelsRef.current.length > 0) {
      nodes.forEach((node, index) => {
        const text = labelsRef.current?.[index];
        if (!text) return;
        const el = document.createElement('div');
        el.className = LABEL_CLASS;
        const dot = document.createElement('span');
        dot.className = LABEL_DOT_CLASS;
        dot.style.backgroundColor = `#${(node.mesh.material.color as Color).getHexString()}`;
        const caption = document.createElement('span');
        caption.textContent = text;
        el.append(dot, caption);
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
      // #8390 — clic sur un module lié : navigation (locale conservée via la
      // query courante, même règle que `withLocaleHref`).
      if (hovered.href) {
        routerRef.current.push(`${hovered.href}${window.location.search}`);
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
      // #8403 — cadrage resserré (720 au lieu de 860) : la scène remplit son
      // cadre — retour propriétaire « qu'elle occupe tout l'espace » — tout en
      // gardant les étiquettes des modules extérieurs dans le panneau.
      const scale = Math.min(1, width / 720);
      world.scale.setScalar(Math.max(0.6, scale));
    };
    resize();
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(host);

    // ── Boucle de rendu, pausée hors écran / onglet caché ─────────────
    let raf = 0;
    let inView = true;
    let pageVisible = !document.hidden;

    const tick = (): void => {
      raf = requestAnimationFrame(tick);
      if (!inView || !pageVisible) return;

      const still = reducedRef.current;
      const t = performance.now() * 0.001;

      // Orbites des modules + liens vers le noyau + liens inter-modules.
      nodes.forEach((node, index) => {
        if (!still) node.angle += RING_SPEED[node.ring] * (node.ring === 0 ? 1.6 : 1);
        const radius = RING_RADII[node.ring];
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
        glowLight.intensity = 16 + Math.sin(t * 1.4) * 3;
        dust.rotation.y += 0.0004;
        dust2.rotation.y -= 0.0003;
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
        host.style.cursor = hovered?.href ? 'pointer' : '';
      }

      // Étiquettes : projection écran à chaque image (#8404). La distance à
      // la caméra estompe doucement les modules à l'arrière de l'orbite ;
      // le module survolé passe en pleine lumière.
      if (labelEls.length > 0) {
        nodes.forEach((node) => {
          const el = node.labelEl;
          if (!el) return;
          node.mesh.getWorldPosition(labelVec);
          const distance = labelVec.distanceTo(camera.position);
          labelVec.project(camera);
          const x = (labelVec.x * 0.5 + 0.5) * viewW;
          const y = (-labelVec.y * 0.5 + 0.5) * viewH;
          el.style.transform = `translate(-50%, -135%) translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)`;
          const depthFade = Math.min(1, Math.max(0.45, 1.5 - distance / 12));
          el.style.opacity = hovered === node ? '1' : depthFade.toFixed(2);
        });
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
      renderer.dispose();
      renderer.domElement.remove();
    };
  }, []);

  return (
    <>
      <div
        ref={hostRef}
        aria-hidden="true"
        className={`absolute inset-0 overflow-hidden ${moduleLinks && moduleLinks.length > 0 ? '' : 'pointer-events-none'} ${dimmed ? 'opacity-[0.55]' : ''}`}
      />
      {/* #8404 — indice d'interaction : sans lui, personne ne devine que les
          losanges sont cliquables. Visuel uniquement (aria-hidden) : les
          mêmes destinations restent portées par la navbar, les CTA et le hub
          /packs pour clavier et lecteur d'écran. */}
      {interactive && hint ? (
        <div
          aria-hidden="true"
          className="pointer-events-none absolute bottom-2 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm backdrop-blur dark:border-slate-700/60 dark:bg-slate-900/80 dark:text-slate-400"
        >
          <MousePointerClick className="h-3.5 w-3.5 text-emerald-500" />
          {hint}
        </div>
      ) : null}
    </>
  );
}

export default HeroBusinessOS3D;
