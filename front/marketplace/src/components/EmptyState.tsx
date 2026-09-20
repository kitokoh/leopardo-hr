import { PackageSearch, type LucideIcon } from "lucide-react";
import Link from "next/link";

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description?: string;
  action?: { href: string; label: string };
}

/** État vide/erreur réutilisable — centré, sobre. */
export function EmptyState({ icon: Icon = PackageSearch, title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
      <span className="rounded-full bg-amber-50 p-4">
        <Icon aria-hidden="true" className="h-8 w-8 text-amber-500" />
      </span>
      <h2 className="text-lg font-semibold text-stone-900">{title}</h2>
      {description ? <p className="max-w-md text-sm text-stone-500">{description}</p> : null}
      {action ? (
        <Link
          href={action.href}
          className="mt-2 rounded-full bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700"
        >
          {action.label}
        </Link>
      ) : null}
    </div>
  );
}
