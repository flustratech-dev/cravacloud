/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.jsx",
  ],
  theme: {
    extend: {
      colors: {
        primary: "#4A6FA5",
        success: "#6ABD73",
        dark: "#333",
      },
    },
  },
  plugins: [],
}
