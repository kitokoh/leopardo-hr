import { generateMetadata } from '@/modules/vitrine/lib/seo';
import { pageMetadata } from '@/modules/vitrine/lib/seo';

export const metadata = generateMetadata(pageMetadata.comptabilite);

export default function ComptabiliteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
