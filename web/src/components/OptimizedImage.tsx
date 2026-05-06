'use client';

import Image from 'next/image';
import { CSSProperties, useState } from 'react';
import { generateBlurDataURL } from '@/lib/image-optimization';

interface OptimizedImageProps {
  src: string;
  alt: string;
  width?: number;
  height?: number;
  priority?: boolean;
  placeholder?: 'blur' | 'empty';
  blurDataURL?: string;
  sizes?: string;
  quality?: number;
  className?: string;
  containerClassName?: string;
  objectFit?: 'contain' | 'cover' | 'fill' | 'scale-down';
  objectPosition?: string;
  onLoad?: () => void;
  onError?: () => void;
  style?: CSSProperties;
}

/**
 * OptimizedImage Component
 * Wraps Next.js Image component with sensible defaults for optimization
 * Includes lazy loading, blur placeholders, and responsive sizing
 */
export function OptimizedImage({
  src,
  alt,
  width,
  height,
  priority = false,
  placeholder = 'blur',
  blurDataURL,
  sizes,
  quality = 75,
  className = '',
  containerClassName = '',
  objectFit = 'cover',
  objectPosition = 'center',
  onLoad,
  onError,
  style,
}: OptimizedImageProps) {
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);

  const handleLoadingComplete = () => {
    setIsLoading(false);
    onLoad?.();
  };

  const handleError = () => {
    setHasError(true);
    onError?.();
  };

  // Generate blur data URL if not provided
  const finalBlurDataURL = blurDataURL || (placeholder === 'blur' ? generateBlurDataURL() : undefined);

  return (
    <div
      className={`relative overflow-hidden ${containerClassName}`}
      style={{
        aspectRatio: width && height ? `${width}/${height}` : undefined,
      }}
    >
      {hasError ? (
        // Fallback for failed images
        <div className="w-full h-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
          <span className="text-gray-400 dark:text-gray-500 text-sm">
            Image failed to load
          </span>
        </div>
      ) : (
        <Image
          src={src}
          alt={alt}
          width={width}
          height={height}
          priority={priority}
          quality={quality}
          placeholder={placeholder}
          blurDataURL={finalBlurDataURL}
          sizes={sizes}
          className={`
            ${className}
            ${isLoading ? 'blur-sm' : 'blur-0'}
            transition-all duration-300
          `}
          style={{
            objectFit,
            objectPosition,
            ...style,
          }}
          onLoadingComplete={handleLoadingComplete}
          onError={handleError}
        />
      )}

      {/* Loading skeleton */}
      {isLoading && (
        <div className="absolute inset-0 bg-gradient-to-r from-gray-200 via-gray-100 to-gray-200 dark:from-gray-700 dark:via-gray-600 dark:to-gray-700 animate-pulse" />
      )}
    </div>
  );
}

export default OptimizedImage;
