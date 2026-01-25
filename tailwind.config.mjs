/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        // Custom slate tones matching the original design
        'slate-950': '#0b0f14',
        'slate-900': '#12161d',
      },
      typography: {
        DEFAULT: {
          css: {
            '--tw-prose-body': '#d4d4d4',
            '--tw-prose-headings': '#fff',
            '--tw-prose-lead': '#d4d4d4',
            '--tw-prose-links': '#67e8f9',
            '--tw-prose-bold': '#fff',
            '--tw-prose-counters': '#a3a3a3',
            '--tw-prose-bullets': '#a3a3a3',
            '--tw-prose-hr': 'rgba(255,255,255,0.1)',
            '--tw-prose-quotes': '#a3a3a3',
            '--tw-prose-quote-borders': '#22d3ee',
            '--tw-prose-captions': '#a3a3a3',
            '--tw-prose-code': '#fff',
            '--tw-prose-pre-code': '#d4d4d4',
            '--tw-prose-pre-bg': 'rgba(255,255,255,0.05)',
            '--tw-prose-th-borders': 'rgba(255,255,255,0.1)',
            '--tw-prose-td-borders': 'rgba(255,255,255,0.1)',
            // Custom overrides
            'h2': {
              fontSize: '1.5rem',
              fontWeight: '600',
              marginTop: '2rem',
              marginBottom: '1rem',
            },
            'h3': {
              fontSize: '1.25rem',
              fontWeight: '600',
              marginTop: '1.5rem',
              marginBottom: '0.75rem',
            },
            'p': {
              marginBottom: '1rem',
              lineHeight: '1.75',
            },
            'a': {
              textDecoration: 'underline',
              '&:hover': {
                color: '#a5f3fc',
              },
            },
            'code': {
              background: 'rgba(255,255,255,0.1)',
              padding: '0.2rem 0.4rem',
              borderRadius: '0.25rem',
              fontSize: '0.875em',
              fontWeight: '400',
            },
            'code::before': {
              content: '""',
            },
            'code::after': {
              content: '""',
            },
            'pre': {
              background: 'rgba(255,255,255,0.05)',
              padding: '1rem',
              borderRadius: '0.5rem',
              overflowX: 'auto',
              marginBottom: '1rem',
              fontFamily: "'Consolas', 'Monaco', 'Courier New', monospace",
              lineHeight: '1.3',
              fontSize: '0.75rem',
              letterSpacing: '0',
            },
            'pre code': {
              background: 'transparent',
              padding: '0',
            },
            'blockquote': {
              borderLeftColor: '#22d3ee',
              paddingLeft: '1rem',
              marginLeft: '0',
              fontStyle: 'italic',
            },
            'table': {
              width: '100%',
              borderCollapse: 'collapse',
              marginBottom: '1.5rem',
            },
            'th': {
              padding: '0.75rem 1rem',
              textAlign: 'left',
              borderBottom: '1px solid rgba(255,255,255,0.1)',
              fontWeight: '600',
              background: 'rgba(255,255,255,0.05)',
            },
            'td': {
              padding: '0.75rem 1rem',
              textAlign: 'left',
              borderBottom: '1px solid rgba(255,255,255,0.1)',
            },
            'tr:hover': {
              background: 'rgba(255,255,255,0.03)',
            },
            'img': {
              borderRadius: '0.5rem',
              marginTop: '1.5rem',
              marginBottom: '1.5rem',
            },
            'strong': {
              color: '#fff',
            },
          },
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
};
