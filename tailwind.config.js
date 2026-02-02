/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './**/*.html.twig',
    './assets/**/*.js',
    './components/**/*.html.twig',
    './pages/**/*.html.twig',
  ],
  theme: {
    extend: {
      colors: {
        // Main colors from design
        primary: {
          DEFAULT: '#000000',
          50: '#f6f6f6',
          100: '#e7e7e7',
          200: '#d1d1d1',
          300: '#b0b0b0',
          400: '#888888',
          500: '#6d6d6d',
          600: '#5d5d5d',
          700: '#4f4f4f',
          800: '#454545',
          900: '#3d3d3d',
          950: '#000000',
        },
        surface: {
          DEFAULT: '#f5f5f5',
          light: '#fafafa',
          dark: '#e5e5e5',
        },
        accent: {
          DEFAULT: '#c9a227', // Gold accent
          light: '#e5c95c',
          dark: '#a68518',
          hover: '#b8922a',
        },
        // Semantic colors
        danger: {
          DEFAULT: '#ef4444',
          hover: '#dc2626',
          dark: '#b91c1c',
        },
        success: {
          DEFAULT: '#059669',
          hover: '#047857',
        },
        warning: {
          DEFAULT: '#d97706',
          hover: '#b45309',
        },
        info: {
          DEFAULT: '#0284c7',
          hover: '#0369a1',
        },
        'logged-in': {
          DEFAULT: '#16a34a',
          hover: '#15803d',
        },
        stars: '#fbbf24',
        // Legacy color aliases for backward compatibility
        error: '#ef4444',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', 'sans-serif'],
      },
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],
        'base': ['1rem', { lineHeight: '1.5rem' }],
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1.2' }],
      },
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem',
      },
      borderRadius: {
        'xl': '1rem',
        '2xl': '1.5rem',
        '3xl': '2rem',
      },
      boxShadow: {
        'soft': '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
        'card': '0 0 0 1px rgba(0,0,0,0.05), 0 2px 8px rgba(0,0,0,0.08)',
      },
      transitionTimingFunction: {
        'out-expo': 'cubic-bezier(0.19, 1, 0.22, 1)',
      },
      transitionDuration: {
        '150': '150ms',
        '200': '200ms',
        '300': '300ms',
        '400': '400ms',
        '600': '600ms',
        '800': '800ms',
      },
      animation: {
        // Existing animations
        'fade-in': 'fadeIn 0.3s ease-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
        // New animations
        'cart-bounce': 'cartBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        'heart-bounce': 'heartBounce 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        'heart-implode': 'heartImplode 0.4s ease-out forwards',
        'heart-shrink': 'heartShrink 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        'delete-pulse': 'deleteIconPulse 0.8s ease-in-out infinite',
        'spin-slow': 'spin 1s linear infinite',
        'toast-progress': 'toastProgress var(--toast-duration, 5s) linear forwards',
        'burst-particle': 'burstParticle 0.6s ease-out forwards',
        'add-particle-burst': 'addParticleBurst 0.6s ease-out forwards',
      },
      keyframes: {
        // Existing keyframes
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideDown: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        // New keyframes
        cartBounce: {
          '0%': { transform: 'scale(1)' },
          '30%': { transform: 'scale(1.3)' },
          '50%': { transform: 'scale(0.9)' },
          '70%': { transform: 'scale(1.1)' },
          '100%': { transform: 'scale(1)' },
        },
        heartBounce: {
          '0%': { transform: 'scale(1)' },
          '15%': { transform: 'scale(1.35)' },
          '30%': { transform: 'scale(1)' },
          '45%': { transform: 'scale(1.15)' },
          '60%': { transform: 'scale(1)' },
          '75%': { transform: 'scale(1.05)' },
          '100%': { transform: 'scale(1)' },
        },
        heartImplode: {
          '0%': { transform: 'scale(1)', opacity: '1' },
          '30%': { transform: 'scale(1.3)', opacity: '1' },
          '60%': { transform: 'scale(0.4)', opacity: '0.8' },
          '80%': { transform: 'scale(0.6)', opacity: '0.5' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
        heartShrink: {
          '0%': { transform: 'scale(1)' },
          '20%': { transform: 'scale(1.2)' },
          '40%': { transform: 'scale(0.85)' },
          '60%': { transform: 'scale(1.08)' },
          '80%': { transform: 'scale(0.95)' },
          '100%': { transform: 'scale(1)' },
        },
        deleteIconPulse: {
          '0%, 100%': { transform: 'scale(1)', opacity: '1' },
          '50%': { transform: 'scale(1.1)', opacity: '0.8' },
        },
        toastProgress: {
          '0%': { transform: 'scaleX(1)' },
          '100%': { transform: 'scaleX(0)' },
        },
        burstParticle: {
          '0%': { transform: 'scale(0) rotate(0deg)', opacity: '1' },
          '50%': { transform: 'scale(1) rotate(180deg)', opacity: '0.8' },
          '100%': { transform: 'scale(0) rotate(360deg)', opacity: '0' },
        },
        addParticleBurst: {
          '0%': { transform: 'scale(0)', opacity: '1' },
          '50%': { transform: 'scale(1.2)', opacity: '0.6' },
          '100%': { transform: 'scale(0)', opacity: '0' },
        },
      },
      zIndex: {
        'dropdown': '50',
        'sticky': '100',
        'drawer': '200',
        'modal': '250',
        'popover': '300',
        'toast': '9999',
      },
    },
    screens: {
      'sm': '640px',
      'md': '768px',
      'lg': '1024px',
      'xl': '1280px',
      '2xl': '1536px',
    },
  },
  plugins: [],
}
