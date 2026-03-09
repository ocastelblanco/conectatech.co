/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{html,ts,scss}",
    "./node_modules/primeng/**/*.{js,mjs}"
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        'cnt-midnight': '#1D2B36',
        'cnt-blue':     '#4A90E2',
        'cnt-green':    '#52A467',
        'cnt-coral':    '#FF7F50',
      },
      fontFamily: {
        heading: ['Montserrat', 'sans-serif'],
        body:    ['Inter', 'sans-serif'],
      },
      borderRadius: {
        'card': '12px',
        'btn':  '8px',
      }
    }
  },
  plugins: [
    require('tailwindcss-primeui')
  ]
}
