/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/admin/**/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#f59e0b',
                    dark: '#d97706',
                    darker: '#b45309',
                    light: '#fbbf24',
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                gold: {
                    DEFAULT: '#f59e0b',
                    light: '#fbbf24',
                    dark: '#d97706',
                },
                charcoal: {
                    DEFAULT: '#292524',
                    light: '#44403c',
                },
                surface: '#f5f5f4',
                'text-primary': '#1c1917',
                'text-muted': '#78716c',
                border: '#e7e5e4',
            },
            fontFamily: {
                jost: ['Jost', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
