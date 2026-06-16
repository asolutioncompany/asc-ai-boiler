# aS.c AI Boiler

WordPress boilerplate plugin for AI-assisted site builds. Provides a partial-based layout system, content sync, custom post types, shortcodes, admin settings, and bundled assets.

Includes an example site layer (services, projects, blog) that demonstrates full usage of the boilerplate and can be used as a reference or starting point for new builds.

## Structure

```
includes/Core/         boilerplate core: partial system, content sync, lifecycle
includes/Admin/        boilerplate admin: settings, content sync UI
includes/ExampleCore/  example site: post types, settings, sync profile
includes/ExampleAdmin/ example site: admin screens
includes/ExampleFront/ example site: front-end rendering, shortcodes
content/               HTML content and media for content sync
assets/                admin and front-end CSS/JS
templates/             PHP templates for the theme shell
scripts/               deployment and utility scripts
```

See ARCH.md for architecture detail and STYLE.md for code style.

## Requirements

- WordPress 5.0+
- PHP 8.1+
- Composer (for autoloader)

## Setup

```bash
composer install
```

Activate the plugin in WordPress. Run content sync from the plugin settings page to import the example content.

## Verification

```bash
php -l <file>   # syntax check
```

No build step. No test suite.
