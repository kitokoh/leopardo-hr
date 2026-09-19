"use client";

import { PackageOpen } from "lucide-react";
import Image from "next/image";
import { useState } from "react";

interface ProductImageProps {
  src: string | null;
  alt: string;
  /** Classes du conteneur (dimensionne l'image, object-cover). */
  className?: string;
  sizes?: string;
  priority?: boolean;
}

/**
 * Visuel produit avec fallback élégant : si `image_url` est nulle ou si le
 * chargement échoue, un motif ambré discret prend le relais.
 */
export function ProductImage({ src, alt, className, sizes, priority }: ProductImageProps) {
  const [failed, setFailed] = useState(false);

  if (!src || failed) {
    return (
      <div
        role="img"
        aria-label={alt}
        className={`flex items-center justify-center bg-gradient-to-br from-amber-50 via-stone-100 to-amber-100 ${className ?? ""}`}
      >
        <PackageOpen aria-hidden="true" className="h-10 w-10 text-amber-300" />
      </div>
    );
  }

  return (
    <div className={`relative overflow-hidden bg-stone-100 ${className ?? ""}`}>
      <Image
        src={src}
        alt={alt}
        fill
        sizes={sizes ?? "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"}
        priority={priority}
        className="object-cover"
        onError={() => setFailed(true)}
      />
    </div>
  );
}
