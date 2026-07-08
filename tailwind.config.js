import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['system-ui', '-apple-system', 'Segoe UI', 'Roboto', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Marca — inspirada en Docker
                primary: {
                    DEFAULT: '#2496ED',
                    dark: '#1B7FD1',
                    tint: '#E5F2FD',
                },
                navy: '#0D3B66',
                // Neutrales (sesgo azul)
                ink: '#10233A',
                muted: '#46607C',
                faint: '#8AA0B8',
                line: '#DCE7F1',
                canvas: '#F2F8FD',
                surface: '#FFFFFF',
                // Semánticos — semáforo (no cambian con la marca)
                success: { DEFAULT: '#167C4A', tint: '#E4F4EB' },
                warning: { DEFAULT: '#B26A0B', tint: '#FAF0DA' },
                danger: { DEFAULT: '#C62828', tint: '#FBE9E7' },
                excel: '#217346',
            },
        },
    },

    plugins: [forms],
};
