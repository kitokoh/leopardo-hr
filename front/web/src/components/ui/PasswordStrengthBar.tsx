'use client';

/**
 * PasswordStrengthBar — indicateur visuel de force de mot de passe (#5620)
 *
 * Calcul de force natif (aucune dépendance réseau) :
 *   score 0 : longueur < 8 (trop court)
 *   score 1 : longueur ≥ 8 uniquement (faible)
 *   score 2 : + majuscule ou chiffre (moyen)
 *   score 3 : + majuscule ET chiffre (bon)
 *   score 4 : + caractère spécial (fort)
 *
 * Props :
 *   password  — valeur courante du champ mot de passe
 *   locale    — 'fr' | 'ar' | 'tr' | 'en'  (défaut : 'fr')
 */

const MIN_LENGTH = 8;

type Locale = 'fr' | 'ar' | 'tr' | 'en';

interface StrengthLabels {
  tooShort: string;
  weak: string;
  fair: string;
  good: string;
  strong: string;
}

const LABELS: Record<Locale, StrengthLabels> = {
  fr: { tooShort: 'Trop court', weak: 'Faible', fair: 'Moyen', good: 'Bon', strong: 'Fort' },
  ar: { tooShort: 'قصير جداً', weak: 'ضعيف', fair: 'متوسط', good: 'جيد', strong: 'قوي' },
  tr: { tooShort: 'Çok kısa', weak: 'Zayıf', fair: 'Orta', good: 'İyi', strong: 'Güçlü' },
  en: { tooShort: 'Too short', weak: 'Weak', fair: 'Fair', good: 'Good', strong: 'Strong' },
};

/** Score 0-4, puis label et couleur. */
function score(password: string): { score: number; label: StrengthLabels[keyof StrengthLabels]; color: string; filled: number } {
  if (password.length < MIN_LENGTH) {
    return { score: 0, label: 'tooShort', color: 'bg-slate-300 dark:bg-slate-600', filled: 0 };
  }
  let s = 1;
  if (/[A-Z]/.test(password)) s++;
  if (/[0-9]/.test(password)) s++;
  if (/[^A-Za-z0-9]/.test(password)) s++;

  const levels: Array<{ label: StrengthLabels[keyof StrengthLabels]; color: string }> = [
    { label: 'tooShort', color: '' },          // 0 (unused here)
    { label: 'weak', color: 'bg-red-500' },    // 1
    { label: 'fair', color: 'bg-orange-400' }, // 2
    { label: 'good', color: 'bg-yellow-400' }, // 3
    { label: 'strong', color: 'bg-emerald-500' }, // 4
  ];

  return { score: s, label: levels[s].label, color: levels[s].color, filled: s };
}

interface Props {
  password: string;
  locale?: string;
}

export function PasswordStrengthBar({ password, locale = 'fr' }: Props) {
  if (password.length === 0) return null;

  const loc: Locale = (['fr', 'ar', 'tr', 'en'] as Locale[]).includes(locale as Locale)
    ? (locale as Locale)
    : 'fr';

  const { label, color, filled } = score(password);
  const labels = LABELS[loc];
  const labelText = labels[label as keyof StrengthLabels];

  return (
    <div aria-live="polite" aria-label={`${labels.weak === 'Faible' ? 'Force du mot de passe' : 'Password strength'}: ${labelText}`}>
      {/* Barre segmentée 4 blocs */}
      <div className="mt-2 flex gap-1" role="img" aria-hidden="true">
        {[1, 2, 3, 4].map((segment) => (
          <div
            key={segment}
            className={[
              'h-1.5 flex-1 rounded-full transition-all duration-300',
              filled >= segment ? color : 'bg-slate-200 dark:bg-slate-700',
            ].join(' ')}
          />
        ))}
      </div>
      {/* Label textuel */}
      <p className={['mt-1 text-xs font-medium transition-colors duration-300', filled >= 3 ? 'text-emerald-600 dark:text-emerald-400' : filled >= 2 ? 'text-orange-500 dark:text-orange-400' : 'text-red-500 dark:text-red-400'].join(' ')}>
        {labelText}
      </p>
    </div>
  );
}
