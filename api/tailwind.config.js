import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * APV L.05/L.07 - Couleur = domaine, grille partagee.
 *
 * Ce fichier doit rester aligne avec :
 *  - mobile/lib/core/theme/app_colors.dart (Flutter)
 *  - docs/COULEURS.md (source de verite documentaire)
 *
 * Toute modification de teinte se fait dans les 3 fichiers en meme temps.
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Domaines (APV L.05 - immuables une fois publies)
                rh: {
                    DEFAULT: '#10B981',
                    light: '#D1FAE5',
                    dark: '#047857',
                },
                finance: {
                    DEFAULT: '#F59E0B',
                    light: '#FEF3C7',
                    dark: '#B45309',
                },
                security: {
                    DEFAULT: '#3B82F6',
                    light: '#DBEAFE',
                    dark: '#1D4ED8',
                },
                ia: {
                    DEFAULT: '#7C3AED',
                    light: '#EDE9FE',
                    dark: '#5B21B6',
                },
                // Semantique
                success: '#10B981',
                warning: '#F59E0B',
                danger: '#EF4444',
                info: '#3B82F6',
            },
        },
    },
    plugins: [],
};
