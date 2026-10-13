'use client';

/**
 * #8447 — « Mon Logiciel » : constellation 3D des apps du tenant.
 *
 * Lecture produit : le NOYAU Leopardo (la plateforme) au centre, les apps
 * réellement installables par le tenant en ORBITE autour, reliées par des
 * liens fins — le « tout-en-un » rendu visible dans l'espace tenant. Le clic
 * sur une app la sélectionne (remontée au parent qui surligne la carte
 * correspondante et l'amène à l'écran).
 *
 * Robustesse — mêmes règles que `HeroBusinessOS3D` (#8390/#8404/#8405) :
 *   - imports three.js nommés, monté uniquement via dynamic import ssr:false
 *     par la page (WebGL vérifié ici : échec → rendu silencieusement nul) ;
 *   - un seul `requestAnimationFrame`, aucune allocation three.js en boucle
 *     (vecteurs/matrices pré-alloués, seules les positions des étiquettes
 *     HTML sont écrites par image) ;
 *   - rendu mis en pause hors écran (IntersectionObserver) et onglet caché ;
 *   - `prefers-reduced-motion` neutralise la rotation continue et la
 *     respiration du noyau ;
 *   - nettoyage intégral au démontage (renderer, géométries, écouteurs).
 */

import { useEffect, useRef } from 'react';
import {
  ACESFilmicToneMapping,
  AdditiveBlending,
  AmbientLight,
  BufferGeometry,
  CanvasTexture,
  Color,
  DirectionalLight,
  Float32BufferAttribute,
  Group,
  IcosahedronGeometry,
  LineBasicMaterial,
  LineSegments,
  Mesh,
  MeshBasicMaterial,
  MeshStandardMaterial,
  PerspectiveCamera,
  PointLight,
  Raycaster,
  Scene,
  SphereGeometry,
  Sprite,
  SpriteMaterial,
  SRGBColorSpace,
  Vector2,
  Vector3,
  WebGLRenderer,
} from 'three';

export type ConstellationNode = {
  slug: string;
  label: string;
  /** Couleur d'accent de l'app (hex) — reprise du dégradé de sa carte. */
  color: string;
};

type Props = {
  nodes: ConstellationNode[];
  selectedSlug?: string | null;
  onSelect?: (slug: string) => void;
  className?: string;
};

const CORE_COLOR = '#10b981';
const LINK_COLOR = '#34d399';

/** Sprite texturé d'un disque dégradé (créé UNE fois par nœud). */
function makeDiscTexture(color: string): CanvasTexture {
  const size = 128;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  if (ctx) {
    const gradient = ctx.createRadialGradient(size / 2, size / 2, 4, size / 2, size / 2, size / 2);
    gradient.addColorStop(0, '#ffffff');
    gradient.addColorStop(0.25, color);
    gradient.addColorStop(1, 'rgba(2, 6, 23, 0)');
    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
    ctx.fill();
  }
  const texture = new CanvasTexture(canvas);
  texture.colorSpace = SRGBColorSpace;
  return texture;
}

export default function SoftwareConstellation3D({ nodes, selectedSlug, onSelect, className }: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const labelsRef = useRef<HTMLDivElement | null>(null);
  const onSelectRef = useRef(onSelect);
  onSelectRef.current = onSelect;
  const selectedRef = useRef(selectedSlug);
  selectedRef.current = selectedSlug;
  // Clé stable de composition : la scène n'est reconstruite que si l'ensemble
  // des apps change, jamais sur une simple sélection.
  const nodesKey = nodes.map((node) => node.slug).join('|');
  const nodesRef = useRef(nodes);
  nodesRef.current = nodes;

  useEffect(() => {
    const container = containerRef.current;
    const labelLayer = labelsRef.current;
    if (!container || !labelLayer || nodesRef.current.length === 0) {
      return;
    }

    // WebGL indisponible (navigateur verrouillé, GPU bloqué) : la page reste
    // pleinement utilisable sans la scène — on sort sans rien afficher.
    let renderer: WebGLRenderer;
    try {
      renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    } catch {
      return;
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const currentNodes = nodesRef.current;

    renderer.setClearColor(0x000000, 0);
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);

    const scene = new Scene();
    const camera = new PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(0, 2.2, 11);

    scene.add(new AmbientLight(0xffffff, 0.55));
    const keyLight = new DirectionalLight(0xffffff, 1.1);
    keyLight.position.set(4, 6, 6);
    scene.add(keyLight);
    const coreLight = new PointLight(new Color(CORE_COLOR), 18, 14);
    coreLight.position.set(0, 0, 0);
    scene.add(coreLight);

    const world = new Group();
    scene.add(world);

    // ── Noyau Leopardo (facettes, respiration douce) ───────────────────────
    const coreGeometry = new IcosahedronGeometry(1.05, 1);
    const coreMaterial = new MeshStandardMaterial({
      color: new Color(CORE_COLOR),
      flatShading: true,
      metalness: 0.25,
      roughness: 0.35,
      emissive: new Color(CORE_COLOR),
      emissiveIntensity: 0.35,
    });
    const core = new Mesh(coreGeometry, coreMaterial);
    world.add(core);

    // Halo additif derrière le noyau.
    const haloTexture = makeDiscTexture(CORE_COLOR);
    const halo = new Sprite(new SpriteMaterial({
      map: haloTexture,
      blending: AdditiveBlending,
      depthWrite: false,
      opacity: 0.55,
    }));
    halo.scale.set(6.5, 6.5, 1);
    world.add(halo);

    // ── Nœuds apps en orbite + liens vers le noyau ─────────────────────────
    const radius = 4.1;
    const nodeGeometry = new SphereGeometry(0.34, 24, 24);
    const raycastTargets: Mesh[] = [];
    const nodeState: Array<{
      mesh: Mesh;
      material: MeshStandardMaterial;
      glow: Sprite;
      baseAngle: number;
      inclination: number;
      labelEl: HTMLDivElement;
      slug: string;
    }> = [];

    currentNodes.forEach((node, i) => {
      const color = new Color(node.color);
      const material = new MeshStandardMaterial({
        color,
        metalness: 0.2,
        roughness: 0.4,
        emissive: color,
        emissiveIntensity: 0.25,
      });
      const mesh = new Mesh(nodeGeometry, material);
      mesh.userData.slug = node.slug;

      const glowTexture = makeDiscTexture(node.color);
      const glow = new Sprite(new SpriteMaterial({
        map: glowTexture,
        blending: AdditiveBlending,
        depthWrite: false,
        opacity: 0.35,
      }));
      glow.scale.set(1.6, 1.6, 1);
      mesh.add(glow);

      // Répartition régulière sur l'anneau, légère alternance de plan.
      const baseAngle = (i / currentNodes.length) * Math.PI * 2;
      const inclination = (i % 2 === 0 ? 1 : -1) * 0.35;

      const labelEl = document.createElement('div');
      labelEl.textContent = node.label;
      labelEl.style.cssText = [
        'position:absolute', 'top:0', 'left:0', 'transform:translate(-50%,-130%)',
        'pointer-events:none', 'white-space:nowrap', 'font-size:11px',
        'font-weight:600', 'letter-spacing:0.02em', 'color:#0f172a',
        'background:rgba(255,255,255,0.85)', 'border:1px solid rgba(15,23,42,0.08)',
        'border-radius:9999px', 'padding:2px 8px', 'backdrop-filter:blur(4px)',
        'box-shadow:0 1px 3px rgba(15,23,42,0.12)', 'will-change:transform',
      ].join(';');
      labelLayer.appendChild(labelEl);

      world.add(mesh);
      raycastTargets.push(mesh);
      nodeState.push({ mesh, material, glow, baseAngle, inclination, labelEl, slug: node.slug });
    });

    // Liens noyau ↔ nœuds (positions mises à jour une fois par image).
    const linkPositions = new Float32BufferAttribute(new Float32Array(nodeState.length * 6), 3);
    const linkGeometry = new BufferGeometry();
    linkGeometry.setAttribute('position', linkPositions);
    const links = new LineSegments(linkGeometry, new LineBasicMaterial({
      color: new Color(LINK_COLOR),
      transparent: true,
      opacity: 0.28,
    }));
    world.add(links);

    // ── Interaction (raycast survol/clic) ──────────────────────────────────
    const raycaster = new Raycaster();
    const pointer = new Vector2(-10, -10);
    let hoveredSlug: string | null = null;

    const updatePointer = (event: PointerEvent) => {
      const rect = renderer.domElement.getBoundingClientRect();
      pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
      pointer.y = -(((event.clientY - rect.top) / rect.height) * 2 - 1);
    };
    const handleMove = (event: PointerEvent) => {
      updatePointer(event);
    };
    const handleClick = (event: PointerEvent) => {
      updatePointer(event);
      raycaster.setFromCamera(pointer, camera);
      const hit = raycaster.intersectObjects(raycastTargets, false)[0];
      const slug = (hit?.object.userData.slug as string | undefined) ?? null;
      if (slug) {
        onSelectRef.current?.(slug);
      }
    };
    renderer.domElement.addEventListener('pointermove', handleMove);
    renderer.domElement.addEventListener('pointerdown', handleClick);

    // ── Dimensionnement ────────────────────────────────────────────────────
    const resize = () => {
      const width = container.clientWidth;
      const height = container.clientHeight;
      if (width === 0 || height === 0) return;
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
    };
    resize();
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(container);

    // ── Boucle de rendu (pause hors écran / onglet caché) ─────────────────
    let visible = true;
    const intersection = new IntersectionObserver((entries) => {
      visible = entries[0]?.isIntersecting ?? true;
    });
    intersection.observe(container);

    const scratch = new Vector3();
    let rafId = 0;
    const startedAt = performance.now();

    const frame = (now: number) => {
      rafId = requestAnimationFrame(frame);
      if (!visible || document.hidden) {
        return;
      }
      const elapsed = (now - startedAt) / 1000;
      const spin = reducedMotion ? 0 : elapsed * 0.12;

      world.rotation.y = spin;
      if (!reducedMotion) {
        const breathe = 1 + Math.sin(elapsed * 1.4) * 0.03;
        core.scale.setScalar(breathe);
      }
      core.rotation.y = elapsed * 0.25;
      core.rotation.x = Math.sin(elapsed * 0.3) * 0.12;

      // Survol : un seul raycast par image (aucune allocation).
      raycaster.setFromCamera(pointer, camera);
      const hit = raycaster.intersectObjects(raycastTargets, false)[0];
      hoveredSlug = (hit?.object.userData.slug as string | undefined) ?? null;
      renderer.domElement.style.cursor = hoveredSlug ? 'pointer' : 'default';

      nodeState.forEach((state, i) => {
        const angle = state.baseAngle + spin * (0.6 + (i % 3) * 0.1);
        const y = Math.sin(angle * 1.3 + i) * 0.55 + state.inclination;
        state.mesh.position.set(Math.cos(angle) * radius, y, Math.sin(angle) * radius);

        const active = state.slug === hoveredSlug || state.slug === selectedRef.current;
        const targetScale = active ? 1.45 : 1;
        state.mesh.scale.setScalar(state.mesh.scale.x + (targetScale - state.mesh.scale.x) * 0.18);
        state.material.emissiveIntensity = active ? 0.9 : 0.25;
        state.glow.material.opacity = active ? 0.75 : 0.35;

        // Lien noyau → nœud.
        linkPositions.setXYZ(i * 2, 0, 0, 0);
        linkPositions.setXYZ(i * 2 + 1, state.mesh.position.x, state.mesh.position.y, state.mesh.position.z);

        // Étiquette HTML projetée (seule écriture DOM par image et par nœud).
        scratch.copy(state.mesh.position).applyMatrix4(world.matrixWorld).project(camera);
        const x = (scratch.x * 0.5 + 0.5) * container.clientWidth;
        const yy = (-scratch.y * 0.5 + 0.5) * container.clientHeight;
        state.labelEl.style.transform = `translate(${Math.round(x)}px, ${Math.round(yy)}px) translate(-50%, -130%)`;
        state.labelEl.style.opacity = scratch.z < 1 ? '1' : '0';
      });
      linkPositions.needsUpdate = true;

      renderer.render(scene, camera);
    };
    rafId = requestAnimationFrame(frame);

    return () => {
      cancelAnimationFrame(rafId);
      intersection.disconnect();
      resizeObserver.disconnect();
      renderer.domElement.removeEventListener('pointermove', handleMove);
      renderer.domElement.removeEventListener('pointerdown', handleClick);
      nodeState.forEach((state) => {
        state.material.dispose();
        state.glow.material.map?.dispose();
        state.glow.material.dispose();
        state.labelEl.remove();
      });
      nodeGeometry.dispose();
      linkGeometry.dispose();
      links.material.dispose();
      coreGeometry.dispose();
      coreMaterial.dispose();
      haloTexture.dispose();
      halo.material.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    };
    // Scène reconstruite uniquement si la composition change (nodesKey).
  }, [nodesKey]);

  if (nodes.length === 0) {
    return null;
  }

  return (
    <div ref={containerRef} className={className} style={{ position: 'relative' }}>
      <div ref={labelsRef} style={{ position: 'absolute', inset: 0, overflow: 'hidden', pointerEvents: 'none' }} />
    </div>
  );
}
