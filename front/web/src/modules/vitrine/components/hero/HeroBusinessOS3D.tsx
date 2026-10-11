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
 *   • survol d'un module — il s'illumine et grossit ;
 *   • clic sur un module — impulsion (flash + onde), sans navigation : le
 *     canvas reste un fond, le contenu du héro garde la priorité.
 *
 * Performance / robustesse (mêmes règles que `SolutionStack3D`) :
 *   • three.js en imports nommés, monté uniquement par `HeroScene3D`
 *     (dynamic import, ssr:false, WebGL vérifié, idle callback) ;
 *   • rendu mis en pause hors écran (IntersectionObserver) et onglet caché ;
 *   • un seul `requestAnimationFrame`, aucune allocation dans la boucle ;
 *   • canvas en arrière-plan (pointer-events:none), écoute sur `window` ;
 *   • `prefers-reduced-motion` neutralise rotation continue et parallaxe.
 */

import { useEffect, useRef } from 'react';
import {
  ACESFilmicToneMapping,
  AmbientLight,
  BufferGeometry,
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
  SRGBColorSpace,
  TorusGeometry,
  Vector2,
  WebGLRenderer,
} from 'three';

export interface HeroBusinessOS3DProps {
  /** Neutralise rotation continue et parallaxe (accessibilité). */
  reducedMotion?: boolean;
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

type ModuleNode = {
  mesh: Mesh<OctahedronGeometry, MeshStandardMaterial>;
  ring: number;
  angle: number;
  baseScale: number;
  hoverT: number;
  pulse: number;
};

export function HeroBusinessOS3D({ reducedMotion = false }: HeroBusinessOS3DProps) {
  const hostRef = useRef<HTMLDivElement>(null);
  const reducedRef = useRef(reducedMotion);
  reducedRef.current = reducedMotion;

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

    scene.add(new AmbientLight(0xffffff, 0.75));
    const keyLight = new DirectionalLight(0xffffff, 1.1);
    keyLight.position.set(4, 6, 8);
    scene.add(keyLight);
    const glowLight = new PointLight(EMERALD, 18, 14);
    glowLight.position.set(0, 0, 0);
    scene.add(glowLight);

    const world = new Group();
    scene.add(world);

    // ── Noyau : la plateforme ──────────────────────────────────────────
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
      }),
    );
    world.add(core);
    const coreEdges = new LineSegments(
      new EdgesGeometry(coreGeo),
      new LineBasicMaterial({ color: EMERALD, transparent: true, opacity: 0.35 }),
    );
    core.add(coreEdges);

    // Anneaux orbitaux (repères visuels des trajectoires).
    const ringMeshes: Mesh[] = [];
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

    // ── Modules métier en orbite ───────────────────────────────────────
    const nodes: ModuleNode[] = [];
    const nodeGeo = new OctahedronGeometry(0.21, 0);
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
        });
        const mesh = new Mesh(nodeGeo, material);
        const angle = (i / count) * Math.PI * 2 + ringIndex * 0.7;
        nodes.push({ mesh, ring: ringIndex, angle, baseScale: 1, hoverT: 0, pulse: 0 });
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

    // Tableau des meshes pré-calculé : le raycast de la boucle ne doit rien
    // allouer (`nodes.map` à chaque frame créerait un tableau par image).

    // Poussière d'étoiles pour la profondeur.
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

    // ── Interaction (références réutilisées — zéro allocation en boucle) ─
    const pointer = new Vector2(0, 0);
    const pointerNdc = new Vector2(-10, -10);
    const raycaster = new Raycaster();
    const nodeMeshes = nodes.map((n) => n.mesh);
    let hovered: ModuleNode | null = null;
    let targetRotX = 0;
    let targetRotY = 0;

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
      if (hovered) hovered.pulse = 1;
    };

    window.addEventListener('pointermove', onPointerMove, { passive: true });
    window.addEventListener('pointerdown', onPointerDown, { passive: true });

    // ── Dimensionnement ────────────────────────────────────────────────
    const resize = (): void => {
      const width = host.clientWidth || 1;
      const height = host.clientHeight || 1;
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      // Sur petit écran on resserre la constellation pour qu'elle reste lisible.
      const scale = Math.min(1, width / 860);
      world.scale.setScalar(Math.max(0.62, scale));
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

      // Orbites des modules + liens vers le noyau.
      nodes.forEach((node, index) => {
        if (!still) node.angle += RING_SPEED[node.ring] * (node.ring === 0 ? 1.6 : 1);
        const radius = RING_RADII[node.ring];
        const tilt = RING_TILT[node.ring];
        const x = Math.cos(node.angle) * radius;
        const z = Math.sin(node.angle) * radius * Math.cos(tilt);
        const y = Math.sin(node.angle) * radius * Math.sin(tilt);
        node.mesh.position.set(x, y, z);
        if (!still) node.mesh.rotation.y += 0.006;

        // Survol / impulsion : easing sans allocation.
        const wantHover = hovered === node ? 1 : 0;
        node.hoverT += (wantHover - node.hoverT) * 0.12;
        if (node.pulse > 0) node.pulse = Math.max(0, node.pulse - 0.03);
        const scale = node.baseScale + node.hoverT * 0.55 + node.pulse * 0.9;
        node.mesh.scale.setScalar(scale);
        node.mesh.material.emissiveIntensity = 0.35 + node.hoverT * 0.8 + node.pulse * 1.6;

        linkPositions[index * 6] = 0;
        linkPositions[index * 6 + 1] = 0;
        linkPositions[index * 6 + 2] = 0;
        linkPositions[index * 6 + 3] = x;
        linkPositions[index * 6 + 4] = y;
        linkPositions[index * 6 + 5] = z;
      });
      linkGeo.attributes.position.needsUpdate = true;

      // Parallaxe douce + respiration du noyau.
      world.rotation.y += (targetRotY - world.rotation.y) * 0.045;
      world.rotation.x += (targetRotX - world.rotation.x) * 0.045;
      if (!still) {
        world.rotation.y += 0.0016;
        const t = performance.now() * 0.001;
        const breathe = 1 + Math.sin(t * 1.4) * 0.03;
        core.scale.setScalar(breathe);
        glowLight.intensity = 16 + Math.sin(t * 1.4) * 3;
        dust.rotation.y += 0.0004;
      }

      // Raycast survol (les modules seuls sont testés, tableau pré-calculé).
      raycaster.setFromCamera(pointerNdc, camera);
      const hits = raycaster.intersectObjects(nodeMeshes, false);
      const hit = hits.length > 0 ? hits[0].object : null;
      hovered = nodes.find((n) => n.mesh === hit) ?? null;

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

      coreGeo.dispose();
      coreEdges.geometry.dispose();
      (coreEdges.material as LineBasicMaterial).dispose();
      (core.material as MeshStandardMaterial).dispose();
      ringMeshes.forEach((ring) => {
        ring.geometry.dispose();
        (ring.material as MeshBasicMaterial).dispose();
      });
      nodeGeo.dispose();
      nodes.forEach((node) => node.mesh.material.dispose());
      linkGeo.dispose();
      (links.material as LineBasicMaterial).dispose();
      dustGeo.dispose();
      (dust.material as PointsMaterial).dispose();
      renderer.dispose();
      renderer.domElement.remove();
    };
  }, []);

  return (
    <div
      ref={hostRef}
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 overflow-hidden"
    />
  );
}

export default HeroBusinessOS3D;
