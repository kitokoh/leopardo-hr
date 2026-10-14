/**
 * FranceConstellation — la France métropolitaine dessinée « en constellation ».
 *
 * #8461 (vague 4) — retour propriétaire : « il va juste avoir la France en
 * plus de la constellation 3D… la France en arrière-plan de la constellation ».
 * La silhouette de l'Hexagone devient une SECONDE constellation, posée derrière
 * la scène WebGL : contour fin, villes-nœuds éclairées reliées par des liens —
 * le même langage visuel que la constellation Business OS, un plan plus loin.
 *
 * Décoratif purement visuel : aucun texte (rien à traduire — garde
 * PA2-I18N-014), `aria-hidden`, jamais cliquable. La dérive lente et le
 * scintillement des villes vivent dans `globals.css` (classes
 * `france-constellation-drift` / `france-city-twinkle`), neutralisés en
 * `prefers-reduced-motion`.
 *
 * Coordonnées : projection simple lon [-5.5, 10] × lat [51.2, 42.2] vers un
 * viewBox 100×100. Tracé volontairement stylisé (segments droits) : c'est le
 * langage « constellation » qui prime, pas la précision cartographique.
 */

/** Contour de l'Hexagone (sens horaire, de Dunkerque à Calais). */
const OUTLINE_POINTS =
  '50.8,1.9 58.1,8.9 62.6,13.9 67.1,15.6 74.4,18.3 77.4,23.3 88.6,24.8 ' +
  '85.8,28.9 84.5,40.1 79.7,46.7 75.1,55.6 80.0,58.9 80.6,66.7 84.0,84.7 ' +
  '78.1,86.7 75.5,88.3 70.1,87.8 64.5,86.7 60.6,84.4 54.2,94.4 47.1,95.6 ' +
  '40.0,92.8 25.2,85.6 27.7,75.6 31.6,70.6 28.7,61.1 28.1,56.1 23.9,50.0 ' +
  '21.3,42.2 13.5,37.8 4.5,31.7 7.4,28.3 16.1,26.7 22.9,28.3 23.9,21.1 ' +
  '25.2,17.0 28.4,20.0 36.1,18.9 42.6,14.4 47.5,2.8';

/** Villes-nœuds de la constellation (lueur émeraude ou cyan, alternée). */
const CITIES: ReadonlyArray<{ x: number; y: number; cyan?: boolean }> = [
  { x: 50.6, y: 26.0 }, // Paris
  { x: 55.2, y: 6.3, cyan: true }, // Lille
  { x: 85.5, y: 29.1 }, // Strasbourg
  { x: 66.6, y: 60.4, cyan: true }, // Lyon
  { x: 70.1, y: 87.8 }, // Marseille
  { x: 82.4, y: 83.3, cyan: true }, // Nice
  { x: 31.7, y: 70.7 }, // Bordeaux
  { x: 25.5, y: 44.2, cyan: true }, // Nantes
  { x: 44.8, y: 84.4, cyan: true }, // Toulouse
  { x: 24.6, y: 34.3 }, // Rennes
];

/** Maillage intérieur — indices dans `CITIES` (effet constellation). */
const LINKS: ReadonlyArray<readonly [number, number]> = [
  [0, 1], // Paris–Lille
  [0, 2], // Paris–Strasbourg
  [0, 3], // Paris–Lyon
  [0, 9], // Paris–Rennes
  [0, 7], // Paris–Nantes
  [0, 6], // Paris–Bordeaux
  [3, 4], // Lyon–Marseille
  [4, 5], // Marseille–Nice
  [4, 8], // Marseille–Toulouse
  [8, 6], // Toulouse–Bordeaux
  [6, 7], // Bordeaux–Nantes
  [7, 9], // Nantes–Rennes
];

const EMERALD = '#10b981';
const CYAN = '#06b6d4';

export function FranceConstellation({ className = '' }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 100 100"
      preserveAspectRatio="xMidYMid meet"
      aria-hidden="true"
      focusable="false"
      className={className}
      style={{ transformOrigin: 'center' }}
    >
      <defs>
        <linearGradient id="france-outline-gradient" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor={EMERALD} />
          <stop offset="100%" stopColor={CYAN} />
        </linearGradient>
      </defs>

      {/* Voile intérieur très léger : la silhouette se lit sans écraser la scène. */}
      <polygon points={OUTLINE_POINTS} fill={EMERALD} fillOpacity={0.045} stroke="none" />

      {/* Maillage constellation (villes reliées). */}
      {LINKS.map(([from, to]) => (
        <line
          key={`${from}-${to}`}
          x1={CITIES[from].x}
          y1={CITIES[from].y}
          x2={CITIES[to].x}
          y2={CITIES[to].y}
          stroke={CYAN}
          strokeWidth={0.22}
          strokeOpacity={0.5}
          strokeLinecap="round"
        />
      ))}

      {/* Contour de l'Hexagone. */}
      <polygon
        points={OUTLINE_POINTS}
        fill="none"
        stroke="url(#france-outline-gradient)"
        strokeWidth={0.45}
        strokeOpacity={0.85}
        strokeLinejoin="round"
        strokeLinecap="round"
      />

      {/* Villes-nœuds : halo + cœur, scintillement doux (CSS, reduced-motion off). */}
      {CITIES.map((city) => (
        <g key={`${city.x}-${city.y}`} className="france-city-twinkle">
          <circle
            cx={city.x}
            cy={city.y}
            r={1.7}
            fill={city.cyan ? CYAN : EMERALD}
            fillOpacity={0.22}
          />
          <circle cx={city.x} cy={city.y} r={0.55} fill={city.cyan ? CYAN : EMERALD} fillOpacity={0.95} />
        </g>
      ))}
    </svg>
  );
}

export default FranceConstellation;
