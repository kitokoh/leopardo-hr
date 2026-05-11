'use client';

import React from 'react';

export type DividerVariant = 'horizontal' | 'vertical' | 'gradient';

interface DividerProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: DividerVariant;
  spacing?: 'sm' | 'md' | 'lg';
}

const spacingStyles = {
  sm: 'my-4',
  md: 'my-8',
  lg: 'my-12',
};

const variantStyles: Record<DividerVariant, string> = {
  horizontal: 'h-px bg-slate-200 dark:bg-slate-800 w-full',
  vertical: 'w-px bg-slate-200 dark:bg-slate-800 h-full',
  gradient: 'h-px bg-gradient-to-r from-transparent via-slate-300 dark:via-slate-700 to-transparent w-full',
};

export function Divider({
  variant = 'horizontal',
  spacing = 'md',
  className = '',
  ...props
}: DividerProps) {
  const combinedClassName = `
    ${spacingStyles[spacing]}
    ${variantStyles[variant]}
    ${className}
  `.trim();

  return <div className={combinedClassName} {...props} />;
}
