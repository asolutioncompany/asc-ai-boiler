# aS.c AI Boiler Framework

WordPress boilerplate framework for AI-assisted site builds. Features a decoupled architecture where site layer plugins (`aS.c AI Boiler Example`) operate completely standalone with their own theme shell and partials registry, while `aS.c AI Boiler Plugin` serves as an independent Content Synchronization Tool.

Includes a standalone example site layer (`asc-ai-example`) featuring a Portfolio Custom Post Type (with featured image and additional photo gallery support), Blog, Pages, and Partials for testing and starting new website builds.

## Structure

```
asc-ai-theme/   aS.c AI Boiler Theme (bare minimum stub)
asc-ai-plugin/  aS.c AI Boiler Plugin (Content Synchronization Tool: diffing, import/export, media sync)
asc-ai-example/ aS.c AI Boiler Example (Standalone site layer: Portfolio CPT, blog, pages, partials registry, theme shell)
```

See `ARCH.md` for architecture detail and `STYLE.md` for code style.

## Requirements

- WordPress 5.0+
- PHP 8.1+
- Composer (for autoloader)

## Setup

Run composer install in plugin directories:
```bash
cd asc-ai-plugin && composer install
cd ../asc-ai-example && composer install
```

`asc-ai-example` (`aS.c AI Boiler Example`) can be activated in WordPress and will function fully on its own. When `asc-ai-plugin` (`aS.c AI Boiler Plugin`) is active, use the Content Sync settings in WP Admin to synchronize static content files with the database.

## Verification

```bash
find . -name "*.php" -exec php -l {} +   # syntax check
```

## Security Hardening

### Restricting Access to Raw Content Files
The `content/` subdirectory in site layer plugins contains raw `.html`, `.json`, and `.txt` files used by the sync system. If you sync drafts or private posts, these files could potentially be fetched directly by browsers bypassing WordPress permissions.

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
