# brianchaplow.com (Astro)

Professional portfolio site for Brian S. Chaplow - Cyber Threat Intelligence & Data Analytics.

Migrated from 11ty/Nunjucks to Astro.

## Tech Stack

- **Framework**: [Astro](https://astro.build) v5
- **Styling**: [Tailwind CSS](https://tailwindcss.com) with Typography plugin
- **Content**: Astro Content Collections (Markdown)
- **Analytics**: Umami (self-hosted)
- **Forms**: Formspree

## Project Structure

```
src/
├── components/       # Reusable Astro components
│   ├── Header.astro
│   ├── Footer.astro
│   ├── ProjectCard.astro
│   ├── TechBadge.astro
│   └── StatusIndicator.astro
├── content/
│   ├── config.ts     # Content Collections schema
│   └── projects/     # Project markdown files
├── data/
│   └── site.json     # Global site configuration
├── layouts/
│   ├── BaseLayout.astro
│   └── ProjectLayout.astro
├── pages/
│   ├── index.astro
│   ├── about.astro
│   ├── contact.astro
│   ├── resume-thanks.astro
│   └── projects/
│       ├── index.astro
│       └── [slug].astro
└── utils/
    └── dates.ts      # Date formatting utilities
public/
├── images/           # Static images
├── assets/js/        # Client-side scripts
├── favicon.ico
└── robots.txt
```

## Development

```bash
# Install dependencies
npm install

# Start dev server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

## Content

### Adding a New Project

Create a new markdown file in `src/content/projects/`:

```markdown
---
title: "Project Title"
summary: "One-line description"
description: "SEO description (optional)"
date: 2025-01-01
status: active | completed | archived
featured: true | false
technologies:
  - Tech1
  - Tech2
github: https://github.com/...
image: /images/projects/project-name/hero.png
permalink: /projects/custom-url/  # Optional custom URL
---

Your project content in Markdown...
```

### Site Configuration

Edit `src/data/site.json` to update:
- Site title and tagline
- Author information
- Navigation items
- Social links

## Deployment

Build the site with `npm run build` and deploy the `dist/` folder to any static hosting:
- Cloudflare Pages
- Netlify
- Vercel
- GitHub Pages

## Migration Notes

Ported from 11ty with the following changes:
- Nunjucks templates → Astro components
- 11ty collections → Astro Content Collections
- Inline prose styles → Tailwind Typography plugin
- `{% block %}` → `<slot />` patterns
