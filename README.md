# Personal Site

This repository contains a small PHP personal site for Zachary Shepelsky. It uses Twig templates for rendering and serves a single-page profile with sections for About, Skills, and Contact.

## Stack

- PHP
- Twig
- Static assets in `public/assets`

## How It Works

- `public/index.php` bootstraps Composer autoloading, builds the page data, and renders the Twig view.
- `templates/` contains the layout, page template, and shared partials.
- `public/assets/` contains the site CSS and JavaScript.

## Local Development

Install dependencies if needed:

```bash
composer install
```

Start the local PHP development server with:

```bash
make run
```

By default, this runs:

```bash
php -S 127.0.0.1:8000 -t public
```

Then open:

```text
http://127.0.0.1:8000
```

You can override the defaults when starting the server:

```bash
make run PORT=8080
make run HOST=0.0.0.0 PORT=8080
```

## Quick Start (alternate)

If you prefer a single command without Makefile:

```bash
php -S 0.0.0.0:8000 -t public
```

Open http://127.0.0.1:8000 or http://<your-lan-ip>:8000 to preview.

## Production notes

- Generate WebP images and include srcset for responsive loading.
- Serve static assets (CSS/JS/images) with cache headers and gzip compression.
- Use a platform like Heroku (Procfile included) or any PHP-capable host.

## TODO

- Write more content: subtext, bio, pictures, skills, and contact section;
- Separate the sections more clearly
- Make the skills section look really cool
- Fix the links so they all have uniform hovering (make the hovers super cool);
- Add SEO and AI discovery improvements; done
- Set up deployment for Heroku
- fix alt text; done
- for the hover on the nav.. if entering on left side have it go left to right, if entering from right side, have it go right to left; done
- make linkedin symbol white and add a hover effect; done
- when you click to open the mobile nav, make an animation to switch the burger icon into an x; done
- update so nav links dont append the url; done

## Skills to Write about:
HTML
CSS
JavaScript
PHP
Symfony (Twig)
AI
Vibe Coding
Git
Version Control
SEO Optimization
AEO Optimization
Social Media Management
