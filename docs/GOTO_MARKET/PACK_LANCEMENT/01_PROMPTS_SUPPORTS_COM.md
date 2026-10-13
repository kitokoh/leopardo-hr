# Prompts supports de communication — prêts à l'emploi

**Issue :** #8445 · **Concept :** `00_CONCEPT.md`

> Prompts rédigés en anglais (meilleurs résultats sur les générateurs),
> avec intention et cadrage en français. Palette marque : ardoise profonde
> `#0f172a`, émeraude `#34d399`, texte clair `#ecfeff`. Mascotte : léopard
> (vitesse, précision, élégance). Éviter : clichés corporate, poignées de
> main, tableaux Excel.

## 1. Vidéo héro vitrine (15–20 s, boucle, 16:9)

**Intention :** le « wow » offline — un service qui continue quand le réseau tombe.

```
Cinematic restaurant evening service: a waiter takes an order on a smartphone,
tickets appear instantly on a kitchen display screen, a POS terminal processes
payment. Suddenly the wifi router light turns red — nothing stops, service
continues smoothly. A subtle emerald green data trail reconnects everything
at the end. Dark slate blue ambiance (#0f172a), emerald accent lighting
(#34d399), shallow depth of field, smooth camera dolly, photorealistic,
4K, 24fps, no text overlay.
```

Variante station-service :

```
Night scene at a modern gas station: an attendant closes a cash shift on a
tablet, pumps keep running, dashboard screens sync in the background. Power
of continuity: internet icon blinks off, operations never pause. Dark slate
tones, emerald accents, cinematic lighting, photorealistic, 4K, no text.
```

## 2. Vidéo démo produit « Onboarding 10 minutes » (60–90 s, 16:9)

**Intention :** montrer le vrai parcours (captures réelles à incruster).

```
Screen-recording style motion design: a clean SaaS onboarding flow, five
steps shown as elegant cards ("Votre entreprise", "Votre métier", "Vos sites",
"Votre équipe", "Vos apps"), a progress bar filling, then a restaurant
dashboard coming alive with orders, kitchen tickets and POS sales animating
in real time. UI in dark slate with emerald accents, smooth transitions,
modern motion graphics, 1080p. Leave safe margins for captions.
```

Plan de montage : 0–10 s accroche « Créez votre compte » → 10–40 s les 5
étapes (vraies captures) → 40–70 s le service (serveur → cuisine → caisse) →
70–90 s tagline « Votre entreprise tourne. Même hors-ligne. » + CTA.

## 3. Cuts réseaux sociaux (9:16, 15 s)

```
Vertical video, fast cut: 1) waiter taps order on phone, 2) kitchen screen
bumps ticket, 3) POS receipt prints, 4) manager watches live dashboard on
phone from home. Bold caption space at top third. Energetic but premium,
dark slate + emerald, photorealistic, no dialogue.
```

## 4. Visuels image (générateur type GPT Image / Midjourney)

### 4.1 Héro vitrine (21:9)

```
A sleek geometric leopard made of emerald green light trails leaping across
a dark slate blue (#0f172a) abstract landscape of floating UI cards
(restaurant order, kitchen ticket, POS receipt, staff schedule). Minimal,
premium tech aesthetic, soft glow, lots of negative space for headline text,
ultra clean vector-3D hybrid style, no text.
```

### 4.2 Illustration 404 (1:1)

```
A cute minimalist leopard sitting and looking confused at a disconnected
cable, flat vector illustration, dark slate background (#0f172a), emerald
green accents (#34d399), soft rounded shapes, generous negative space,
no text.
```

### 4.3 Empty states (1:1, déclinable)

```
Minimal flat illustration of an empty restaurant tray with a small emerald
leopard paw print on it, dark slate background, soft rounded geometry,
premium SaaS illustration style, no text.
```

### 4.4 Posts réseaux (1:1 et 4:5)

```
Split-screen visual: left side a chaotic paper-based restaurant (paper
tickets flying, calculator, stress), right side the same scene calm and
digital with a clean emerald dashboard on a tablet. Flat premium
illustration style, dark slate + emerald palette, strong contrast,
space for a bold headline, no text.
```

### 4.5 OG banner / bannières (1200×630)

```
Wide banner: abstract constellation of app icons (waiter app, kitchen
display, POS terminal, employee badge, payroll document) orbiting a
glowing emerald leopard logo mark, dark slate background, premium tech
style, clean composition with center-left safe zone for title, no text.
```

## 5. Vidéo témoignage (tournage réel — brief)

- **Casting** : vrai patron (Karim, restaurant) — pas d'acteur.
- **Structure 45 s** : douleur (10 s) → bascule (10 s) → preuve chiffrée
  (15 s) → tagline (10 s).
- **Plan imposé** : le téléphone qui passe en mode avion pendant le service,
  la caisse qui continue. Ce plan EST la marque.
- Sous-titres fr + ar ; format 16:9 + cut 9:16.

## 6. Print / salon (A4, roll-up)

```
Roll-up banner design: a premium dark slate background, a geometric emerald
leopard leaping upward on the right third, left side clean white headline
zone, bottom strip with 4 language flags (FR EN AR TR) and a QR code.
Minimal, lots of dark space, print-ready, 300dpi, CMYK friendly.
```

Accroche roll-up : « Votre entreprise tourne. Même hors-ligne. » + QR vers
la démo restaurant.

## 7. Règles d'usage des prompts

- Toujours générer **sans texte** : les libellés sont ajoutés au montage
  (i18n 4 langues obligatoire).
- Décliner chaque visuel validé en 4 langues × 3 formats (16:9, 1:1, 9:16).
- Archiver les assets bruts dans `shared/mediaForMarketing/`.
- Convention UTM sur tous les liens : `CONVENTION_UTM.md`.
