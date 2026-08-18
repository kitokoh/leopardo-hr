'use client';

import React from 'react';
import { Loader2 } from 'lucide-react';

/**
 * Button — composant partagé des surfaces portail/vitrine (#4942).
 *
 * Unifie le double système de boutons (composant `vitrine/common/Button` vs
 * `<button className>` inline du dashboard) : variants, tailles, états
 * loading/disabled, icône, largeur pleine — tout en laissant passer
 * `className` pour les styles bespoke (le rendu visuel de chaque écran est
 * préservé lors de la migration).
 */
export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
  loading?: boolean;
  fullWidth?: boolean;
  children?: React.ReactNode;
}

const variantBase: Record<ButtonVariant, string> = {
  // Gradients emerald (design system Leopardo) — base commune, surchargeable
  // par className (ex. boutons auth full-width rounded-2xl uppercase).
  primary: 'bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-70',
  secondary: 'bg-slate-100 text-slate-700 hover:bg-slate-200 disabled:opacity-60',
  outline: 'border border-slate-200 text-slate-700 hover:border-emerald-300 hover:text-emerald-700 disabled:opacity-60',
  ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-50',
  danger: 'bg-red-50 text-red-700 hover:bg-red-100 disabled:opacity-60',
};

const sizeBase: Record<ButtonSize, string> = {
  sm: 'px-3 py-1.5 text-xs rounded-lg',
  md: 'px-4 py-2 text-sm rounded-xl',
  lg: 'px-6 py-3 text-sm rounded-xl',
};

export function Button({
  variant = 'primary',
  size = 'md',
  icon,
  iconPosition = 'left',
  loading = false,
  fullWidth = false,
  disabled = false,
  className = '',
  children,
  type = 'button',
  ...props
}: ButtonProps) {
  const classes = [
    'inline-flex items-center justify-center gap-2 font-semibold transition-all duration-200',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2',
    'disabled:cursor-not-allowed',
    variantBase[variant],
    sizeBase[size],
    fullWidth ? 'w-full' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  const iconEl = loading ? (
    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
  ) : icon ? (
    icon
  ) : null;

  return (
    <button
      type={type}
      disabled={disabled || loading}
      className={classes}
      aria-busy={loading || undefined}
      {...props}
    >
      {iconEl && iconPosition === 'left' && iconEl}
      {children}
      {iconEl && iconPosition === 'right' && iconEl}
    </button>
  );
}
