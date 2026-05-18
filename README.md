# Personal Site

Personal portfolio site for Zachary Shepelsky, built with PHP and Twig and deployed to Heroku. The site is a focused single-page profile with sections for background, skills, and contact information.

## Overview

This project is a lightweight server-rendered site with a simple PHP entry point, Twig templates for layout and content, and static CSS/JS assets served from the `public/` directory. It is designed to stay easy to maintain, fast to deploy, and straightforward to host on Heroku.

## Features

- Server-rendered HTML with Twig templates
- Single-page portfolio layout with reusable partials
- Static asset pipeline for CSS, JavaScript, and images
- SEO metadata, sitemap, robots rules, and structured data support
- Heroku-ready deployment flow with a single `make deploy` command

## Tech Stack

- PHP 8.1+
- Twig
- Composer
- Heroku
- Make

## Project Structure

```text
public/         Web root, entry point, static assets, robots, sitemap
build/          Build output for static packaging workflows
templates/      Twig layouts, page templates, and partials
scripts/        Build, deploy, and audit scripts
Makefile        Local development and deployment commands
Procfile        Heroku process definition
```

## Local Development

Install PHP dependencies:

```bash
composer install
```

Start the local development server:

```bash
make run
```

Default server settings:

```bash
php -S 127.0.0.1:8000 -t public
```

Open the site at:

```text
http://127.0.0.1:8000
```

Override host or port when needed:

```bash
make run PORT=8080
make run HOST=0.0.0.0 PORT=8080
```

If you want to skip the Makefile, you can run the built-in PHP server directly:

```bash
php -S 0.0.0.0:8000 -t public
```

## Common Commands

```bash
make run        # Start the local PHP server
make build      # Run the build script
make audit      # Run the audit script
make deploy     # Create/update the Heroku app and deploy the site
```

## Deployment

The project is configured for Heroku deployment.

Deploy with the default configured app:

```bash
make deploy
```

Deploy to a different Heroku app:

```bash
make deploy HEROKU_APP=my-app-name
```

Optional Heroku helpers:

```bash
make heroku-create HEROKU_APP=my-app-name
make heroku-open HEROKU_APP=my-app-name
```

## Notes

- `public/index.php` is the main application entry point.
- `templates/base.html.twig` defines the shared document structure and metadata.
- `scripts/deploy.sh` packages the app and pushes it to Heroku.
- Domain DNS can be managed separately from app hosting.

## TODO
- images are too large for where they are being displayed, they should be at most, double the size of the display;
- add caching, look at what lighthouse says about it.
- align the snowboard image better so you can see your entire self.
- update hobbies bio to include creating personal projects, applications, useful tools with code. remove stay away from screens.