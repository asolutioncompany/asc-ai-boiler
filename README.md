# aS.c AI Boiler Framework

WordPress boilerplate framework for AI-assisted site builds. Features a decoupled architecture where site layer plugins (`aS.c AI Boiler Example`) operate completely standalone with their own theme shell and partials registry, while `aS.c AI Boiler Plugin` serves as an independent Content Synchronization Tool.

Includes a standalone example site layer (`asc-ai-example`) featuring a Portfolio Custom Post Type (with featured image and additional photo gallery support), Blog, Pages, and Partials for testing and starting new website builds.

## Live Preview

You can preview the framework in action by visiting the example site at [boiler.asolution.company](https://boiler.asolution.company).

## Benefits

- **AI-Assisted Desktop Development**: Develop the design and content of your website locally utilizing AI tools on your desktop.
- **Source Control**: Put your entire website design and content under version control.
- **Portability**: Makes the website design and content fully portable, allowing it to be easily migrated to a live staging or production environment.
- **Metadata Management**: Define essential metadata alongside your content to drastically reduce setup time. The content synchronization tool supports:
  - Title & Slug
  - Categories & Tags
  - Excerpt
  - Publication Date
  - SEO Meta Description
  - Social Title (OpenGraph)
  - X Title (Twitter)
  - SEO Focus Keyphrase
- **CMS Flexibility**: The synchronization tool allows WordPress to still be used normally as a CMS to add and edit posts, pages, and media directly in the dashboard.

## Additional Notes

- **Theme and Builder Compatibility**: While AI can be used to directly modify a theme, this framework is designed for greater portability. It decouples the design and content, allowing you to use any theme or site builder alongside the framework without locking your AI-generated assets into a specific theme structure.
- **Risk Mitigation vs Direct Database Edits**: AI could technically be prompted to directly modify a database, but that exposes significant security risks, requires manual database exports and imports, and loses the crucial ability to put your website content under source control. This boilerplate solves that by storing content in flat files.
- **Source Control is Crucial**: The synchronization tool makes its best recommendations for importing and exporting, but it may not always make the correct guess. Disabling the import/export of an item could cause data loss if data is simultaneously exported and imported. Putting your content under source control protects against these edge cases; if a WordPress administrator makes a mistake or overwrites content, they can simply revert the files via version control and re-import from the plugin files.
- **Performance Optimization**: The synchronization tool itself can be deactivated in production environments to make the website lean and eliminate unnecessary backend overhead.
- **Custom Design Advantages**: Building a custom design with this framework significantly reduces the overhead of loading bloated features that might never be used, allowing for full customizations and improved page load speeds.

## Limitations

- **Conceptual Product**: This is a new conceptual framework. While it has been successfully developed utilizing the example site and two other production sites, it is still evolving. Updates to the core framework may require significant redevelopment of your existing sites.
- **Technical Expertise Recommended**: In theory, this framework can be used by non-technical WordPress users, but it is highly recommended to have technical skills. You should be comfortable looking at code, copying error log messages, and actively guiding the AI on architecture decisions.
- **Explicit Reviews Required**: AI output should not be trusted blindly. It is recommended to do explicit reviews for maintainability, performance, security, accessibility, and other architectural standards.
- **Time Investment**: Using AI to develop a custom site is a time-consuming process that requires constant testing, iteration, and guidance. It can often take longer than simply using an off-the-shelf page builder and manually configuring plugins.
- **The Trade-off**: Despite the time investment and technical requirements, this approach comes with the immense benefit of achieving a fully custom, performant website whose entire design and content can be reliably placed under source control.

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
