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

## TODO

- Apply text compression
- Fix the `robots.txt`
- Write more content: subtext, bio, pictures, skills, and contact section
- Separate the sections more clearly
- Fix the footer styling
- Make the skills section look really cool
- Fix the links so they all have uniform hovering
- Add SEO and AI discovery improvements
- Create a sitemap
- Set up deployment for Heroku