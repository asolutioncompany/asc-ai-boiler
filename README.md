# aS.c Boiler

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

## Security Hardening

### Restricting Access to Raw Content Files
The `content/` subdirectory in the `asc-ai-example` plugin contains raw `.html`, `.json`, and `.txt` files used by the sync system. If you sync drafts or private posts, these files could potentially be fetched directly by browsers bypassing WordPress permissions.

To prevent direct download of these files, it is recommended to configure your web server to block access to these extensions inside the content folder.

#### Apache (.htaccess)
You can place a `.htaccess` file inside `asc-ai-example/content/` with the following contents:
```apache
<FilesMatch "\.(html|json|txt)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>
```

#### Nginx Configuration
Add the following rule to your server configuration block:
```nginx
location ~* /wp-content/plugins/asc-ai-example/content/.*\.(html|json|txt)$ {
    deny all;
    access_log off;
    log_not_found off;
}
```

