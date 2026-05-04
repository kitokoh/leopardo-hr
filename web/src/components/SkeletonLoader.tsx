'use client';

import React from 'react';

interface SkeletonLoaderProps {
  type?: 'card' | 'image' | 'text' | 'avatar' | 'line' | 'paragraph' | 'custom';
  count?: number;
  width?: string | number;
  height?: string | number;
  className?: string;
  animated?: boolean;
}

/**
 * SkeletonLoader Component
 * Provides loading placeholders for content while it's being fetched
 * Improves perceived performance and user experience
 */
export function SkeletonLoader({
  type = 'card',
  count = 1,
  width = '100%',
  height = '200px',
  className = '',
  animated = true,
}: SkeletonLoaderProps) {
  const animationClass = animated ? 'animate-pulse' : '';

  const baseClasses = `bg-gray-200 dark:bg-gray-700 rounded ${animationClass}`;

  const renderSkeleton = () => {
    switch (type) {
      case 'card':
        return (
          <div className={`${baseClasses} p-4 space-y-4 ${className}`}>
            <div className={`${baseClasses} h-48 w-full`} />
            <div className={`${baseClasses} h-4 w-3/4`} />
            <div className={`${baseClasses} h-4 w-1/2`} />
            <div className="flex gap-2">
              <div className={`${baseClasses} h-8 w-20`} />
              <div className={`${baseClasses} h-8 w-20`} />
            </div>
          </div>
        );

      case 'image':
        return (
          <div
            className={`${baseClasses} ${className}`}
            style={{ width, height }}
          />
        );

      case 'avatar':
        return (
          <div
            className={`${baseClasses} rounded-full ${className}`}
            style={{ width: height, height }}
          />
        );

      case 'text':
        return (
          <div className={`${baseClasses} h-4 ${className}`} style={{ width }} />
        );

      case 'line':
        return (
          <div className={`${baseClasses} h-2 ${className}`} style={{ width }} />
        );

      case 'paragraph':
        return (
          <div className={`space-y-2 ${className}`}>
            <div className={`${baseClasses} h-4 w-full`} />
            <div className={`${baseClasses} h-4 w-5/6`} />
            <div className={`${baseClasses} h-4 w-4/6`} />
          </div>
        );

      case 'custom':
      default:
        return (
          <div
            className={`${baseClasses} ${className}`}
            style={{ width, height }}
          />
        );
    }
  };

  return (
    <div className="space-y-4">
      {Array.from({ length: count }).map((_, index) => (
        <div key={index}>{renderSkeleton()}</div>
      ))}
    </div>
  );
}

/**
 * CardSkeleton Component
 * Skeleton loader for card components
 */
export function CardSkeleton({ count = 1, className = '' }: { count?: number; className?: string }) {
  return (
    <div className={`grid gap-4 ${className}`}>
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="bg-gray-200 dark:bg-gray-700 rounded-lg p-4 space-y-4 animate-pulse"
        >
          <div className="bg-gray-300 dark:bg-gray-600 h-48 rounded" />
          <div className="space-y-2">
            <div className="bg-gray-300 dark:bg-gray-600 h-4 rounded w-3/4" />
            <div className="bg-gray-300 dark:bg-gray-600 h-4 rounded w-1/2" />
          </div>
        </div>
      ))}
    </div>
  );
}

/**
 * ImageSkeleton Component
 * Skeleton loader for images
 */
export function ImageSkeleton({
  width = '100%',
  height = '300px',
  className = '',
}: {
  width?: string | number;
  height?: string | number;
  className?: string;
}) {
  return (
    <div
      className={`bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse ${className}`}
      style={{ width, height }}
    />
  );
}

/**
 * TextSkeleton Component
 * Skeleton loader for text content
 */
export function TextSkeleton({
  lines = 3,
  className = '',
}: {
  lines?: number;
  className?: string;
}) {
  return (
    <div className={`space-y-2 ${className}`}>
      {Array.from({ length: lines }).map((_, index) => (
        <div
          key={index}
          className="bg-gray-200 dark:bg-gray-700 h-4 rounded animate-pulse"
          style={{
            width: index === lines - 1 ? '60%' : '100%',
          }}
        />
      ))}
    </div>
  );
}

/**
 * AvatarSkeleton Component
 * Skeleton loader for avatar images
 */
export function AvatarSkeleton({
  size = 'md',
  className = '',
}: {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}) {
  const sizeClasses = {
    sm: 'w-8 h-8',
    md: 'w-12 h-12',
    lg: 'w-16 h-16',
  };

  return (
    <div
      className={`bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse ${sizeClasses[size]} ${className}`}
    />
  );
}

/**
 * FeatureCardSkeleton Component
 * Skeleton loader for feature cards
 */
export function FeatureCardSkeleton({
  count = 3,
  className = '',
}: {
  count?: number;
  className?: string;
}) {
  return (
    <div className={`grid gap-6 md:grid-cols-${count} ${className}`}>
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="bg-gray-200 dark:bg-gray-700 rounded-lg p-6 space-y-4 animate-pulse"
        >
          <div className="bg-gray-300 dark:bg-gray-600 w-12 h-12 rounded" />
          <div className="bg-gray-300 dark:bg-gray-600 h-4 rounded w-3/4" />
          <div className="space-y-2">
            <div className="bg-gray-300 dark:bg-gray-600 h-3 rounded w-full" />
            <div className="bg-gray-300 dark:bg-gray-600 h-3 rounded w-5/6" />
          </div>
        </div>
      ))}
    </div>
  );
}

export default SkeletonLoader;
