# aS.c AI Boiler Framework

WordPress boilerplate framework for AI-assisted site builds. Features a decoupled architecture where site layer plugins (`aS.c AI Boiler Example`) operate completely standalone with their own theme shell and partials registry, while `aS.c AI Boiler Plugin` serves as an independent Content Synchronization Tool. It is shipped with a minimum theme `aS.c AI Boiler Theme` but is compatible with others and page builers.

Includes a standalone example site layer (`asc-ai-example`) featuring a Portfolio Custom Post Type (with featured image and additional photo gallery support), Blog, Pages, and Partials for testing and starting new website builds.

## What's New in v1.2.1

- **Reliable Media Binding & Featured Images**: Resolved an issue where newly imported media attachments were not bound to posts during import due to in-memory cache timing, and improved post lookups across standard posts and custom post types.

## What's New in v1.2.0

- **Custom Post Meta Synchronization**: Added support for synchronizing custom post meta fields via `content-manifest.json` and the `asc_ai_boiler_post_meta_sync_keys` filter hook, supporting both raw values (`raw`) and portable comma-delimited media/post slug lists (`slug`).
- **Featured Post Settings**: Added Featured post settings toggle for Portfolio and Blog post types in `asc-ai-example` with mutually exclusive selection per post type.
- **Normalization Summaries & Accurate Counters**: Normalization messages during import and export are now summarized by total count rather than logging an entry for every individual file, and on-disk file formatting normalizations are cleanly separated from post update counters.
- **Noise Reduction in Detect Differences**: Differences only alert on individual items with actual content changes, eliminating false-positive timestamp alerts when content and formatting are in sync.

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
  - Custom Post Meta (raw values and portable slug/media lists)
  - SEO Meta Description (Yoast)
  - Social Title (OpenGraph) (Yoast)
  - X Title (Twitter) (Yoast)
  - SEO Focus Keyphrase (Yoast)
  - Media alt, title, caption, and description
  - Featured images (with automatic support for social media Open Graph & X images at 1200x627 or matching aspect ratio)
- **CMS Flexibility**: The synchronization tool allows WordPress to still be used normally as a CMS to add and edit posts, pages, and media directly in the dashboard.

## Content Synchronization Mechanics

Understanding how static content files interact with WordPress is key to a smooth development workflow:

1. **File Modification Timestamps & Suggestions**: When detecting differences, the sync tool compares the file modification time on disk with the WordPress post modification timestamp (`post_modified_gmt`). If the file on disk is newer, the tool suggests **Import**; if WordPress is newer, it suggests **Export**; if timestamps match, it prompts a manual review.
2. **File Normalization**: Canonical export form standardizes line endings (CRLF to LF), strips UTF-8 BOM markers, and trims outer whitespace. Detecting minor byte differences between raw disk files and canonical format triggers automatic normalization.
3. **Noise Reduction in Difference Alerts**: Detect Differences isolates actual content and metadata divergence for individual item alerts, while grouping files that only need timestamp or whitespace normalization into an aggregated summary by post type.
4. **Dynamic Manifest Auto-Population**: When you add new posts, pages, custom post types, or media in the WordPress dashboard and run an Export, the synchronization tool automatically writes them into `content-manifest.json`. Similarly, dropping new HTML files on disk without manifest entries allows Import fallback creation, after which the manifest is automatically regenerated.
5. **AI Desktop Workflow**: You can have AI generate `content-manifest.json` entries directly (titles, slugs, publication dates, taxonomies, media bindings, custom post meta) so that running Import on a fresh WordPress install instantly sets up the entire site without manual dashboard clicking. Subsequent exports ensure canonical slug sanitization and synchronized GMT timestamps.

## Additional Notes

- **Theme and Builder Compatibility**: While AI can be used to directly modify a theme, this framework is designed for greater portability. It decouples the design and content, allowing you to use any theme or site builder alongside the framework without locking your AI-generated assets into a specific theme structure.
- **Non-Portable Identifiers**: Some third-party plugins use database-generated numeric IDs that differ across environments (for example, Fluent Forms form IDs). Because `content-manifest.json` avoids storing WordPress database IDs for portability, site layers should be designed to resolve these references dynamically (e.g., looking up forms or items by title/slug) rather than hardcoding numeric IDs.
- **Risk Mitigation vs Direct Database Edits**: AI could technically be prompted to directly modify a database, but that exposes significant security risks, requires manual database exports and imports, and loses the crucial ability to put your website content under source control. This boilerplate solves that by storing content in flat files.
- **Source Control is Crucial**: The synchronization tool makes its best recommendations for importing and exporting, but it may not always make the correct guess. Disabling the import/export of an item could cause data loss if data is simultaneously exported and imported. Putting your content under source control protects against these edge cases; if a WordPress administrator makes a mistake or overwrites content, they can simply revert the files via version control and re-import from the plugin files.
- **Performance Optimization**: The synchronization tool itself can be deactivated in production environments to make the website lean and eliminate unnecessary backend overhead.
- **Custom Design Advantages**: Building a custom design with this framework significantly reduces the overhead of loading bloated features that might never be used, allowing for full customizations and improved page load speeds.

## Limitations

- **Conceptual Product**: This is a new conceptual framework. While it has been successfully developed utilizing the example site and two other production sites, it is still evolving. Updates to the core framework may require significant redevelopment of your existing sites.
- **Technical Expertise Recommended**: In theory, this framework can be used by non-technical WordPress users, but it is highly recommended to have technical skills. You should be comfortable looking at code, copying error log messages, and actively guiding the AI on architecture decisions.
- **Explicit Reviews Required**: AI output should not be trusted blindly. It is recommended to do explicit reviews for maintainability, performance, security, accessibility, and other architectural standards.
- **Time Investment**: Using AI to develop a custom site is still a time-consuming process that requires constant testing, iteration, and guidance.

## Structure

```
asc-ai-theme/   aS.c AI Boiler Theme (bare minimum stub)
asc-ai-plugin/  aS.c AI Boiler Plugin (Content Synchronization Tool: diffing, import/export, media sync)
asc-ai-example/ aS.c AI Boiler Example (Standalone site layer: Portfolio CPT, blog, pages, partials registry, theme shell)
```

See `ARCH.md` for architecture detail and `STYLE.md` for code style.

## Requirements

- WordPress 5.0+ (Tested up to 7.0.2)
- PHP 8.1+ (Tested up to 8.3)
- Composer (for autoloader)

## Setup

Run composer install in plugin directories:

```bash
cd asc-ai-plugin && composer install
cd ../asc-ai-example && composer install
```

`asc-ai-example` (`aS.c AI Boiler Example`) can be activated in WordPress and will function fully on its own. When `asc-ai-plugin` (`aS.c AI Boiler Plugin`) is active, use the Content Sync settings in WP Admin to synchronize static content files with the database.

## Starting a New Project

When starting a new website project, it is highly recommended to:

1. Create a dedicated repository for your new project.
2. Add the path to the `asc-ai-boiler` framework repository into your project directory so the AI can reference the framework's architecture and code.
3. If you soft-link the framework repository in your project, add it your project's `.gitignore` to prevent committing the framework into your site's repository.
4. Have your AI agent create your site layer plugin with your own slug name and website name, using `asc-ai-example` as a reference.

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
