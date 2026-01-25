import { defineConfig } from 'astro/config';
import mdx from '@astrojs/mdx';
import tailwind from '@astrojs/tailwind';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://brianchaplow.com',
  integrations: [
    mdx(),
    tailwind(),
    sitemap({
      filter: (page) => !page.includes('/resume-thanks/'),
    }),
  ],
  markdown: {
    shikiConfig: {
      theme: 'github-dark',
    },
  },
});
