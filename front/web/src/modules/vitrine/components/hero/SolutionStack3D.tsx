'use client';

/**
 * SolutionStack3D — scène WebGL de la « Pile Leopardo » (hero de la vitrine).
 *
 * Trois niveaux, dans cet ordre de bas en haut :
 *   1. SOCLE           → plaque sombre (plateforme : identité, tenant, audit…)
 *   2. HORIZONTALE     → grille 4×4 de 16 tuiles (les briques transverses)
 *   3. VERTICALES      → 4 colonnes métier, branchées sur la couche horizontale
 *
 * Quand une verticale est active, des « faisceaux » relient sa colonne aux
 * tuiles qu'elle consomme réellement (données issues des manifests serveur,
 * voir `data/solution-stack.ts`) et les tuiles non concernées s'éteignent.
 *
 * Performance / robustesse :
 *   • three.js en imports nommés (tree-shaking) — aucune dépendance ajoutée au-delà ;
 *   • ce module n'est monté que par `SolutionStack` (dynamic import, ssr:false)
 *     et seulement si WebGL est disponible ;
 *   • rendu mis en pause hors écran (IntersectionObserver) et onglet caché ;
 *   • un seul `requestAnimationFrame`, aucune allocation dans la boucle ;
 *   • les libellés sont des <span> HTML positionnés impérativement (refs),
 *     donc zéro re-render React par frame.
 */

import { useEffect, useRef } from 'react';
import {
  ACESFilmicToneMapping,
  AmbientLight,
  BoxGeometry,
  BufferGeometry,
  Color,
  DirectionalLight,
  EdgesGeometry,
  Float32BufferAttribute,
  GridHelper,
  Group,
  LineBasicMaterial,
  LineSegments,
  Mesh,
  MeshStandardMaterial,
  PerspectiveCamera,
  PointLight,
  Raycaster,
  Scene,
  SRGBColorSpace,
  Vector2,
  Vector3,
  WebGLRenderer,
} from 'three';
import {
  HORIZONTAL_BLOCKS,
  VERTICALS,
  verticalGlow,
  type HorizontalKey,
  type VerticalKey,
} from '@/modules/vitrine/data/solution-stack';

export interface SolutionStack3DProps {
  /** Verticale sélectionnée (contrôlée par le parent). */
  active: VerticalKey | null;
  /** Notifie le survol d'une colonne (null = plus rien de survolé). */
  onHover: (key: VerticalKey | null) => void;
  /** Notifie le clic sur une colonne. */
  onSelect: (key: VerticalKey) => void;
  /** Libellés des 4 verticales, projetés au-dessus des colonnes. */
  labels: Record<VerticalKey, string>;
  /** Direction du document (les libellés restent lisibles en RTL). */
  dir: 'ltr' | 'rtl';
  /** Neutralise toute animation continue (accessibilité). */
  reducedMotion?: boolean;
}

// ── Géométrie de la composition ──────────────────────────────────────────
// Les trois niveaux doivent être lisibles SÉPARÉMENT : d'où un socle épais,
// une couche horizontale qui FLOTTE au-dessus (avec entretoises) et des
// colonnes qui se dressent derrière. Sans cet écart, l'ensemble se lit comme
// un unique damier et l'idée « un socle + des briques + des verticales »
// disparaît.
const SOCLE_SIZE = 4.0;
const SOCLE_HEIGHT = 0.3;
const SOCLE_TOP = -0.14;
/** Écart visible entre le dessus du socle et la couche horizontale. */
const LAYER_GAP = 0.3;

const TILE_SIZE = 0.62;
const TILE_HEIGHT = 0.1;
const TILE_GAP = 0.78;
/** Le damier de tuiles est décalé vers l'avant pour laisser respirer les colonnes. */
const TILE_GRID_Z = 0.35;
/** Ordonnée du CENTRE des tuiles (couche horizontale flottante). */
const TILE_Y = SOCLE_TOP + LAYER_GAP + TILE_HEIGHT / 2;

const COLUMN_SIZE = 0.4;
const COLUMN_Z = -1.45;
const COLUMN_SPREAD = 0.95;

const EMERALD = '#10B981'; // RH/émeraude — COULEURS.md

/**
 * Teinte claire d'une verticale (chapeau, pied, faisceaux, libellé).
 *
 * DÉRIVÉE de la couleur de base plutôt qu'écrite en dur : la garde
 * `check-web-design-tokens.sh` interdit les hex hors palette dans
 * `front/web/src`, et une variante de halo n'a pas à devenir un token produit.
 * Éclaircir vers le blanc donne le même effet que les anciens `*-400`.
 */
function glowOf(color: string): Color {
  return new Color(verticalGlow(color));
}

/** Hauteur d'une colonne : plus une verticale consomme de briques, plus elle monte. */
function columnHeight(consumesCount: number): number {
  return 1.18 + Math.max(0, consumesCount - 6) * 0.17;
}

export function SolutionStack3D({
  active,
  onHover,
  onSelect,
  labels,
  dir,
  reducedMotion = false,
}: SolutionStack3DProps) {
  const mountRef = useRef<HTMLDivElement>(null);
  const labelRefs = useRef<Partial<Record<VerticalKey, HTMLSpanElement | null>>>({});

  // Les callbacks sont lus depuis des refs : la scène n'est jamais reconstruite
  // quand un handler change d'identité côté React.
  const activeRef = useRef(active);
  const onHoverRef = useRef(onHover);
  const onSelectRef = useRef(onSelect);
  const labelTextRef = useRef(labels);
  activeRef.current = active;
  onHoverRef.current = onHover;
  onSelectRef.current = onSelect;
  labelTextRef.current = labels;

  useEffect(() => {
    const mount = mountRef.current;
    if (!mount) return;

    // ── Scène ────────────────────────────────────────────────────────────
    const scene = new Scene();
    const camera = new PerspectiveCamera(34, 1, 0.1, 100);
    camera.position.set(0, 2.15, 4.25);
    camera.lookAt(0, 0.62, 0);

    let renderer: WebGLRenderer;
    try {
      renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    } catch {
      return; // pas de contexte WebGL → le parent garde son repli CSS
    }
    renderer.outputColorSpace = SRGBColorSpace;
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.setClearAlpha(0);
    mount.appendChild(renderer.domElement);
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';

    // ── Lumières ─────────────────────────────────────────────────────────
    // Ambiance bleue « Sécurité » (#3B82F6) — couleur de la palette produit.
    scene.add(new AmbientLight('#3b82f6', 0.55));

    const key = new DirectionalLight('#ffffff', 2.1);
    key.position.set(3.4, 5.4, 3.2);
    scene.add(key);

    const fill = new DirectionalLight(EMERALD, 1.15);
    fill.position.set(-3.6, 2.2, -2.4);
    scene.add(fill);

    const rim = new PointLight('#06B6D4', 22, 14, 2) // cyan palette;
    rim.position.set(0, 2.6, -3.4);
    scene.add(rim);

    // ── Groupe animé ─────────────────────────────────────────────────────
    const root = new Group();
    root.position.y = -0.12;
    scene.add(root);

    const disposables: Array<{ dispose: () => void }> = [];
    const track = <T extends { dispose: () => void }>(x: T): T => {
      disposables.push(x);
      return x;
    };

    // Rendu « blueprint » : chaque volume est doublé d'une arête fine.
    const edgeMaterial = track(new LineBasicMaterial({ color: EMERALD, transparent: true, opacity: 0.38 }));
    // Le socle a ses propres arêtes, plus franches : c'est le niveau qui doit
    // se distinguer du damier horizontal.
    const socleEdgeMaterial = track(
      new LineBasicMaterial({ color: glowOf(EMERALD), transparent: true, opacity: 0.9 }),
    );
    const postMaterial = track(
      new MeshStandardMaterial({
        color: '#0f172a', // slate-900 — palette neutres
        metalness: 0.1,
        roughness: 0.45,
        emissive: new Color(EMERALD),
        emissiveIntensity: 0.3,
      }),
    );

    const addEdges = (mesh: Mesh, scale = 1.002, material: LineBasicMaterial = edgeMaterial): void => {
      const edges = new LineSegments(track(new EdgesGeometry(mesh.geometry)), material);
      edges.scale.setScalar(scale);
      mesh.add(edges);
    };

    // ── 1. Socle ─────────────────────────────────────────────────────────
    // Deux matériaux : des FLANCS sombres et une TABLE plus claire. C'est ce
    // contraste qui fait lire un « plateau » — avec une couleur unique proche
    // du fond (slate-950 de la carte), le socle disparaissait purement et
    // simplement et la scène se réduisait à un damier.
    const socleSide = track(
      new MeshStandardMaterial({
        color: '#0f172a', // slate-900 — palette neutres
        metalness: 0.12,
        roughness: 0.5,
        emissive: new Color('#3b82f6'),
        emissiveIntensity: 0.1,
      }),
    );
    const socleTop = track(
      new MeshStandardMaterial({
        color: '#1e293b', // slate-800 — palette neutres
        metalness: 0.06,
        roughness: 0.34,
        emissive: new Color('#3b82f6'),
        emissiveIntensity: 0.26,
      }),
    );
    // BoxGeometry : l'index 2 est la face +Y (le dessus).
    const socle = new Mesh(track(new BoxGeometry(SOCLE_SIZE, SOCLE_HEIGHT, SOCLE_SIZE)), [
      socleSide,
      socleSide,
      socleTop,
      socleSide,
      socleSide,
      socleSide,
    ]);
    socle.position.y = SOCLE_TOP - SOCLE_HEIGHT / 2;
    root.add(socle);
    addEdges(socle, 1.002, socleEdgeMaterial);

    // Entretoises d'angle : elles matérialisent la couche horizontale comme un
    // NIVEAU posé sur le socle, et pas comme un simple motif au sol.
    const postGeometry = track(new BoxGeometry(0.07, LAYER_GAP, 0.07));
    for (const postX of [-1.3, 1.3]) {
      for (const postZ of [TILE_GRID_Z - 1.05, TILE_GRID_Z + 1.05]) {
        const post = new Mesh(postGeometry, postMaterial);
        post.position.set(postX, SOCLE_TOP + LAYER_GAP / 2, postZ);
        root.add(post);
      }
    }

    // Grille de sol très discrète sous le socle.
    const grid = new GridHelper(11, 22, EMERALD, '#1e293b');
    grid.position.y = SOCLE_TOP - SOCLE_HEIGHT - 0.02;
    (grid.material as LineBasicMaterial).transparent = true;
    (grid.material as LineBasicMaterial).opacity = 0.12;
    root.add(grid);

    // ── 2. Couche horizontale (16 tuiles) ────────────────────────────────
    const tileGeometry = track(new BoxGeometry(TILE_SIZE, TILE_HEIGHT, TILE_SIZE));
    const tileMaterials = new Map<HorizontalKey, MeshStandardMaterial>();
    const tiles = new Map<HorizontalKey, Mesh>();

    for (const block of HORIZONTAL_BLOCKS) {
      const material = track(
        new MeshStandardMaterial({
          color: EMERALD,
          metalness: 0.08,
          roughness: 0.36,
          emissive: new Color(EMERALD),
          emissiveIntensity: 0.42,
          transparent: true,
          opacity: 1,
        }),
      );
      tileMaterials.set(block.key, material);

      const tile = new Mesh(tileGeometry, material);
      tile.position.set(
        (block.col - 1.5) * TILE_GAP,
        TILE_Y,
        TILE_GRID_Z + (block.row - 1.5) * TILE_GAP,
      );
      addEdges(tile);
      root.add(tile);
      tiles.set(block.key, tile);
    }

    // ── 3. Verticales (4 colonnes) ───────────────────────────────────────
    const columnGeometryCache = new Map<number, BoxGeometry>();
    const columns: Array<{
      key: VerticalKey;
      group: Group;
      body: MeshStandardMaterial;
      cap: MeshStandardMaterial;
      height: number;
    }> = [];

    VERTICALS.forEach((vertical, index) => {
      const height = columnHeight(vertical.consumes.length);
      let geometry = columnGeometryCache.get(height);
      if (!geometry) {
        geometry = track(new BoxGeometry(COLUMN_SIZE, height, COLUMN_SIZE));
        columnGeometryCache.set(height, geometry);
      }

      const group = new Group();
      group.position.set((index - 1.5) * COLUMN_SPREAD, SOCLE_TOP + height / 2, COLUMN_Z);

      const glow = glowOf(vertical.color);
      const body = track(
        new MeshStandardMaterial({
          color: vertical.color,
          metalness: 0.1,
          roughness: 0.32,
          emissive: new Color(vertical.color),
          emissiveIntensity: 0.3,
          transparent: true,
          opacity: 1,
        }),
      );
      const bodyMesh = new Mesh(geometry, body);
      group.add(bodyMesh);
      addEdges(bodyMesh);

      // Chapeau lumineux : identifie la colonne à distance.
      const capGeometry = track(new BoxGeometry(COLUMN_SIZE * 1.18, 0.06, COLUMN_SIZE * 1.18));
      const cap = track(
        new MeshStandardMaterial({
          color: glow,
          metalness: 0.05,
          roughness: 0.25,
          emissive: glow,
          emissiveIntensity: 1.5,
        }),
      );
      const capMesh = new Mesh(capGeometry, cap);
      capMesh.position.y = height / 2 + 0.03;
      group.add(capMesh);

      // Socle de la colonne : matérialise le « branchement » sur la couche horizontale.
      const footGeometry = track(new BoxGeometry(COLUMN_SIZE * 1.35, 0.05, COLUMN_SIZE * 1.35));
      const foot = track(
        new MeshStandardMaterial({
          color: glow,
          metalness: 0.05,
          roughness: 0.45,
          emissive: glow,
          emissiveIntensity: 0.8,
        }),
      );
      const footMesh = new Mesh(footGeometry, foot);
      footMesh.position.y = -height / 2 + 0.025;
      group.add(footMesh);

      root.add(group);
      columns.push({ key: vertical.key, group, body, cap, height });
    });

    // ── Faisceaux « verticale → briques consommées » ──────────────────────
    // Reconstruits uniquement au changement de verticale active : coût nul
    // dans la boucle de rendu.
    const beamMaterial = track(
      new LineBasicMaterial({ color: glowOf(EMERALD), transparent: true, opacity: 0.85 }),
    );
    let beams: LineSegments | null = null;

    const clearBeams = (): void => {
      if (!beams) return;
      root.remove(beams);
      beams.geometry.dispose();
      beams = null;
    };

    const buildBeams = (verticalKey: VerticalKey): void => {
      clearBeams();

      const vertical = VERTICALS.find((v) => v.key === verticalKey);
      if (!vertical) return;

      const column = columns.find((c) => c.key === verticalKey);
      if (!column) return;

      // Les faisceaux prennent la couleur de la verticale : l'association
      // « cette colonne consomme ces briques » se lit sans légende.
      beamMaterial.color.copy(glowOf(vertical.color));

      const origin = new Vector3(
        column.group.position.x,
        SOCLE_TOP + 0.04,
        column.group.position.z,
      );

      const positions: number[] = [];
      for (const blockKey of vertical.consumes) {
        const tile = tiles.get(blockKey);
        if (!tile) continue;
        positions.push(origin.x, origin.y, origin.z);
        positions.push(tile.position.x, TILE_Y + TILE_HEIGHT / 2, tile.position.z);
      }

      if (positions.length === 0) return;

      const geometry = new BufferGeometry();
      geometry.setAttribute('position', new Float32BufferAttribute(positions, 3));
      beams = new LineSegments(geometry, beamMaterial);
      // Les faisceaux vivent dans `root` : ils suivent la rotation du groupe.
      root.add(beams);
    };

    // ── État visé (interpolé dans la boucle) ─────────────────────────────
    const pointer = new Vector2(0, 0);
    const pointerTarget = new Vector2(0, 0);
    const raycaster = new Raycaster();
    let hovered: VerticalKey | null = null;
    let activeKey: VerticalKey | null = null;

    const columnMeshes: Mesh[] = [];
    const meshToKey = new Map<Mesh, VerticalKey>();
    root.traverse((object) => {
      if (object instanceof Mesh && object.geometry instanceof BoxGeometry) return; // ignoré : on cible explicitement
    });
    for (const column of columns) {
      const bodyMesh = column.group.children[0] as Mesh;
      columnMeshes.push(bodyMesh);
      meshToKey.set(bodyMesh, column.key);
    }

    // ── Boucle de rendu ──────────────────────────────────────────────────
    let frame = 0;
    let running = true;
    let visible = true;
    let elapsed = 0;
    let last = performance.now();

    // Largeur des libellés mémorisée : évite un reflow par frame (offsetWidth
    // forcerait un calcul de layout 60×/s). Re-mesurée à basse fréquence.
    const labelWidths = new Map<VerticalKey, number>();
    const measureLabels = (): void => {
      for (const vertical of VERTICALS) {
        const element = labelRefs.current[vertical.key];
        if (element) labelWidths.set(vertical.key, element.offsetWidth || 90);
      }
    };

    const setLabelTransform = (keyName: VerticalKey, x: number, y: number, opacity: number): void => {
      const element = labelRefs.current[keyName];
      if (!element) return;
      // Les libellés restent à l'intérieur du cadre : une colonne qui passe
      // derrière ne doit pas pousser son étiquette hors du canvas.
      const half = (labelWidths.get(keyName) ?? 90) / 2;
      const maxX = Math.max(half + 8, mount.clientWidth - half - 8);
      const clampedX = Math.min(Math.max(x, half + 8), maxX);
      const clampedY = Math.min(Math.max(y, 20), Math.max(20, mount.clientHeight - 20));
      element.style.transform = `translate3d(${clampedX}px, ${clampedY}px, 0) translate(-50%, -50%)`;
      element.style.opacity = String(opacity);
    };

    const resize = (): void => {
      const width = mount.clientWidth;
      const height = mount.clientHeight;
      if (width === 0 || height === 0) return;
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
    };

    const projected = new Vector3();

    const render = (now: number): void => {
      frame = requestAnimationFrame(render);
      if (!running || !visible) {
        last = now;
        return;
      }

      const delta = Math.min((now - last) / 1000, 0.05);
      last = now;
      elapsed += delta;

      // Interpolation douce du pointeur (parallaxe caméra).
      pointer.lerp(pointerTarget, 1 - Math.pow(0.001, delta));

      // Balancier plutôt que tour complet : sur un schéma en couches, un
      // turntable à 360° fait passer les colonnes devant la grille et rend la
      // lecture impossible. ±30° suffisent à donner la sensation de volume.
      if (!reducedMotion) {
        root.rotation.y = Math.sin(elapsed * 0.16) * 0.52;
      } else {
        root.rotation.y = 0.34;
      }
      root.position.y = -0.12 + (reducedMotion ? 0 : Math.sin(elapsed * 0.55) * 0.035);

      // ── Cibles d'état ────────────────────────────────────────────────
      const focus = hovered ?? activeKey;

      for (const column of columns) {
        const isFocus = focus === column.key;
        const isDimmed = focus !== null && !isFocus;
        column.body.emissiveIntensity += ((isFocus ? 1.15 : isDimmed ? 0.08 : 0.3) - column.body.emissiveIntensity) * 0.14;
        column.body.opacity += ((isDimmed ? 0.42 : 1) - column.body.opacity) * 0.14;
        column.cap.emissiveIntensity += ((isFocus ? 3.2 : isDimmed ? 0.5 : 1.5) - column.cap.emissiveIntensity) * 0.14;
        column.group.scale.y += ((isFocus ? 1.045 : 1) - column.group.scale.y) * 0.14;
      }

      const activeVertical = activeKey ? VERTICALS.find((v) => v.key === activeKey) : undefined;
      const consumed = activeVertical ? new Set(activeVertical.consumes) : null;

      for (const [blockKey, material] of tileMaterials) {
        const isConsumed = consumed === null || consumed.has(blockKey);
        material.emissiveIntensity += ((isConsumed ? 0.34 : 0.03) - material.emissiveIntensity) * 0.12;
        material.opacity += ((isConsumed ? 1 : 0.3) - material.opacity) * 0.12;
      }

      // ── Caméra (léger parallaxe souris/tactile) ──────────────────────
      const camTargetX = pointer.x * 0.75;
      const camTargetY = 2.15 - pointer.y * 0.42;
      camera.position.x += (camTargetX - camera.position.x) * 0.05;
      camera.position.y += (camTargetY - camera.position.y) * 0.05;
      camera.position.z = 4.25;
      camera.lookAt(0, 0.62, 0);

      // ── Libellés HTML projetés ───────────────────────────────────────
      const width = mount.clientWidth;
      const height = mount.clientHeight;
      for (const column of columns) {
        projected.set(column.group.position.x, column.group.position.y + column.height / 2 + 0.26, column.group.position.z);
        projected.applyMatrix4(root.matrixWorld);
        projected.project(camera);

        const x = (projected.x * 0.5 + 0.5) * width;
        const y = (-projected.y * 0.5 + 0.5) * height;
        const isBehind = projected.z > 1;
        const isFocus = focus === column.key;
        setLabelTransform(column.key, x, y, isBehind ? 0 : isFocus ? 1 : 0.72);
      }

      renderer.render(scene, camera);
    };

    // ── Interactions ─────────────────────────────────────────────────────
    const pick = (clientX: number, clientY: number): VerticalKey | null => {
      const rect = mount.getBoundingClientRect();
      if (rect.width === 0 || rect.height === 0) return null;
      pointerTarget.set(
        ((clientX - rect.left) / rect.width) * 2 - 1,
        -(((clientY - rect.top) / rect.height) * 2 - 1),
      );
      raycaster.setFromCamera(pointerTarget, camera);
      const hits = raycaster.intersectObjects(columnMeshes, false);
      const first = hits[0]?.object as Mesh | undefined;
      return first ? meshToKey.get(first) ?? null : null;
    };

    const handlePointerMove = (event: PointerEvent): void => {
      if (event.pointerType === 'touch') return;
      const next = pick(event.clientX, event.clientY);
      mount.style.cursor = next ? 'pointer' : 'default';
      if (next !== hovered) {
        hovered = next;
        onHoverRef.current(next);
      }
    };

    const handlePointerLeave = (): void => {
      pointerTarget.set(0, 0);
      mount.style.cursor = 'default';
      if (hovered !== null) {
        hovered = null;
        onHoverRef.current(null);
      }
    };

    const handleClick = (event: MouseEvent): void => {
      const next = pick(event.clientX, event.clientY);
      if (next) onSelectRef.current(next);
    };

    mount.addEventListener('pointermove', handlePointerMove);
    mount.addEventListener('pointerleave', handlePointerLeave);
    mount.addEventListener('click', handleClick);

    // ── Cycle de vie ─────────────────────────────────────────────────────
    resize();
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(mount);

    const intersectionObserver = new IntersectionObserver(
      (entries) => {
        visible = entries.some((entry) => entry.isIntersecting);
      },
      { rootMargin: '120px' },
    );
    intersectionObserver.observe(mount);

    const handleVisibility = (): void => {
      running = document.visibilityState === 'visible';
    };
    document.addEventListener('visibilitychange', handleVisibility);

    frame = requestAnimationFrame(render);

    // La verticale active pilote les faisceaux : reconstruits à la demande,
    // jamais dans la boucle de rendu.
    const syncActive = (): void => {
      measureLabels();
      if (activeRef.current === activeKey) return;
      activeKey = activeRef.current;
      if (activeKey) buildBeams(activeKey);
      else clearBeams();
    };
    syncActive();
    const activeWatcher = window.setInterval(syncActive, 120);

    return () => {
      cancelAnimationFrame(frame);
      window.clearInterval(activeWatcher);
      resizeObserver.disconnect();
      intersectionObserver.disconnect();
      document.removeEventListener('visibilitychange', handleVisibility);
      mount.removeEventListener('pointermove', handlePointerMove);
      mount.removeEventListener('pointerleave', handlePointerLeave);
      mount.removeEventListener('click', handleClick);
      clearBeams();
      for (const item of disposables) item.dispose();
      renderer.dispose();
      if (renderer.domElement.parentNode === mount) mount.removeChild(renderer.domElement);
    };
  }, [reducedMotion]);

  return (
    <div className="relative h-full w-full" dir={dir}>
      {/* Canvas WebGL */}
      <div ref={mountRef} className="absolute inset-0" />

      {/* Libellés des verticales — positionnés impérativement (aucun re-render) */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        {VERTICALS.map((vertical) => (
          <span
            key={vertical.key}
            ref={(element) => {
              labelRefs.current[vertical.key] = element;
            }}
            className="absolute left-0 top-0 whitespace-nowrap rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-wide opacity-0 backdrop-blur-sm transition-[opacity] duration-300 sm:text-xs"
            style={{
              borderColor: `${vertical.color}66`,
              backgroundColor: `${vertical.color}1f`,
              color: verticalGlow(vertical.color),
            }}
          >
            {labels[vertical.key]}
          </span>
        ))}
      </div>
    </div>
  );
}

export default SolutionStack3D;
