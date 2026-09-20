import { LoadingGrid } from "@/components/LoadingGrid";

export default function ProductsLoading() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-8">
      <div className="mb-6 h-9 w-64 animate-pulse rounded-lg bg-stone-200" />
      <div className="mb-8 h-20 animate-pulse rounded-2xl bg-stone-100" />
      <LoadingGrid count={8} />
    </div>
  );
}
