import { Variants } from "framer-motion";

/**
 * Framer Motion Animation Variants
 */

// Page transitions
export const pageVariants: Variants = {
  initial: {
    opacity: 0,
    y: 20,
  },
  animate: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.6,
      ease: [0.22, 1, 0.36, 1],
    },
  },
  exit: {
    opacity: 0,
    y: -20,
    transition: {
      duration: 0.4,
      ease: [0.22, 1, 0.36, 1],
    },
  },
};

// Fade in animations
export const fadeInVariants: Variants = {
  initial: { opacity: 0 },
  animate: {
    opacity: 1,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

export const fadeInUpVariants: Variants = {
  initial: { opacity: 0, y: 30 },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

export const fadeInDownVariants: Variants = {
  initial: { opacity: 0, y: -30 },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

export const fadeInLeftVariants: Variants = {
  initial: { opacity: 0, x: -30 },
  animate: {
    opacity: 1,
    x: 0,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

export const fadeInRightVariants: Variants = {
  initial: { opacity: 0, x: 30 },
  animate: {
    opacity: 1,
    x: 0,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

// Scale animations
export const scaleInVariants: Variants = {
  initial: { opacity: 0, scale: 0.95 },
  animate: {
    opacity: 1,
    scale: 1,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

// Hover animations
export const hoverScaleVariants: Variants = {
  initial: { scale: 1 },
  hover: { scale: 1.05, transition: { duration: 0.3 } },
};

export const hoverLiftVariants: Variants = {
  initial: { y: 0 },
  hover: {
    y: -4,
    transition: { duration: 0.3 },
    boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.1)",
  },
};

export const hoverGlowVariants: Variants = {
  initial: { boxShadow: "0 0 0 rgba(16, 185, 129, 0)" },
  hover: {
    boxShadow: "0 20px 60px -15px rgba(16, 185, 129, 0.4)",
    transition: { duration: 0.3 },
  },
};

// Tap animations
export const tapVariants: Variants = {
  tap: { scale: 0.95 },
};

// Container animations (stagger)
export const containerVariants: Variants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.1,
      delayChildren: 0.2,
    },
  },
};

export const itemVariants: Variants = {
  hidden: { opacity: 0, y: 20 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.6, ease: "easeOut" },
  },
};

// Hero section animations
export const heroHeadlineVariants: Variants = {
  initial: { opacity: 0, y: 30 },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.8, ease: "easeOut", delay: 0.1 },
  },
};

export const heroSubheadlineVariants: Variants = {
  initial: { opacity: 0, y: 20 },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.8, ease: "easeOut", delay: 0.2 },
  },
};

export const heroCTAVariants: Variants = {
  initial: { opacity: 0, y: 20 },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.8, ease: "easeOut", delay: 0.3 },
  },
};

// Card animations
export const cardVariants: Variants = {
  initial: { opacity: 0, y: 30 },
  animate: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.6, ease: "easeOut" },
  },
  hover: {
    y: -8,
    transition: { duration: 0.3 },
  },
};

// Button animations
export const buttonVariants: Variants = {
  initial: { scale: 1 },
  hover: { scale: 1.02, transition: { duration: 0.2 } },
  tap: { scale: 0.98 },
};

// Badge animations
export const badgeVariants: Variants = {
  initial: { opacity: 0, scale: 0.8 },
  animate: {
    opacity: 1,
    scale: 1,
    transition: { duration: 0.4, ease: "easeOut" },
  },
};

// Accordion animations
export const accordionVariants: Variants = {
  collapsed: { height: 0, opacity: 0 },
  expanded: {
    height: "auto",
    opacity: 1,
    transition: { duration: 0.3, ease: "easeOut" },
  },
};

/**
 * GSAP Animation Configurations
 */

export const gsapAnimationConfig = {
  // Scroll trigger animations
  scrollTrigger: {
    fadeIn: {
      trigger: ".fade-in",
      start: "top 80%",
      end: "top 20%",
      scrub: 1,
      markers: false,
    },
    parallax: {
      trigger: ".parallax",
      scrub: 1,
      markers: false,
    },
    counter: {
      trigger: ".counter",
      start: "top 80%",
      markers: false,
    },
  },

  // Tween animations
  tween: {
    duration: 0.6,
    ease: "power2.out",
  },

  // Timeline animations
  timeline: {
    stagger: 0.1,
    duration: 0.6,
  },
};

/**
 * Easing functions
 */
export const easings = {
  smooth: [0.4, 0, 0.2, 1],
  bounce: [0.22, 1, 0.36, 1],
  easeIn: [0.42, 0, 1, 1],
  easeOut: [0, 0, 0.58, 1],
  easeInOut: [0.42, 0, 0.58, 1],
};

/**
 * Transition presets
 */
export const transitions = {
  fast: { duration: 0.15, ease: "easeOut" },
  base: { duration: 0.3, ease: "easeOut" },
  slow: { duration: 0.5, ease: "easeOut" },
  slowest: { duration: 0.8, ease: [0.22, 1, 0.36, 1] },
};

/**
 * Delay presets
 */
export const delays = {
  none: 0,
  xs: 0.05,
  sm: 0.1,
  md: 0.2,
  lg: 0.3,
  xl: 0.4,
};

/**
 * Stagger presets
 */
export const staggerPresets = {
  xs: 0.05,
  sm: 0.1,
  md: 0.15,
  lg: 0.2,
  xl: 0.3,
};
