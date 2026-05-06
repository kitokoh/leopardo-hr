'use client';

import React from 'react';

export type ContainerSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';

interface ContainerProps extends React.HTMLAttributes<HTMLDivElement> {
  size?: ContainerSize;
  children: React.ReactNode;
}

const sizeStyles: Record<ContainerSize, string> = {
  sm: 'max-w-2xl',
  md: 'max-w-4xl',
  lg: 'max-w-6xl',
  xl: 'max-w-7xl',
  full: 'w-full',
};

export function Container({
  size = 'xl',
  className = '',
  children,
  ...props
}: ContainerProps) {
  const combinedClassName = `
    ${sizeStyles[size]}
    mx-auto px-4 sm:px-6 lg:px-8
    ${className}
  `.trim();

  return (
    <div className={combinedClassName} {...props}>
      {children}
    </div>
  );
}
