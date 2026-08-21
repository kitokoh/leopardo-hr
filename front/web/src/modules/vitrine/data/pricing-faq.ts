// FAQ tarifs par locale — source unique utilisée par la page /pricing (UI)
// et par le JSON-LD FAQPage (layout, issue #3921) pour que le schéma suive
// exactement le contenu visible.
//
// Les libellés (question/réponse/catégorie) vivent dans le catalogue i18n
// partagé (namespace `pricing.faq.*`, #2755 lot 2) — ce module ne garde que
// les ids techniques et reconstruit les items par locale via t().
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export type PricingFaqItem = {
  id: string;
  question: string;
  answer: string;
  category: string;
};

const FAQ_ITEM_IDS = ["starter-plan","change-plan","per-employee","free-trial","free-plan","trial-to-paid","support","data-location","gdpr","api"];

function buildFaq(locale: AppLocale): PricingFaqItem[] {
  return FAQ_ITEM_IDS.map((id, index) => ({
    id,
    question: t(locale, `pricing.faq.items.${index}.question`),
    answer: t(locale, `pricing.faq.items.${index}.answer`),
    category: t(locale, `pricing.faq.items.${index}.category`),
  }));
}

export const pricingFaqByLocale: Record<AppLocale, PricingFaqItem[]> = {
  fr: buildFaq('fr'),
  en: buildFaq('en'),
  tr: buildFaq('tr'),
  ar: buildFaq('ar'),
};

export function getPricingFaq(locale: AppLocale): PricingFaqItem[] {
  return pricingFaqByLocale[locale] ?? pricingFaqByLocale.fr;
}
