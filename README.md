# Startup Sites PH

Marketing site for Startup Sites PH, a Philippines-based web development
studio. Built with [Astro](https://astro.build) as a static site, deployed to
Hostinger shared hosting (no Node server on the host — see **Deployment**
below).

## Prerequisites

- Node.js `20.20.x` or newer (see `package.json` → `engines`). Astro is
  pinned to `^4.16.0` in this project specifically because Astro 5+ requires
  Node ≥ 22.12 — if you upgrade Node system-wide, the Astro version can be
  bumped too.
- npm (ships with Node)

## Setup

```sh
git clone https://github.com/mikoyltnjua9/startup-sites-ph.git
cd startup-sites-ph
npm install
npm run dev
```

The dev server runs at `http://localhost:4321`.

## Scripts

| Command                | Action                                                |
| :---------------------- | :----------------------------------------------------- |
| `npm run dev`            | Starts the local dev server with hot reload            |
| `npm run build`           | Builds the static site to `./dist/`                    |
| `npm run preview`         | Serves the built `./dist/` locally, for a final check  |
| `npm run astro check`     | Type-checks all `.astro` files                          |
| `npx tsc --noEmit`        | Type-checks the standalone `.ts` files (e.g. `site.ts`) |

## Project structure

```text
src/
├── components/     # Nav, Hero, About, Team, Work, Capabilities, Services,
│                    # Testimonials, Faq, Cta, Footer
├── layouts/
│   └── BaseLayout.astro   # <html>/<head> shell, fonts, anti-FOUC theme script
├── pages/
│   ├── index.astro        # one-page homepage
│   └── contact.astro      # standalone Contact page
├── scripts/
│   └── site.ts            # single bundled script: nav dock/undock, dark
│                           # mode, scroll reveals, hero canvas background,
│                           # Work section pin/drag, testimonial carousel, etc.
└── styles/
    └── global.css          # design tokens (colors, fonts) + shared utility
                              # classes (.btn-warm, .warm-card, .section-inner)
public/
├── icons/            # hero icon images
└── videos/            # Work section background video
```

## Deployment

This site builds to static HTML/CSS/JS — Hostinger's shared hosting plan
does **not** run a Node server, so there is no server-side deploy step:

1. `npm run build`
2. Upload the contents of `./dist/` to your Hostinger `public_html/` (or
   equivalent) directory via FTP or the Hostinger File Manager.

## Known placeholders

A few things still need real content/config before launch:

- **Contact form** (`src/pages/contact.astro`) posts to a placeholder
  Formspree endpoint (`https://formspree.io/f/your-form-id`). Sign up at
  [formspree.io](https://formspree.io) (or a similar static-form service) and
  swap in your real endpoint.
- Pricing figures in `src/components/Services.astro` (`[₱PRICE]`)
- Client logos/testimonials in `src/components/Nav.astro` and
  `src/components/Testimonials.astro`
- Team photos in `src/components/Team.astro` (currently colored-initial
  placeholders)
- The `[X]` "sites shipped" stat in `src/components/Nav.astro`
