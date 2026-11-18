/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        cream: '#F8F6F0',
        creamDark: '#EFECE4',
        electric: '#001AFF',
        electricHover: '#0012B8',
        ink: '#121212',
        muted: '#686763',
        hairline: '#DEDAD0',
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        display: ['"Space Grotesk"', 'Inter', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
    },
  },
  plugins: [],
};
