import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

const Concord = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#e8f5f1',
      100: '#c5e6dc',
      200: '#9dd4c4',
      300: '#6bbba6',
      400: '#3d9a86',
      500: '#0b6b5a',
      600: '#0a5c4e',
      700: '#084a3f',
      800: '#063830',
      900: '#042620',
      950: '#021411',
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
