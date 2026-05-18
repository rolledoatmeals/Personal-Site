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
- Set up deployment for Heroku
- images are too large for where they are being displayed, they should be at most, double the size of the display;
- add caching, look at what lighthouse says about it.
- align the snowboard image better so you can see your entire self.
- update hobbies bio to include creating personal projects, applications, useful tools with code. remove stay away from screens.