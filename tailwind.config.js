import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],

    theme: {
        extend: {
            screens: {
                // mobile < md(768) · tablet md–lg · desktop ≥ lg(1024)
                xs: '420px',
            },
            fontFamily: {
                sans: ['"Neaby Naira"', '"Inter Variable"', 'Inter', ...defaultTheme.fontFamily.sans],
                brand: ['Comfortaa', '"Plus Jakarta Sans Variable"', ...defaultTheme.fontFamily.sans],
                display: ['"Neaby Naira"', '"Plus Jakarta Sans Variable"', '"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#edfdf4',
                    100: '#d3f8e2',
                    200: '#aaf0ca',
                    300: '#71e2a9',
                    400: '#38cc83',
                    500: '#16b26a',
                    600: '#0a9156',
                    700: '#097347',
                    800: '#0b5b3a',
                    900: '#0a4b31',
                    950: '#032a1b',
                },
                ink: {
                    50: '#f6f8f7',
                    100: '#eef2f0',
                    200: '#e2e8e5',
                    300: '#c9d3ce',
                    400: '#94a39b',
                    500: '#67766e',
                    600: '#4b5952',
                    700: '#36423c',
                    800: '#222b27',
                    900: '#141a17',
                    950: '#0a0e0c',
                },
            },
            borderRadius: {
                '4xl': '2rem',
            },
            boxShadow: {
                soft: '0 1px 2px rgba(16,24,20,.04), 0 4px 16px -4px rgba(16,24,20,.08)',
                lift: '0 2px 4px rgba(16,24,20,.04), 0 16px 40px -12px rgba(16,24,20,.18)',
                glow: '0 10px 30px -8px rgba(22,178,106,.55)',
            },
            keyframes: {
                drift: {
                    '0%,100%': { transform: 'translate3d(0,0,0) scale(1)' },
                    '50%': { transform: 'translate3d(4%,-3%,0) scale(1.08)' },
                },
                float: { '0%, 100%': { translate: '0 0' }, '50%': { translate: '0 -8px' } },
                'drift-alt': {
                    '0%,100%': { transform: 'translate3d(0,0,0) scale(1)' },
                    '50%': { transform: 'translate3d(-5%,4%,0) scale(1.05)' },
                },
                rise: {
                    from: { opacity: 0, transform: 'translateY(8px)' },
                    to: { opacity: 1, transform: 'translateY(0)' },
                },
            },
            animation: {
                drift: 'drift 22s ease-in-out infinite',
                'drift-alt': 'drift-alt 28s ease-in-out infinite',
                float: 'float 7s ease-in-out infinite',
                'float-slow': 'float 9s ease-in-out -2s infinite',
                'float-alt': 'float 8s ease-in-out -4s infinite',
                rise: 'rise .35s cubic-bezier(.2,.8,.2,1) both',
            },
        },
    },

    plugins: [forms],
};
