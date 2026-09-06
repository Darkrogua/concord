import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

const Concord = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#eaf2ff',
      100: '#d6e6ff',
      200: '#adc8ff',
      300: '#84a9ff',
      400: '#5b8bf8',
      500: '#2563eb',
      600: '#1d4ed8',
      700: '#1e40af',
      800: '#1e3a8a',
      900: '#172554',
      950: '#0f172a',
    },
    colorScheme: {
      light: {
        surface: {
          0: '#ffffff',
          50: '#e7eef2',
          100: '#dce5eb',
          200: '#c7d3db',
          300: '#a8b8c3',
          400: '#7d909e',
          500: '#5b6e7c',
          600: '#455663',
          700: '#34434e',
          800: '#22303a',
          900: '#14202b',
          950: '#0c141c',
        },
      },
      dark: {
        surface: {
          0: '#14202b',
          50: '#0f161c',
          100: '#18232d',
          200: '#22303a',
          300: '#34434e',
          400: '#455663',
          500: '#7d909e',
          600: '#a8b8c3',
          700: '#c7d3db',
          800: '#dce5eb',
          900: '#e8eef2',
          950: '#f4f7f9',
        },
      },
    },
  },
})

export default Concord
