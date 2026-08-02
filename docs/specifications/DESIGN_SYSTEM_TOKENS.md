# Design System Leopardo RH

## Brand & Style

The design system is engineered for a high-end HR SaaS environment, focusing on clarity, transparency, and a premium "executive" feel. The brand personality is professional yet innovative, positioning human resources as a forward-thinking strategic partner rather than a purely administrative function.

The visual style is **Glassmorphism**. It utilizes multi-layered translucent surfaces, backdrop blurs, and subtle inner glows to create a sense of depth and lightness. This approach avoids the heaviness of traditional enterprise software, favoring a spacious, airy interface that reduces cognitive load for HR professionals managing complex data.

## Colors

The palette is anchored by **Emerald Green**, chosen for its associations with growth, vitality, and positive action. 

- **Primary (#10B981):** Used for primary call-to-actions, active states, and critical success indicators.
- **Surface:** The dark mode utilizes a deep Navy/Slate foundation (`#0b1326`) to allow the glass effects to shine. 
- **Glass Effects:** Surfaces use varying degrees of transparency. Level 1 glass (backgrounds) is more opaque for legibility, while Level 2 glass (overlays/modals) is more translucent with higher blur values.
- **Accents:** Secondary greens (`#34d399`) are used for data visualization and subtle highlights within the glass cards.

## Typography

This design system relies exclusively on **Inter** to maintain a systematic, utilitarian, yet modern appearance. 

The type hierarchy is designed for high-density data environments. Headlines use tighter letter spacing and heavier weights to maintain authority, while body text uses a generous line height (1.6) to ensure readability in long-form employee records or policy documents. Labels are often set in Medium or SemiBold to differentiate them from static content at a glance.

## Layout & Spacing

The layout follows a **Fluid Grid** model with a fixed sidebar. 

- **Sidebar:** A constant 280px glass panel on the left, providing a persistent anchor for navigation.
- **Main Content:** A flexible area with a minimum 32px padding from the viewport edges.
- **The 8px Rhythm:** All spatial relationships (margins, padding, gaps) are multiples of 8px. 
- **Responsiveness:** On tablet devices, the sidebar collapses into an icon-only rail (80px). On mobile, it becomes a bottom navigation bar or a hidden drawer, and the main container padding reduces to 16px.

## Elevation & Depth

Depth is achieved through **Glassmorphism and Backdrop Blurs** rather than traditional drop shadows.

- **Level 0 (Background):** Solid dark neutral (`#0b1326`) or subtle gradient mesh.
- **Level 1 (Main Cards/Sidebar):** Glass background (15% white or 60% dark slate) with a `backdrop-filter: blur(20px)` and a 1px inner border (white at 10% opacity) to simulate a light-catching edge.
- **Level 2 (Modals/Popovers):** Higher transparency glass with a `backdrop-filter: blur(40px)` and a subtle ambient outer shadow (Emerald tinted) to suggest floating above Level 1.

## Shapes

The design system uses a **Rounded (8px base)** corner language. 

- Standard components (Buttons, Inputs, Small Cards) use **0.5rem (8px)**.
- Large containers and Metric Cards use **1rem (16px)** to emphasize the "object" feel of the glass panels.
- Status chips and toggle switches use a full pill-shape (999px) to contrast against the structured grid of the cards.

## Components

### Glass Cards
The core container for HR metrics. They must feature a subtle top-down gradient stroke to define their edges. Content inside should have ample padding (minimum 24px).

### Sidebar Navigation
The sidebar is a full-height glass panel. Active links are indicated by a vertical Emerald Green bar on the left edge and a subtle background tint (Emerald at 10% opacity).

### Data Tables
Tables should be "ghost" style. Row separators are thin, low-opacity lines. The header row is pinned with a slightly more opaque glass background. Every third row may have a subtle tint for zebra-striping if data is heavy.

### Buttons & Inputs
- **Primary Button:** Solid Emerald Green with white text. No glass effect here—actions must be the most solid, high-contrast elements in the UI.
- **Input Fields:** Glass backgrounds with a 1px border. On focus, the border glows Emerald Green.

### Metric Indicators
Use high-contrast Emerald for positive trends and a muted, translucent red for negative trends, ensuring the colors feel like they are "projected" onto the glass surfaces.
