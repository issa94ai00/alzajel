import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// تُربط درجات indigo وviolet بمتغيرات CSS حتى يتبع التطبيق كله لون التمييز
// الذي يختاره المستخدم (انظر resources/css/app.css و useTheme.js).
const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
const themed = (name) =>
    Object.fromEntries(shades.map((s) => [s, `rgb(var(--${name}-${s}) / <alpha-value>)`]));

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', ...defaultTheme.fontFamily.sans],
            },
            spacing: {
                0.2: '0.05rem',
                4.5: '1.125rem',
                18: '4.5rem',
            },
            opacity: {
                8: '0.08',
            },
            boxShadow: {
                '2xs': '0 1px 1px 0 rgb(0 0 0 / 0.06)',
                xs: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
            colors: {
                indigo: themed('accent'),
                violet: themed('accent2'),
                slate: {
                    750: '#283548',
                    850: '#161f31',
                },
            },
        },
    },

    plugins: [forms],
};
