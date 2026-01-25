import { defineCollection, z } from 'astro:content';

const projects = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    summary: z.string(),
    description: z.string().optional(),
    date: z.coerce.date(),
    status: z.enum(['active', 'completed', 'archived']),
    featured: z.boolean().default(false),
    technologies: z.array(z.string()),
    github: z.string().url().optional(),
    demo: z.string().url().optional(),
    image: z.string().optional(),
    // Custom permalink support (for v1 having different slug than filename)
    permalink: z.string().optional(),
  }),
});

export const collections = { projects };
